<?php

namespace App\Http\Controllers;

use App\Models\Batik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BatikController extends Controller
{
    /**
     * Menampilkan semua data batik (biasanya publik).
     */
    public function index(): JsonResponse
    {
        $batiks = Batik::all();
        return response()->json(['batiks' => $batiks]);
    }

    /**
     * Menampilkan data batik yang diunggah user yang sedang login.
     */
    public function myBatiks(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        $batiks = $user->batiks;
        return response()->json(['histories' => $batiks]);
    }

    /**
     * Menyimpan data batik baru (Create).
     */
    public function store(Request $request): JsonResponse
    {
        Log::info('Permintaan diterima untuk store batik.', $request->all());

        try {
            // Periksa apakah permintaan file ada sebelum validasi.
            if (!$request->hasFile('image')) {
                return response()->json(['error' => 'File gambar tidak ditemukan dalam permintaan.'], 422);
            }
            
            $validator = Validator::make($request->all(), [
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
                'is_minangkabau_batik' => 'required|in:true,false',
                'batik_name' => 'required_if:is_minangkabau_batik,false|nullable|string|max:255',
                'description' => 'required_if:is_minangkabau_batik,false|nullable|string',
                'origin' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                Log::error('Validasi gagal.', ['errors' => $validator->errors()]);
                return response()->json(['errors' => $validator->errors()], 422);
            }

            if (!Auth::check()) {
                Log::warning('Percobaan store batik tanpa otentikasi.');
                return response()->json(['error' => 'Anda tidak memiliki izin untuk melakukan tindakan ini.'], 403);
            }
            Log::info('User terotentikasi. ID User: ' . Auth::id());

            $isMinangkabauBatik = $request->input('is_minangkabau_batik') === 'true';

            $image = $request->file('image');
            $filename = time() . '_' . $image->getClientOriginalName();
            
            $path = $image->storeAs('public/batik_images', $filename);
            
            if (!$path) {
                Log::error('Operasi storeAs gagal.', ['filename' => $filename]);
                return response()->json(['error' => 'Gagal menyimpan file gambar.'], 500);
            }

            $imageUrl = asset(Storage::url($path));
            
            Log::info('File berhasil diunggah. Path: ' . $path . ' URL: ' . $imageUrl);

            $batikName = $request->input('batik_name');
            $description = $request->input('description');

            if (!$isMinangkabauBatik) {
                $batikName = $batikName ?? 'Bukan Batik Minangkabau';
                $description = $description ?? 'Gambar bukan motif batik Minangkabau.';
            }

            $batik = Batik::create([
                'user_id' => Auth::id(),
                'filename' => $filename,
                'path' => $path,
                'image_url' => $imageUrl,
                'original_name' => $image->getClientOriginalName(),
                'is_minangkabau_batik' => $isMinangkabauBatik,
                'batik_name' => $batikName,
                'description' => $description,
                'origin' => $request->input('origin'),
            ]);
            Log::info('Data batik berhasil disimpan. Batik ID: ' . $batik->id);

            return response()->json(['message' => 'Batik berhasil disimpan!', 'data' => $batik], 201);
        } catch (\Exception $e) {
            Log::error('Error saat menyimpan batik: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Terjadi kesalahan server.'], 500);
        }
    }

    /**
     * Menampilkan satu data batik.
     */
    public function show(Batik $batik): JsonResponse
    {
        return response()->json(['data' => $batik]);
    }

    /**
     * Menghapus batik dari riwayat.
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
            if ($batik->path) {
                if (Storage::exists($batik->path)) {
                    Storage::delete($batik->path);
                    Log::info('File berhasil dihapus.', ['path' => $batik->path]);
                } else {
                    Log::warning('File tidak ditemukan di storage, hanya menghapus data dari database.', ['path' => $batik->path]);
                }
            } else {
                Log::info('Tidak ada path file untuk dihapus. Menghapus data dari database.', ['batik_id' => $batik->id]);
            }
            
            $batik->delete();

            Log::info('Batik berhasil dihapus.', ['batik_id' => $batik->id, 'user_id' => $user->id]);

            return response()->json(['message' => 'Riwayat berhasil dihapus.'], 200);

        } catch (\Exception $e) {
            Log::error('Gagal menghapus batik: ' . $e->getMessage(), ['batik_id' => $id]);
            return response()->json(['message' => 'Gagal menghapus riwayat.'], 500);
        }
    }
}