<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Propaganistas\LaravelPhone\Rules\Phone;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class RegisterRequest extends FormRequest
{
    /**
     * Csak publikus regisztrációhoz engedjük a kérést.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Regisztrációs szabályok (telefonszám E.164 mobil formátum, megadott országok).
     */
    public function rules(): array
    {
        return [
            'nev' => ['required', 'string', 'min:2', 'max:255'],
            'felhasznalonev' => [
                'required',
                'string',
                'min:4',
                'max:50',
                'regex:/^[A-Za-z0-9._-]+$/',
                'unique:felhasznalok,felhasznalonev',
            ],
            'email' => ['required', 'email', 'max:255', 'unique:felhasznalok,email'],
            'jelszo' => ['required', 'string', 'min:8', 'regex:/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', 'confirmed'],
            // Alternatív reguláris kifejezés: regex:/^\+[1-9]\d{6,14}$/
            'telefonszam' => [
                'required',
                (new Phone())
                    ->country(['HU', 'SK', 'RO', 'CZ', 'AT', 'DE'])
                    ->type('mobile')
                    ->lenient(),
                'unique:felhasznalok,telefonszam',
            ],
        ];
    }

    /**
     * Magyar hibaüzenetek (E.164 követelmény kihangsúlyozása).
     */
    public function messages(): array
    {
        return [
            'nev.required' => 'A név megadása kötelező.',
            'nev.string' => 'A név szöveges mező kell legyen.',
            'nev.min' => 'A név legalább 2 karakter legyen.',
            'nev.max' => 'A név legfeljebb 255 karakter lehet.',
            'felhasznalonev.required' => 'A felhasználónév megadása kötelező.',
            'felhasznalonev.string' => 'A felhasználónév szöveges mező kell legyen.',
            'felhasznalonev.min' => 'A felhasználónév legalább 4 karakter legyen.',
            'felhasznalonev.max' => 'A felhasználónév legfeljebb 50 karakter lehet.',
            'felhasznalonev.regex' => 'A felhasználónév csak betűt, számot, pontot, aláhúzást vagy kötőjelet tartalmazhat.',
            'felhasznalonev.unique' => 'Ez a felhasználónév már foglalt.',
            'email.required' => 'Az e-mail cím megadása kötelező.',
            'email.email' => 'Adj meg érvényes e-mail címet.',
            'email.max' => 'Az e-mail cím legfeljebb 255 karakter lehet.',
            'email.unique' => 'Ez az e-mail cím már regisztrálva van.',
            'jelszo.required' => 'A jelszó megadása kötelező.',
            'jelszo.string' => 'A jelszó szöveges mező kell legyen.',
            'jelszo.min' => 'A jelszó legalább 8 karakter legyen.',
            'jelszo.regex' => 'A jelszónak tartalmaznia kell legalább egy betűt és egy számot.',
            'jelszo.confirmed' => 'A megadott jelszavak nem egyeznek.',
            'telefonszam.required' => 'A telefonszám megadása kötelező.',
            'telefonszam.phone' => 'Adj meg érvényes mobil számot a megadott országokból E.164 formátumban (pl. +36301234567).',
            'telefonszam.phone_number' => 'Adj meg érvényes mobil számot a megadott országokból E.164 formátumban (pl. +36301234567).',
            'telefonszam.unique' => 'Ez a telefonszám már regisztrálva van.',
        ];
    }

    /**
     * Telefonszám normalizálása validálás előtt (szóköz, kötőjel, zárójel eltávolítása, 00 -> +).
     */
    protected function prepareForValidation(): void
    {
        $rawName = $this->input('nev');
        $rawUsername = $this->input('felhasznalonev');
        $rawEmail = $this->input('email');
        $raw = $this->input('telefonszam');
        if ($raw === null) {
            $cleanPhone = null;
        } else {
            $cleansed = preg_replace('/[\s\-\(\)]/', '', (string) $raw);
            if ($cleansed && str_starts_with($cleansed, '00')) {
                $cleansed = '+' . substr($cleansed, 2);
            }
            $cleanPhone = $cleansed;
        }

        $this->merge([
            'nev' => is_string($rawName) ? trim($rawName) : $rawName,
            'felhasznalonev' => is_string($rawUsername) ? trim($rawUsername) : $rawUsername,
            'email' => is_string($rawEmail) ? trim($rawEmail) : $rawEmail,
            'telefonszam' => $cleanPhone,
        ]);
    }

    /**
     * Telefonszám végső E.164 formázása a libphonenumber segítségével.
     */
    protected function passedValidation(): void
    {
        $value = $this->telefonszam;
        if (!$value) {
            return;
        }

        try {
            $util = PhoneNumberUtil::getInstance();
            $proto = $util->parse($value, null);
            $e164 = $util->format($proto, PhoneNumberFormat::E164);

            $this->merge([
                'telefonszam' => $e164,
            ]);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'telefonszam' => 'Adj meg érvényes mobil számot a megadott országokból E.164 formátumban (pl. +36301234567).',
            ]);
        }
    }
}
