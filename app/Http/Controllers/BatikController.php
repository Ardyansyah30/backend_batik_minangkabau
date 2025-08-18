<?php

namespace App\Http\Controllers;

use App\Models\Batik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class BatikController extends Controller
{
    /**
     * Menampilkan semua data batik (biasanya publik) dengan URL gambar.
     */
    public function index(): JsonResponse
    {
        try {
            $batiks = Batik::all()->map(function ($batik) {
                $batik->image_url = $batik->path ? Storage::url($batik->path) : null;
                return $batik;
            });
            return response()->json(['batiks' => $batiks]);
        } catch (Exception $e) {
            Log::error('Error saat mengambil data batik: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Terjadi kesalahan server saat mengambil data.'], 500);
        }
    }

    /**
     * Menampilkan data batik yang diunggah user yang sedang login dengan URL gambar.
     */
    public function myBatiks(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $batiks = $user->batiks()->get()->map(function ($batik) {
                $batik->image_url = $batik->path ? Storage::url($batik->path) : null;
                return $batik;
            });
            return response()->json(['batiks' => $batiks]);
        } catch (Exception $e) {
            Log::error('Error saat mengambil riwayat batik: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Terjadi kesalahan server saat mengambil riwayat.'], 500);
        }
    }

    /**
     * Menyimpan data batik baru (Create).
     */
    public function store(Request $request): JsonResponse
    {
        Log::info('Permintaan diterima untuk store batik.', $request->all());

        try {
            // Cek otentikasi
            if (!Auth::check()) {
                return response()->json(['error' => 'Anda tidak memiliki izin untuk melakukan tindakan ini.'], 403);
            }

            // Validasi data masukan
            $validator = Validator::make($request->all(), [
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240',
                'is_minangkabau_batik' => 'required|in:true,false',
                'batik_name' => 'required_if:is_minangkabau_batik,true|nullable|string|max:255',
                'description' => 'required_if:is_minangkabau_batik,true|nullable|string',
                'origin' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                Log::error('Validasi gagal.', ['errors' => $validator->errors()]);
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Logika menyimpan file
            $file = $request->file('image');
            $filename = time() . '_' . $file->getClientOriginalName();
            // Menggunakan Storage::disk('public')->putFileAs() untuk path yang lebih andal
            $path = $file->storeAs('batik_images', $filename, 'public');

            if (!$path) {
                Log::error('Gagal menyimpan file gambar.');
                return response()->json(['error' => 'Gagal menyimpan file gambar.'], 500);
            }

            // Menentukan nilai berdasarkan is_minangkabau_batik
            $isMinangkabauBatik = $request->input('is_minangkabau_batik') === 'true';
            
            // Atur nama dan deskripsi default jika bukan batik Minangkabau
            $batikName = $isMinangkabauBatik ? $request->input('batik_name') : 'Bukan Batik Minangkabau';
            $description = $isMinangkabauBatik ? $request->input('description') : 'Gambar bukan motif batik Minangkabau.';
            $origin = $isMinangkabauBatik ? $request->input('origin') : 'N/A';
            
            // Membuat entri baru di database
            $batik = Batik::create([
                'user_id' => Auth::id(),
                'filename' => $filename,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'is_minangkabau_batik' => $isMinangkabauBatik,
                'batik_name' => $batikName,
                'description' => $description,
                'origin' => $origin,
            ]);

            // Tambahkan URL gambar ke respons
            $batik->image_url = Storage::url($batik->path);

            Log::info('Data batik berhasil disimpan. Batik ID: ' . $batik->id);
            return response()->json(['message' => 'Batik berhasil disimpan!', 'data' => $batik], 201);

        } catch (Exception $e) {
            Log::error('Error saat menyimpan batik: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Terjadi kesalahan server.'], 500);
        }
    }

    /**
     * Menampilkan satu data batik (Read) dengan URL gambar.
     */
    public function show(Batik $batik): JsonResponse
    {
        $batik->image_url = $batik->path ? Storage::url($batik->path) : null;
        return response()->json(['data' => $batik]);
    }

    /**
     * Menghapus data batik (Delete).
     */
    public function destroy($id): JsonResponse
    {
        Log::info('Permintaan diterima untuk menghapus batik.', ['batik_id' => $id]);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $batik = Batik::where('id', $id)->where('user_id', $user->id)->first();

        if (!$batik) {
            Log::warning('Percobaan menghapus riwayat yang tidak ditemukan.', ['batik_id' => $id, 'user_id' => $user->id]);
            return response()->json(['message' => 'Riwayat tidak ditemukan.'], 404);
        }

        try {
            if ($batik->path && Storage::disk('public')->exists($batik->path)) {
                Storage::disk('public')->delete($batik->path);
                Log::info('File berhasil dihapus.', ['path' => $batik->path]);
            } else {
                Log::warning('File tidak ditemukan di storage, hanya menghapus data dari database.', ['path' => $batik->path]);
            }

            $batik->delete();

            Log::info('Batik berhasil dihapus.', ['batik_id' => $batik->id, 'user_id' => $user->id]);

            return response()->json(['message' => 'Riwayat berhasil dihapus.'], 200);

        } catch (Exception $e) {
            Log::error('Gagal menghapus batik: ' . $e->getMessage(), ['batik_id' => $id]);
            return response()->json(['message' => 'Gagal menghapus riwayat.'], 500);
        }
    }

    /**
     * Memperbarui data batik (Update) dan gambar baru.
     */
    public function update(Request $request, Batik $batik): JsonResponse
    {
        try {
            Log::info('Permintaan update batik diterima.', ['batik_id' => $batik->id]);

            if ($batik->user_id !== Auth::id()) {
                Log::warning('Percobaan update batik tanpa otentikasi.', ['batik_id' => $batik->id, 'user_id' => Auth::id()]);
                return response()->json(['message' => 'Anda tidak memiliki izin untuk mengedit batik ini.'], 403);
            }

            // Aturan validasi
            $rules = [
                'is_minangkabau_batik' => 'sometimes|in:true,false',
                'batik_name' => 'required_if:is_minangkabau_batik,true|nullable|string|max:255',
                'description' => 'required_if:is_minangkabau_batik,true|nullable|string',
                'origin' => 'nullable|string|max:255',
                'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:10240',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                Log::error('Validasi gagal.', ['errors' => $validator->errors()]);
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Proses unggahan gambar baru
            if ($request->hasFile('image')) {
                // Hapus gambar lama jika ada
                if ($batik->path && Storage::disk('public')->exists($batik->path)) {
                    Storage::disk('public')->delete($batik->path);
                }

                $file = $request->file('image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('batik_images', $filename, 'public');

                $batik->update([
                    'filename' => $filename,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                ]);
            }

            // Perbarui data teks, tambahkan origin jika ada
            $batik->update($request->except('image'));
            
            // Tambahkan URL gambar ke respons
            $batik->image_url = Storage::url($batik->path);

            Log::info('Batik berhasil diperbarui.', ['batik_id' => $batik->id]);

            return response()->json(['message' => 'Batik berhasil diperbarui.', 'data' => $batik], 200);

        } catch (Exception $e) {
            Log::error('Gagal memperbarui batik: ' . $e->getMessage(), ['batik_id' => $batik->id]);
            return response()->json(['message' => 'Gagal memperbarui batik.'], 500);
        }
    }

    /**
     * Menghapus semua riwayat batik yang diunggah oleh user yang sedang login.
     */
    public function deleteAllBatiks(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $batiks = $user->batiks()->get();

            if ($batiks->isEmpty()) {
                return response()->json(['message' => 'Tidak ada riwayat untuk dihapus.'], 404);
            }

            foreach ($batiks as $batik) {
                if ($batik->path && Storage::disk('public')->exists($batik->path)) {
                    Storage::disk('public')->delete($batik->path);
                    Log::info('File riwayat berhasil dihapus.', ['path' => $batik->path]);
                }
            }

            $user->batiks()->delete();

            Log::info('Semua riwayat batik berhasil dihapus.', ['user_id' => $user->id]);

            return response()->json(['message' => 'Semua riwayat berhasil dihapus.'], 200);

        } catch (Exception $e) {
            Log::error('Gagal menghapus semua riwayat batik: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Gagal menghapus semua riwayat.'], 500);
        }
    }
}