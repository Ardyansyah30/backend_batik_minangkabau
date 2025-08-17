<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BatikController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;

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

// Rute lupa password
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail']);
Route::post('/reset-password', [ResetPasswordController::class, 'reset']);


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

    // Rute untuk menyimpan data batik baru, termasuk dari halaman kontribusi
    Route::post('/batiks/store', [BatikController::class, 'store']);

    // Rute untuk menampilkan data batik yang diunggah oleh user yang sedang login
    Route::get('/histories', [BatikController::class, 'myBatiks']);

    // Rute untuk memperbarui data batik
    Route::put('/batiks/{batik}', [BatikController::class, 'update']);
    Route::patch('/batiks/{batik}', [BatikController::class, 'update']);

    // Rute untuk menghapus data batik individual
    Route::delete('/batiks/{id}', [BatikController::class, 'destroy']); 

    // BARU: Rute untuk menghapus SEMUA riwayat batik yang diunggah oleh user yang sedang login
    Route::delete('/histories/clear-all', [BatikController::class, 'deleteAllBatiks']);

    // Rute untuk menambahkan komentar pada batik tertentu
    Route::post('/batiks/{batik}/comments', [CommentController::class, 'store']);

    // Rute untuk menghapus komentar
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);
});