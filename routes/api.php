<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BatikController;
use App\Http\Controllers\AuthController; // 👈 Pastikan ini ada
use App\Http\Controllers\CommentController; // Pastikan baris ini ada

/*
|--------------------------------------------------------------------------
| Public API Routes (Rute yang dapat diakses tanpa otentikasi)
|--------------------------------------------------------------------------
*/

// Rute untuk registrasi user baru
Route::post('/register', [AuthController::class, 'register']);

// Rute untuk login user
Route::post('/login', [AuthController::class, 'login']);

// Rute untuk menampilkan semua data batik (biasanya publik agar bisa dilihat semua orang)
Route::get('/batiks', [BatikController::class, 'index']);

// Rute untuk menampilkan satu data batik (juga bisa publik agar bisa dilihat semua orang)
Route::get('/batiks/{batik}', [BatikController::class, 'show']);


/*
|--------------------------------------------------------------------------
| Protected API Routes (Rute yang membutuhkan otentikasi)
|--------------------------------------------------------------------------
| Semua rute di dalam grup ini akan memerlukan token otentikasi yang valid.
*/

Route::middleware('auth:sanctum')->group(function () {
    // Rute untuk mendapatkan data user yang sedang login
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Rute untuk logout user
    Route::post('/logout', [AuthController::class, 'logout']);

    // Rute untuk menyimpan data batik baru (membutuhkan user_id)
    Route::post('/batiks/store', [BatikController::class, 'store']); // Tetap gunakan /batiks/store jika Anda suka

    // Rute untuk menampilkan data batik yang diunggah oleh user yang sedang login
    Route::get('/my-batiks', [BatikController::class, 'myBatiks']);

    // Rute untuk memperbarui data batik
    Route::put('/batiks/{batik}', [BatikController::class, 'update']);
    Route::patch('/batiks/{batik}', [BatikController::class, 'update']); // Tetap sertakan patch jika Anda menggunakannya secara spesifik

    // Rute untuk menghapus data batik
    Route::delete('/batiks/{batik}', [BatikController::class, 'destroy']);

     Route::post('/batik/{batik}/add-comment', [CommentController::class, 'store']);
    Route::delete('/comment/remove/{comment}', [CommentController::class, 'destroy']);

});