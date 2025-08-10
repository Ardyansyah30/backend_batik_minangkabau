<?php

namespace App\Http\Controllers;

use App\Models\Batik;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    public function store(Request $request, Batik $batik): JsonResponse
    {
        $validatedData = $request->validate(['content' => 'required|string|max:1000']);
        
        $comment = $batik->comments()->create([
            'user_id' => Auth::id(),
            'content' => $validatedData['content'],
        ]);
        
        $comment->load('user');
        return response()->json(['message' => 'Komentar berhasil ditambahkan.', 'data' => $comment], 201);
    }

    public function destroy(Comment $comment): JsonResponse
    {
        if ($comment->user_id !== Auth::id()) {
            return response()->json(['error' => 'Anda tidak memiliki izin untuk menghapus komentar ini.'], 403);
        }

        $comment->delete();
        return response()->json(['message' => 'Komentar berhasil dihapus.']);
    }
}