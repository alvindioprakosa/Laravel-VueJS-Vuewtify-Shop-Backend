<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    // Login User
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email', 
            'password' => 'required|min:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email atau password salah',
                'data' => null
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login berhasil',
            'data' => [
                'user' => $user->only('id', 'name', 'email', 'roles'), // hanya data penting
                'token' => $token
            ]
        ], 200);
    }

    // Register User
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'roles' => json_encode(['CUSTOMER']),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Registrasi berhasil',
            'data' => [
                'user' => $user->only('id', 'name', 'email', 'roles'),
                'token' => $token
            ]
        ], 201);
    }

    // Logout User
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete(); // hanya logout token aktif
        return response()->json([
            'status' => 'success',
            'message' => 'Logout berhasil',
            'data' => []
        ], 200);
    }
}
