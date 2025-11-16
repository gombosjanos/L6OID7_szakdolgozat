<?php

namespace App\Traits;

use App\Models\Felhasznalo;
use Illuminate\Validation\ValidationException;

trait HandlesPhoneNumbers
{
    protected function validateAndNormalizePhone(string $phone, ?int $ignoreId = null): string
    {
        $normalized = $this->canonicalizePhoneNumber($phone);

        if (!$normalized || !$this->isEuropeanPhoneNumber($normalized)) {
            throw ValidationException::withMessages([
                'telefonszam' => 'Érvényes magyar vagy európai telefonszámot adj meg (pl. +36201234567).',
            ]);
        }

        $query = Felhasznalo::where('telefonszam', $normalized);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'telefonszam' => 'Ez a telefonszám már regisztrálva van.',
            ]);
        }

        return $normalized;
    }

    protected function canonicalizePhoneNumber(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $trimmed = trim($phone);
        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $trimmed);
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0036')) {
            $digits = '36' . substr($digits, 4);
        } elseif (str_starts_with($digits, '06')) {
            $digits = '36' . substr($digits, 2);
        } elseif (preg_match('/^0(20|30|31|50|70|1)/', $digits)) {
            $digits = '36' . substr($digits, 1);
        } elseif (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return $digits !== '' ? '+' . $digits : null;
    }

    protected function isEuropeanPhoneNumber(string $phone): bool
    {
        if (!str_starts_with($phone, '+')) {
            return false;
        }

        $digits = substr($phone, 1);
        $length = strlen($digits);
        if ($length < 8 || $length > 15) {
            return false;
        }

        $first = $digits[0] ?? null;
        return in_array($first, ['3', '4', '5', '7'], true);
    }
}
