<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Felhasznalo;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'jelszo' => 'required',
        ]);

        $user = Felhasznalo::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->jelszo, $user->password)) {
            return response()->json(['error' => 'Hibás email vagy jelszó'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Sikeres bejelentkezés',
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sikeres kijelentkezés']);
    }

    public function register(Request $request)
    {
        $request->validate([
            'nev' => 'required|string|max:50',
            'email' => 'required|string|email|unique:felhasznalok,email',
            'jelszo' => 'required|string|min:6',
            'telefonszam' => 'nullable|string|max:20',
        ]);

        $user = Felhasznalo::create([
            'nev' => $request->nev,
            'felhasznalonev' => $request->nev, // vagy hagyd üresen: ''
            'email' => $request->email,
            'password' => bcrypt($request->jelszo),
            'telefonszam' => $request->telefonszam ?? '',
            'jogosultsag' => 'ugyfel',
        ]);

        return response()->json([
            'message' => 'Sikeres regisztráció!',
            'user' => $user,
        ], 201);
    }
}
