<?php

namespace Tests\Feature;

use App\Models\Felhasznalo;
use App\Models\Gep;
use App\Models\Munkalap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase {
        migrateFreshUsing as baseMigrateFreshUsing;
    }

    protected function createUser(array $attributes = []): Felhasznalo
    {
        $passwordInput = $attributes['password'] ?? 'secret';
        $hashedPassword = str_starts_with($passwordInput, '$2y$')
            ? $passwordInput
            : Hash::make($passwordInput);

        $payload = array_merge([
            'nev' => $attributes['nev'] ?? 'Teszt Felhasznalo',
            'felhasznalonev' => $attributes['felhasznalonev'] ?? Str::random(8),
            'email' => $attributes['email'] ?? Str::random(8) . '@example.test',
            'telefonszam' => $attributes['telefonszam'] ?? $this->uniquePhone(),
            'jogosultsag' => $attributes['jogosultsag'] ?? 'Ugyfel',
            'ketfaktor_secret' => $attributes['ketfaktor_secret'] ?? null,
            'ketfaktor_enabled_at' => $attributes['ketfaktor_enabled_at'] ?? null,
            'ketfaktor_recovery_kodok' => $attributes['ketfaktor_recovery_kodok'] ?? null,
        ], $attributes);

        $payload['jelszo'] = $hashedPassword;
        $payload['password'] = $hashedPassword;

        return Felhasznalo::create($payload);
    }

    protected function createGep(array $attributes = []): Gep
    {
        return Gep::create(array_merge([
            'gyarto' => 'AL-KO',
            'tipusnev' => 'Teszt Gep',
            'g_cikkszam' => 'C-' . Str::upper(Str::random(5)),
            'gyartasiev' => 2024,
        ], $attributes));
    }

    protected function createMunkalap(array $attributes = []): Munkalap
    {
        $now = Carbon::now();
        $userId = $attributes['user_id'] ?? ($attributes['letrehozta'] ?? $this->createUser()->id);
        $gepId = $attributes['gep_id'] ?? $this->createGep()->ID;
        $seq = Munkalap::whereYear('letrehozva', $now->year)->count() + 1;

        return Munkalap::create(array_merge([
            'user_id' => $userId,
            'gep_id' => $gepId,
            'hibaleiras' => $attributes['hibaleiras'] ?? 'Hiba leiras',
            'megjegyzes' => $attributes['megjegyzes'] ?? '',
            'statusz' => $attributes['statusz'] ?? 'uj',
            'letrehozva' => $attributes['letrehozva'] ?? $now,
            'munkalapsorsz' => $attributes['munkalapsorsz'] ?? ($now->year . '-' . $seq),
            'letrehozta' => $attributes['letrehozta'] ?? $userId,
        ], $attributes));
    }

    protected function migrateFreshUsing(): array
    {
        return array_merge($this->baseMigrateFreshUsing(), ['--force' => true]);
    }

    private function uniquePhone(): string
    {
        // Generate a Hungarian-style test phone that stays unique across test runs.
        return '+36' . random_int(200000000, 299999999);
    }
}
