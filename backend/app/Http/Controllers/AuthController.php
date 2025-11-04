<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Felhasznalo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Auth\Events\PasswordReset;

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
            return response()->json(['error' => 'HibĂˇs email vagy jelszĂł'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Sikeres bejelentkezĂ©s',
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sikeres kijelentkezĂ©s']);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'nev' => 'required|string|min:2|max:50',
            'email' => 'required|string|email|max:100|unique:felhasznalok,email',
            'jelszo' => ['required','string','min:8','regex:/^(?=.*[A-Za-z])(?=.*\d).{8,}$/','confirmed'],
            'telefonszam' => ['nullable','string','regex:/^\+?[0-9\s-]{6,20}$/'],
        ]);

        $user = Felhasznalo::create([
            'nev' => $validated['nev'],
            'felhasznalonev' => $validated['nev'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['jelszo']),
            'telefonszam' => $validated['telefonszam'] ?? null,
            'jogosultsag' => 'ugyfel',
        ]);

        return response()->json([
            'message' => 'Sikeres regisztráció!',
            'user' => $user->fresh(),
        ], 201, [], JSON_UNESCAPED_UNICODE);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'nev' => 'required|string|min:2|max:50',
            'email' => [
                'required',
                'string',
                'email',
                'max:100',
                Rule::unique('felhasznalok', 'email')->ignore($user->getKey(), $user->getKeyName()),
            ],
            'telefonszam' => ['nullable','string','regex:/^\+?[0-9\s-]{6,20}$/'],
            'password' => ['nullable','string','min:8','regex:/^(?=.*[A-Za-z])(?=.*\d).{8,}$/','confirmed'],
        ]);

        if (!empty($validated['password'])) {
            $user->password = bcrypt($validated['password']);
        }
        unset($validated['password']);

        $user->fill($validated);
        $user->save();

        return response()->json($user->fresh(), 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function forgotPassword(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::broker()->sendResetLink($credentials);

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => 'Túl sok próbálkozás történt. Kérjük, próbáld újra néhány perc múlva.',
            ], 429, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            'message' => 'Ha a megadott email cím szerepel a rendszerben, elküldtük a jelszó-helyreállító levelet.',
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required','string','min:8','regex:/^(?=.*[A-Za-z])(?=.*\d).{8,}$/','confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                if (method_exists($user, 'tokens')) {
                    $user->tokens()->delete();
                }
                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'A jelszó sikeresen frissült. Most már bejelentkezhetsz az új jelszóval.',
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $httpStatus = $status === Password::INVALID_TOKEN ? 400 : 404;

        return response()->json([
            'message' => 'A jelszó visszaállítása nem sikerült. Kérjük, ellenőrizd a link érvényességét, vagy kérj új helyreállító emailt.',
        ], $httpStatus, [], JSON_UNESCAPED_UNICODE);
    }
}
