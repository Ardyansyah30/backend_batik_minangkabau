<?php

namespace App\Http\Controllers;

use App\Models\Batik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;

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
     * Menyimpan data batik baru (Create)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
                'is_minangkabau_batik' => 'required|string',
                'batik_name' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'origin' => 'nullable|string|max:255',
            ]);

            $isMinangkabauBatik = filter_var($validatedData['is_minangkabau_batik'], FILTER_VALIDATE_BOOLEAN);

            $image = $request->file('image');
            $filename = time() . '_' . $image->getClientOriginalName();
            $path = $image->storeAs('public/batik_images', $filename);

            $batik = Batik::create([
                'filename' => $filename,
                'path' => $path,
                'original_name' => $image->getClientOriginalName(),
                'is_minangkabau_batik' => $isMinangkabauBatik,
                'batik_name' => $validatedData['batik_name'],
                'description' => $validatedData['description'],
                'origin' => $validatedData['origin'],
            ]);

            return response()->json(['message' => 'Batik berhasil disimpan!', 'data' => $batik], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Menampilkan satu data batik (Read - One)
     */
    public function show(Batik $batik): JsonResponse
    {
        return response()->json($batik);
    }
    
    /**
     * Memperbarui data batik (Update)
     */
    public function update(Request $request, Batik $batik): JsonResponse
    {
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
        try {
            Storage::delete($batik->path);
            $batik->delete();
    
            return response()->json(['message' => 'Batik berhasil dihapus.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}