<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\Felhasznalo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    /**
     * Új Ugyfel regisztrálása E.164 formátumú telefonszámmal.
     */
    public function store(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        Felhasznalo::create([
            'nev' => trim($validated['nev']),
            'felhasznalonev' => trim($validated['felhasznalonev']),
            'email' => trim($validated['email']),
            'jelszo' => Hash::make($validated['jelszo']),
            'telefonszam' => $validated['telefonszam'],
            'jogosultsag' => 'Ugyfel',
        ]);

        return response()->json(['ok' => true], 201, [], JSON_UNESCAPED_UNICODE);
    }
}
