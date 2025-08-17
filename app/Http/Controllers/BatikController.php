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
     * Menampilkan semua data batik (biasanya publik).
     */
    public function index(): JsonResponse
    {
        try {
            $batiks = Batik::all();
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
     * Menampilkan data batik yang diunggah user yang sedang login.
     */
    public function myBatiks(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $batiks = $user->batiks()->get();
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
            // --- Cek file upload dengan cara lebih robust ---
            $file = $request->file('image');
            if ($file && $file->isValid()) {
                Log::debug('File image ditemukan dan valid di request.', ['file' => $file]);
                $validator = Validator::make($request->all(), [
                    'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240',
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
                $filename = time() . '_' . $file->getClientOriginalName();
                Log::debug('Nama file yang akan disimpan', ['filename' => $filename]);
                $disk = Storage::disk('public');
                $directory = 'batik_images';
                if (!$disk->exists($directory)) {
                    $disk->makeDirectory($directory);
                    Log::info("Direktori '{$directory}' telah dibuat.");
                }
                if ($disk->exists($directory . '/' . $filename)) {
                    $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                    Log::info('Nama file diubah untuk menghindari duplikasi: ' . $filename);
                }
                Log::debug('Menyimpan file ke storage', ['path' => $directory . '/' . $filename]);
                $path = $file->storeAs($directory, $filename, 'public');
                Log::debug('Hasil storeAs', ['path' => $path, 'exists' => $disk->exists($path)]);
                if (!$path) {
                    Log::error('Operasi storeAs gagal.', ['filename' => $filename]);
                    return response()->json(['error' => 'Gagal menyimpan file gambar.'], 500);
                }
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
                    'original_name' => $file->getClientOriginalName(),
                    'is_minangkabau_batik' => $isMinangkabauBatik,
                    'batik_name' => $batikName,
                    'description' => $description,
                    'origin' => $request->input('origin'),
                ]);
                Log::info('Data batik berhasil disimpan. Batik ID: ' . $batik->id);
                return response()->json(['message' => 'Batik berhasil disimpan!', 'data' => $batik], 201);
            }
            // Jika tidak ada file, cek apakah ada base64
            elseif ($request->filled('image_base64')) {
                Log::debug('Field image_base64 ditemukan di request.');
                $validator = Validator::make($request->all(), [
                    'image_base64' => 'required|string',
                    'is_minangkabau_batik' => 'required|in:true,false',
                    'batik_name' => 'required_if:is_minangkabau_batik,false|nullable|string|max:255',
                    'description' => 'required_if:is_minangkabau_batik,false|nullable|string',
                    'origin' => 'nullable|string|max:255',
                ]);
                if ($validator->fails()) {
                    Log::error('Validasi gagal (base64).', ['errors' => $validator->errors()]);
                    return response()->json(['errors' => $validator->errors()], 422);
                }
                if (!Auth::check()) {
                    Log::warning('Percobaan store batik tanpa otentikasi.');
                    return response()->json(['error' => 'Anda tidak memiliki izin untuk melakukan tindakan ini.'], 403);
                }
                Log::info('User terotentikasi. ID User: ' . Auth::id());
                $isMinangkabauBatik = $request->input('is_minangkabau_batik') === 'true';
                $imageData = $request->input('image_base64');
                // Hilangkan prefix jika ada
                if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
                    $imageData = substr($imageData, strpos($imageData, ',') + 1);
                    $extension = $type[1];
                } else {
                    $extension = 'jpg';
                }
                $imageData = str_replace(' ', '+', $imageData);
                $filename = time() . '_' . uniqid() . '.' . $extension;
                $directory = 'batik_images';
                $disk = Storage::disk('public');
                if (!$disk->exists($directory)) {
                    $disk->makeDirectory($directory);
                }
                $path = $directory . '/' . $filename;
                Storage::disk('public')->put($path, base64_decode($imageData));
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
                    'original_name' => $filename,
                    'is_minangkabau_batik' => $isMinangkabauBatik,
                    'batik_name' => $batikName,
                    'description' => $description,
                    'origin' => $request->input('origin'),
                ]);
                Log::info('Data batik berhasil disimpan (base64). Batik ID: ' . $batik->id);
                return response()->json(['message' => 'Batik berhasil disimpan!', 'data' => $batik], 201);
            }
            // Jika tidak ada file maupun base64
            else {
                Log::error('File gambar tidak ditemukan dalam permintaan. Debug: hasFile(image)='.($request->hasFile('image')?'true':'false').', file='.($file?'ada':'null').', isValid='.($file&&method_exists($file,'isValid')?$file->isValid():'-'));
                return response()->json(['error' => 'File gambar tidak ditemukan dalam permintaan.'], 422);
            }
        } catch (Exception $e) {
            Log::error('Error saat menyimpan batik: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Terjadi kesalahan server.'], 500);
        }
    }
    
    public function show(Batik $batik): JsonResponse
    {
        return response()->json(['data' => $batik]);
    }
    
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
    
        } catch (Exception $e) {
            Log::error('Gagal menghapus batik: ' . $e->getMessage(), ['batik_id' => $id]);
            return response()->json(['message' => 'Gagal menghapus riwayat.'], 500);
        }
    }
    
    public function update(Request $request, Batik $batik): JsonResponse
    {
        try {
            Log::info('Permintaan update batik diterima.', ['batik_id' => $batik->id]);

            if ($batik->user_id !== Auth::id()) {
                Log::warning('Percobaan update batik tanpa otentikasi.', ['batik_id' => $batik->id, 'user_id' => Auth::id()]);
                return response()->json(['message' => 'Anda tidak memiliki izin untuk mengedit batik ini.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'is_minangkabau_batik' => 'sometimes|in:true,false',
                'batik_name' => 'required_if:is_minangkabau_batik,false|nullable|string|max:255',
                'description' => 'required_if:is_minangkabau_batik,false|nullable|string',
                'origin' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                Log::error('Validasi gagal.', ['errors' => $validator->errors()]);
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $batik->update($request->all());

            Log::info('Batik berhasil diperbarui.', ['batik_id' => $batik->id]);

            return response()->json(['message' => 'Batik berhasil diperbarui.', 'data' => $batik], 200);

        } catch (Exception $e) {
            Log::error('Gagal memperbarui batik: ' . $e->getMessage(), ['batik_id' => $batik->id]);
            return response()->json(['message' => 'Gagal memperbarui batik.'], 500);
        }
    }
}