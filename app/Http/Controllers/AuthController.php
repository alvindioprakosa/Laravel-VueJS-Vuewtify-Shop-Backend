<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if ($user && Hash::check($credentials['password'], $user->password)) {
            $user->generateToken();
            return response()->json([
                'status' => 'success',
                'message' => 'Login sukses',
                'data' => $user,
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Login gagal, email atau password salah',
        ], 401);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 400);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'roles' => json_encode(['CUSTOMER']),
        ]);

        Auth::login($user);
        $user->generateToken();

        return response()->json([
            'status' => 'success',
            'message' => 'Registrasi berhasil',
            'data' => $user,
        ], 201);
    }

    public function logout()
    {
        $user = Auth::user();
        if ($user) {
            $user->api_token = null;
            $user->save();
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'Logout berhasil',
        ], 200);
    }
}
