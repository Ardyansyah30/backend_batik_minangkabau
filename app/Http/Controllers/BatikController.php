<?php

namespace App\Http\Controllers;

use App\Models\Batik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; // 👈 Tambahkan ini untuk debugging

class BatikController extends Controller
{
    /**
     * Menampilkan semua data batik. (Read - All)
     */
    public function index(): JsonResponse
    {
        $batiks = Batik::all();
        return response()->json($batiks);
    }

    /**
     * Menampilkan data batik yang diunggah user yang sedang login.
     */
    public function myBatiks(): JsonResponse
    {
        $user = Auth::user();
        $batiks = $user->batiks; // Menggunakan relasi hasMany
        return response()->json($batiks);
    }

    /**
     * Menyimpan data batik baru (Create)
     */
    public function store(Request $request): JsonResponse
    {
        // ➡️ Mulai debugging di sini
        Log::info('Permintaan diterima untuk store batik.');
        Log::info('Data permintaan: ', $request->all());

        try {
            // Cek otentikasi user
            if (!Auth::check()) {
                Log::warning('Percobaan store batik tanpa otentikasi.');
                return response()->json(['error' => 'Anda tidak memiliki izin untuk melakukan tindakan ini.'], 403);
            }
            Log::info('User terotentikasi. ID User: ' . Auth::id());

            // Proses validasi
            $validatedData = $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
                'is_minangkabau_batik' => 'required|string',
                'batik_name' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'origin' => 'nullable|string|max:255',
            ]);
            Log::info('Validasi berhasil.', $validatedData);

            $isMinangkabauBatik = filter_var($validatedData['is_minangkabau_batik'], FILTER_VALIDATE_BOOLEAN);

            // Proses upload file
            $image = $request->file('image');
            $filename = time() . '_' . $image->getClientOriginalName();
            $path = $image->storeAs('public/batik_images', $filename);
            Log::info('File berhasil diunggah. Path: ' . $path);

            // Proses penyimpanan ke database
            $batik = Batik::create([
                'user_id' => Auth::id(),
                'filename' => $filename,
                'path' => $path,
                'original_name' => $image->getClientOriginalName(),
                'is_minangkabau_batik' => $isMinangkabauBatik,
                'batik_name' => $validatedData['batik_name'],
                'description' => $validatedData['description'],
                'origin' => $validatedData['origin'],
            ]);
            Log::info('Data batik berhasil disimpan. Batik ID: ' . $batik->id);

            return response()->json(['message' => 'Batik berhasil disimpan!', 'data' => $batik], 201);
        } catch (\Exception $e) {
            // Menangkap dan mencatat semua jenis error
            Log::error('Error saat menyimpan batik: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Menampilkan satu data batik (Read - One)
     */
        public function show(Batik $batik): JsonResponse
        {
            $batik->load('user', 'comments.user'); // Muat relasi user dan komentar (beserta user yang berkomentar)
                return response()->json($batik);
        }

    
    /**
     * Memperbarui data batik (Update)
     */
    public function update(Request $request, Batik $batik): JsonResponse
    {
        // 👈 Tambahkan otorisasi
        if ($batik->user_id !== Auth::id()) {
            return response()->json(['error' => 'Anda tidak memiliki izin untuk memperbarui batik ini.'], 403);
        }

        try {
            $validatedData = $request->validate([
                'batik_name' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'origin' => 'nullable|string|max:255',
            ]);
    
            $batik->update($validatedData);
    
            return response()->json(['message' => 'Batik berhasil diperbarui.', 'data' => $batik]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Menghapus data batik (Delete)
     */
    public function destroy(Batik $batik): JsonResponse
    {
        // 👈 Tambahkan otorisasi
        if ($batik->user_id !== Auth::id()) {
            return response()->json(['error' => 'Anda tidak memiliki izin untuk menghapus batik ini.'], 403);
        }
        
        try {
            Storage::delete($batik->path);
            $batik->delete();
    
            return response()->json(['message' => 'Batik berhasil dihapus.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}