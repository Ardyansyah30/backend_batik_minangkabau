<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BatikController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Di sini Anda dapat mendaftarkan rute API untuk aplikasi Anda.
|
*/

// Rute untuk mendapatkan data pengguna yang terautentikasi
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('/batiks/store', [BatikController::class, 'store']);
Route::get('/batiks', [BatikController::class, 'index']);
Route::get('/batiks/{batik}', [BatikController::class, 'show']);
Route::put('/batiks/{batik}', [BatikController::class, 'update']);
Route::patch('/batiks/{batik}', [BatikController::class, 'update']);
Route::delete('/batiks/{batik}', [BatikController::class, 'destroy']);
Route::post('/login', [AuthController::class, 'login']);
