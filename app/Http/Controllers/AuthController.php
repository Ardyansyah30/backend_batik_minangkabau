<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException; // Impor kelas ini

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|unique:users|max:255',
                'password' => 'required|string|min:8|confirmed',
            ]);
    
            $user = User::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
            ]);
    
            $token = $user->createToken('auth_token')->plainTextToken;
    
            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'message' => 'User registered successfully!',
            ], 201);
        } catch (ValidationException $e) {
            // Tangani kegagalan validasi secara spesifik dan kembalikan JSON
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Tangani error tak terduga lainnya
            // Catatan: Untuk produksi, jangan tampilkan $e->getMessage() secara langsung
            return response()->json([
                'message' => 'Terjadi kesalahan tak terduga.',
                // 'error_debug' => $e->getMessage() // Hapus atau jadikan komentar untuk produksi
            ], 500);
        }
    }

    /**
     * Log in a user.
     */
    public function login(Request $request): JsonResponse
    {
        // Metode login Anda sudah benar karena Anda menangani kegagalan dengan respons JSON.
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Email atau password salah.'
            ], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
            'message' => 'Login berhasil!',
        ]);
    }

    // ... metode logout Anda juga sudah benar
}