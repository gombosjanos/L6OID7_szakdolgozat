<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Felhasznalo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use App\Traits\HandlesPhoneNumbers;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Auth\Events\PasswordReset;

class AuthController extends Controller
{
    use HandlesPhoneNumbers;

    public function login(Request $request)
    {
        $identifierInput = $request->input('azonosito') ?? $request->input('identifier') ?? $request->input('email');
        $request->merge(['azonosito' => $identifierInput]);

        $credentials = $request->validate([
            'azonosito' => 'required|string',
            'jelszo' => 'required|string',
        ], [
            'azonosito.required' => 'Az email vagy felhasználónév megadása kötelező.',
        ]);

        $identifier = trim($credentials['azonosito']);
        $user = $this->findUserByIdentifier($identifier);

        if (!$user || !Hash::check($credentials['jelszo'], $user->password)) {
            return response()->json(['error' => 'Hibás email/felhasználónév vagy jelszó'], 401);
        }

        if ($this->twoFactorEnabled($user)) {
            $code = $request->input('ketfaktor_kod');
            $recovery = $request->input('helyreallito_kod');

            if (!$code && !$recovery) {
                return response()->json([
                    'message' => 'Kétlépcsős azonosítás szükséges. Add meg az alkalmazás által generált kódot vagy egy helyreállító kódot.',
                    'two_factor' => true,
                ], 423, [], JSON_UNESCAPED_UNICODE);
            }

            if ($code) {
                if (!$this->verifyTwoFactorCode($user, $code)) {
                    return response()->json([
                        'message' => 'Érvénytelen kétlépcsős kód.',
                    ], 422, [], JSON_UNESCAPED_UNICODE);
                }
            } else {
                if (!$this->consumeRecoveryCode($user, $recovery)) {
                    return response()->json([
                        'message' => 'A megadott helyreállító kód érvénytelen vagy már felhasználtad.',
                    ], 422, [], JSON_UNESCAPED_UNICODE);
                }
                $user->refresh();
            }
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Sikeres bejelentkezés',
            'token' => $token,
            'user' => $user->fresh(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sikeres kijelentkezés']);
    }

    public function register(Request $request)
    {
        $messages = [
            'felhasznalonev.required' => 'A felhasználónév megadása kötelező.',
            'felhasznalonev.unique' => 'Ez a felhasználónév már foglalt.',
            'felhasznalonev.min' => 'A felhasználónév legalább 4 karakter legyen.',
            'felhasznalonev.regex' => 'A felhasználónév csak betűt, számot, pontot, aláhúzást vagy kötőjelet tartalmazhat.',
        ];

        $validated = $request->validate([
            'nev' => 'required|string|min:2|max:50',
            'felhasznalonev' => 'required|string|min:4|max:50|regex:/^[A-Za-z0-9._-]+$/|unique:felhasznalok,felhasznalonev',
            'email' => 'required|string|email|max:100|unique:felhasznalok,email',
            'jelszo' => ['required','string','min:8','regex:/^(?=.*[A-Za-z])(?=.*\d).{8,}$/','confirmed'],
            'telefonszam' => ['required','string','max:32'],
        ], $messages);

        $phone = $this->validateAndNormalizePhone($validated['telefonszam']);
        $username = trim($validated['felhasznalonev']);
        $name = trim($validated['nev']);
        $email = trim($validated['email']);

        $user = Felhasznalo::create([
            'nev' => $name,
            'felhasznalonev' => $username,
            'email' => $email,
            'jelszo' => bcrypt($validated['jelszo']),
            'telefonszam' => $phone,
            'jogosultsag' => 'Ugyfel',
        ]);

        return response()->json([
            'message' => 'Sikeres regisztráció!',
            'user' => $user->fresh(),
        ], 201, [], JSON_UNESCAPED_UNICODE);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $messages = [
            'felhasznalonev.required' => 'A felhasználónév megadása kötelező.',
            'felhasznalonev.unique' => 'Ez a felhasználónév már foglalt.',
            'felhasznalonev.min' => 'A felhasználónév legalább 4 karakter legyen.',
            'felhasznalonev.regex' => 'A felhasználónév csak betűt, számot, pontot, aláhúzást vagy kötőjelet tartalmazhat.',
            'current_password.required' => 'Add meg a jelenlegi jelszavad a módosításhoz.',
        ];

        $validated = $request->validate([
            'nev' => 'required|string|min:2|max:50',
            'felhasznalonev' => [
                'required',
                'string',
                'min:4',
                'max:50',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('felhasznalok', 'felhasznalonev')->ignore($user->getKey(), $user->getKeyName()),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:100',
                Rule::unique('felhasznalok', 'email')->ignore($user->getKey(), $user->getKeyName()),
            ],
            'telefonszam' => ['required','string','max:32'],
            'password' => ['nullable','string','min:8','regex:/^(?=.*[A-Za-z])(?=.*\d).{8,}$/','confirmed'],
            'current_password' => ['required','string'],
        ], $messages);

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'A megadott jelenlegi jelszó nem megfelelő.',
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        unset($validated['current_password']);

        $validated['telefonszam'] = $this->validateAndNormalizePhone($validated['telefonszam'], $user->getKey());
        $validated['felhasznalonev'] = trim($validated['felhasznalonev']);
        $validated['nev'] = trim($validated['nev']);
        $validated['email'] = trim($validated['email']);

        if (!empty($validated['password'])) {
            $user->jelszo = bcrypt($validated['password']);
        }
        unset($validated['password']);

        $user->fill($validated);
        $user->save();

        return response()->json($user->fresh(), 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function twoFactorStatus(Request $request)
    {
        $user = $request->user()->fresh();
        $recoveryList = $this->recoveryCodes($user);

        return response()->json([
            'enabled' => $this->twoFactorEnabled($user),
            'pending' => $this->twoFactorPending($user),
            'recovery_ossz' => count($recoveryList),
            'recovery_hatralevo' => $this->twoFactorEnabled($user) ? count($recoveryList) : 0,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function startTwoFactorSetup(Request $request)
    {
        $user = $request->user();

        $secret = $this->generateSecret();
        $recoveryCodes = $this->generateRecoveryCodes();
        $hashedRecovery = array_map(fn ($code) => Hash::make($this->normalizeRecoveryCode($code)), $recoveryCodes);

        $user->ketfaktor_secret = encrypt($secret);
        $user->ketfaktor_enabled_at = null;
        $user->ketfaktor_recovery_kodok = json_encode(array_values($hashedRecovery));
        $user->save();

        $issuer = urlencode(config('app.name', 'Kertigép Szerviz'));
        $label = urlencode($user->email);
        $otpAuth = "otpauth://totp/{$issuer}:{$label}?secret={$secret}&issuer={$issuer}&period=30&digits=6";

        return response()->json([
            'secret' => $secret,
            'otpauth_url' => $otpAuth,
            'recovery_codes' => $recoveryCodes,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function confirmTwoFactor(Request $request)
    {
        $request->validate([
            'kod' => 'required|string',
        ], [], [
            'kod' => 'kétlépcsős kód',
        ]);

        $user = $request->user();

        if (!$user->ketfaktor_secret) {
            return response()->json([
                'message' => 'Nincs folyamatban lévő kétlépcsős beállítás.',
            ], 409, [], JSON_UNESCAPED_UNICODE);
        }

        if (!$this->verifyTwoFactorCode($user, $request->input('kod'))) {
            return response()->json([
                'message' => 'Érvénytelen kód.',
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        $user->ketfaktor_enabled_at = now();
        $user->save();

        return response()->json([
            'message' => 'Kétlépcsős azonosítás aktiválva.',
            'enabled' => true,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function disableTwoFactor(Request $request)
    {
        $user = $request->user();

        if (!$this->twoFactorEnabled($user) && !$this->twoFactorPending($user)) {
            return response()->json([
                'message' => 'A kétlépcsős azonosítás már inaktív.',
                'enabled' => false,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $code = $request->input('kod');
        $recovery = $request->input('helyreallito_kod');

        if ($this->twoFactorEnabled($user)) {
            if (!$code && !$recovery) {
                return response()->json([
                    'message' => 'Add meg a kódot vagy egy helyreállító kódot a kikapcsoláshoz.',
                ], 422, [], JSON_UNESCAPED_UNICODE);
            }
            if ($code && !$this->verifyTwoFactorCode($user, $code)) {
                return response()->json([
                    'message' => 'Érvénytelen kód.',
                ], 422, [], JSON_UNESCAPED_UNICODE);
            }
            if (!$code && !$this->consumeRecoveryCode($user, $recovery)) {
                return response()->json([
                    'message' => 'A megadott helyreállító kód érvénytelen vagy már felhasználtad.',
                ], 422, [], JSON_UNESCAPED_UNICODE);
            }
        }

        $user->ketfaktor_secret = null;
        $user->ketfaktor_enabled_at = null;
        $user->ketfaktor_recovery_kodok = null;
        $user->save();

        return response()->json([
            'message' => 'Kétlépcsős azonosítás kikapcsolva.',
            'enabled' => false,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $user = $request->user();

        if (!$this->twoFactorEnabled($user)) {
            return response()->json([
                'message' => 'Előbb aktiváld a kétlépcsős azonosítást.',
            ], 409, [], JSON_UNESCAPED_UNICODE);
        }

        $code = $request->input('kod');
        $recoveryInput = $request->input('helyreallito_kod');

        if ($code) {
            if (!$this->verifyTwoFactorCode($user, $code)) {
                return response()->json([
                    'message' => 'Érvénytelen kód.',
                ], 422, [], JSON_UNESCAPED_UNICODE);
            }
        } elseif (!$this->consumeRecoveryCode($user, $recoveryInput)) {
            return response()->json([
                'message' => 'A megadott helyreállító kód érvénytelen vagy már felhasználtad.',
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        $newCodes = $this->generateRecoveryCodes();
        $user->ketfaktor_recovery_kodok = json_encode(array_map(fn ($code) => Hash::make($this->normalizeRecoveryCode($code)), $newCodes));
        $user->save();

        return response()->json([
            'message' => 'Új helyreállító kódok generálva. Őrizd meg őket biztonságos helyen!',
            'recovery_codes' => $newCodes,
        ], 200, [], JSON_UNESCAPED_UNICODE);
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

        private function findUserByIdentifier(string $identifier): ?Felhasznalo
        {
            $identifier = trim($identifier);
            if ($identifier === '') {
                return null;
            }

            if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                return Felhasznalo::where('email', $identifier)->first();
            }

            return Felhasznalo::where('felhasznalonev', $identifier)->first();
        }

        private function twoFactorEnabled(Felhasznalo $user): bool
        {
            return (bool) ($user->ketfaktor_secret && $user->ketfaktor_enabled_at);
        }

        private function twoFactorPending(Felhasznalo $user): bool
        {
            return (bool) ($user->ketfaktor_secret && !$user->ketfaktor_enabled_at);
        }

        private function verifyTwoFactorCode(Felhasznalo $user, ?string $code): bool
        {
            if (!$code || !$user->ketfaktor_secret) {
                return false;
            }

            $clean = preg_replace('/\D+/', '', $code);
            if (strlen($clean) < 6 || strlen($clean) > 8) {
                return false;
            }

            try {
                $secret = decrypt($user->ketfaktor_secret);
            } catch (\Exception $e) {
                return false;
            }

            $counter = floor(time() / 30);
            for ($offset = -1; $offset <= 1; $offset++) {
                if ($this->timeBasedOtp($secret, $counter + $offset) === str_pad($clean, 6, '0', STR_PAD_LEFT)) {
                    return true;
                }
            }

            return false;
        }

        private function consumeRecoveryCode(Felhasznalo $user, ?string $code): bool
        {
            if (!$code || !$user->ketfaktor_recovery_kodok) {
                return false;
            }

            $normalized = $this->normalizeRecoveryCode($code);
            $list = $this->recoveryCodes($user);
            $remaining = [];
            $matched = false;

            foreach ($list as $stored) {
                if ($matched) {
                    // Már találtunk egyezést, a maradékot változtatás nélkül átvesszük
                    $remaining[] = $stored;
                    continue;
                }

                // 1) Normál eset: a kód hash-elve van tárolva (Hash::make)
                if (is_string($stored) && Hash::check($normalized, $stored)) {
                    $matched = true;
                    continue;
                }

                // 2) Visszafelé kompatibilitás: ha régi, sima szöveges kódok vannak eltárolva
                if (is_string($stored)) {
                    $legacyNormalized = $this->normalizeRecoveryCode($stored);
                    if ($legacyNormalized !== '' && hash_equals($normalized, $legacyNormalized)) {
                        $matched = true;
                        continue;
                    }
                }

                $remaining[] = $stored;
            }

            if ($matched) {
                $user->ketfaktor_recovery_kodok = $remaining ? json_encode(array_values($remaining)) : null;
                $user->save();
            }

            return $matched;
        }

        private function recoveryCodes(Felhasznalo $user): array
        {
            if (!$user->ketfaktor_recovery_kodok) {
                return [];
            }

            $decoded = json_decode($user->ketfaktor_recovery_kodok, true);
            return is_array($decoded) ? $decoded : [];
        }

        private function normalizeRecoveryCode(string $code): string
        {
            return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code));
        }

        private function generateSecret(int $bytes = 20): string
        {
            return $this->base32Encode(random_bytes($bytes));
        }

        private function generateRecoveryCodes(int $count = 8): array
        {
            $codes = [];
            for ($i = 0; $i < $count; $i++) {
                $raw = strtoupper(Str::random(10));
                $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
            }
            return $codes;
        }

        private function timeBasedOtp(string $secret, int $counter): string
        {
            $key = $this->base32Decode($secret);
            if ($key === '') {
                return '';
            }

            $counterBytes = pack('N*', 0) . pack('N*', $counter);
            $hash = hash_hmac('sha1', $counterBytes, $key, true);
            $offset = ord(substr($hash, -1)) & 0x0F;
            $binary = ((ord($hash[$offset]) & 0x7f) << 24) |
                ((ord($hash[$offset + 1]) & 0xff) << 16) |
                ((ord($hash[$offset + 2]) & 0xff) << 8) |
                (ord($hash[$offset + 3]) & 0xff);

            $otp = $binary % 1000000;

            return str_pad((string) $otp, 6, '0', STR_PAD_LEFT);
        }

        private function base32Encode(string $bytes): string
        {
            $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
            $binary = '';
            foreach (str_split($bytes) as $char) {
                $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
            }

            $chunks = str_split($binary, 5);
            $encoded = '';
            foreach ($chunks as $chunk) {
                if (strlen($chunk) < 5) {
                    $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
                }
                $encoded .= $alphabet[bindec($chunk)];
            }

            return $encoded;
        }

        private function base32Decode(string $secret): string
        {
            $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
            $secret = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret));
            $binary = '';

            foreach (str_split($secret) as $char) {
                $position = strpos($alphabet, $char);
                if ($position === false) {
                    return '';
                }
                $binary .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
            }

            $bytes = '';
            foreach (str_split($binary, 8) as $chunk) {
                if (strlen($chunk) === 8) {
                    $bytes .= chr(bindec($chunk));
                }
            }

            return $bytes;
        }
}
