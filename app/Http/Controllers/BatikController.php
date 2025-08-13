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
     * Menyimpan data batik baru (Create)
     */
    public function store(Request $request): JsonResponse
    {
        Log::info('Permintaan diterima untuk store batik.', $request->all());

        try {
            // ✅ PERBAIKAN: Validasi yang disesuaikan untuk menangani hasil deteksi dan kontribusi
            $validator = Validator::make($request->all(), [
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
                // ✅ Validasi untuk is_minangkabau_batik
                'is_minangkabau_batik' => 'required|in:true,false',
                // ✅ Validasi kondisional untuk kontribusi (batik_name & description wajib)
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
            $imageUrl = Storage::url($path);
            Log::info('File berhasil diunggah. Path: ' . $path . ' URL: ' . $imageUrl);

            // Logika untuk menentukan nama dan deskripsi
            $batikName = $request->input('batik_name');
            $description = $request->input('description');

            // Jika ini hasil deteksi dan bukan batik Minangkabau, berikan nilai default
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
    
    // ... (metode show, update, dan lainnya)

    /**
     * Menghapus batik dari riwayat.
     */
    public function destroy($id): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Cari riwayat batik berdasarkan ID dan pastikan itu milik user yang sedang login
        $batik = Batik::where('id', $id)->where('user_id', $user->id)->first();

        if (!$batik) {
            return response()->json(['message' => 'Riwayat tidak ditemukan.'], 404);
        }

        try {
            // Hapus file gambar dari storage jika ada
            if ($batik->path) {
                Storage::delete($batik->path);
            }
            
            // Hapus entri dari database
            $batik->delete();

            Log::info('Batik berhasil dihapus.', ['batik_id' => $batik->id, 'user_id' => $user->id]);

            return response()->json(['message' => 'Riwayat berhasil dihapus.'], 200);

        } catch (\Exception $e) {
            Log::error('Gagal menghapus batik: ' . $e->getMessage(), ['batik_id' => $id]);
            return response()->json(['message' => 'Gagal menghapus riwayat.'], 500);
        }
    }
}