<?php

namespace Tests\Feature;

use App\Models\Munkalap;
use App\Notifications\OfferUpdated;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

class AjanlatTest extends FeatureTestCase
{
    public function test_f6_upserting_quote_creates_items_and_totals(): void
    {
        Carbon::setTestNow('2025-01-04 08:00:00');

        $admin = $this->createUser(['jogosultsag' => 'admin']);
        $customer = $this->createUser(['jogosultsag' => 'Ugyfel']);
        $gep = $this->createGep();
        $workorder = $this->createMunkalap([
            'user_id' => $customer->id,
            'gep_id' => $gep->ID,
            'letrehozta' => $admin->id,
        ]);

        $partId = DB::table('alkatreszek')->insertGetId([
            'alkatresznev' => 'Teszt csavar',
            'a_cikkszam' => 'C-100',
            'nettoar' => 100,
            'bruttoar' => 127,
            'keszlet' => 10,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/munkalapok/' . $workorder->ID . '/ajanlat', [
            'megjegyzes' => 'Elso ajanlat',
            'tetelek' => [
                [
                    'tipus' => 'munkadij',
                    'megnevezes' => 'Munkaora',
                    'mennyiseg' => 2,
                    'netto_egyseg_ar' => 1000,
                    'afa_kulcs' => 27,
                ],
                [
                    'tipus' => 'alkatresz',
                    'alkatresz_id' => $partId,
                    'megnevezes' => 'Csavar',
                    'mennyiseg' => 3,
                    'brutto_egyseg_ar' => 500,
                    'afa_kulcs' => 27,
                ],
            ],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('munkalap_ajanlat', [
            'munkalap_id' => $workorder->ID,
            'megjegyzes' => 'Elso ajanlat',
        ]);
        $this->assertDatabaseCount('munkalap_ajanlat_tetelek', 2);

        $ajanlat = DB::table('munkalap_ajanlat')->where('munkalap_id', $workorder->ID)->first();
        $this->assertNotNull($ajanlat);
        $this->assertEqualsWithDelta(4040.0, (float) $ajanlat->osszeg_brutto, 0.01);

        $items = DB::table('munkalap_ajanlat_tetelek')->where('ajanlat_id', $ajanlat->id ?? $ajanlat->ID ?? null)->get();
        $this->assertCount(2, $items);
    }

    public function test_f7_customer_accepts_quote_and_status_is_updated_and_logged(): void
    {
        Carbon::setTestNow('2025-01-04 12:00:00');

        $admin = $this->createUser(['jogosultsag' => 'admin']);
        $customer = $this->createUser(['jogosultsag' => 'Ugyfel']);
        $gep = $this->createGep();
        $workorder = $this->createMunkalap([
            'user_id' => $customer->id,
            'gep_id' => $gep->ID,
            'letrehozta' => $admin->id,
            'statusz' => 'uj',
        ]);

        Sanctum::actingAs($admin);
        $this->postJson('/api/munkalapok/' . $workorder->ID . '/ajanlat', [
            'megjegyzes' => 'Teszt ajanlat',
            'tetelek' => [
                [
                    'tipus' => 'munkadij',
                    'megnevezes' => 'Munkaora',
                    'mennyiseg' => 1,
                    'netto_egyseg_ar' => 1500,
                    'afa_kulcs' => 27,
                ],
            ],
        ])->assertStatus(200);

        Sanctum::actingAs($customer);
        $response = $this->postJson('/api/munkalapok/' . $workorder->ID . '/ajanlat/accept');

        $response->assertStatus(200)
            ->assertJsonFragment(['statusz' => 'elfogadva']);

        $this->assertDatabaseHas('munkalap_ajanlat', [
            'munkalap_id' => $workorder->ID,
            'statusz' => 'elfogadva',
        ]);

        $this->assertDatabaseHas('munkalapok', [
            'ID' => $workorder->ID,
            'statusz' => 'ajanlat_elfogadva',
        ]);

        $this->assertDatabaseHas('munkalap_naplo', [
            'munkalap_id' => $workorder->ID,
        ]);
    }

    public function test_f10_email_notification_on_quote_creation_is_documented(): void
    {
        Notification::fake();
        Carbon::setTestNow('2025-01-05 09:00:00');

        $admin = $this->createUser(['jogosultsag' => 'admin']);
        $customer = $this->createUser(['jogosultsag' => 'Ugyfel']);
        $gep = $this->createGep();
        $workorder = $this->createMunkalap([
            'user_id' => $customer->id,
            'gep_id' => $gep->ID,
            'letrehozta' => $admin->id,
        ]);

        Sanctum::actingAs($admin);
        $this->postJson('/api/munkalapok/' . $workorder->ID . '/ajanlat', [
            'megjegyzes' => 'Email teszt ajanlat',
            'tetelek' => [
                [
                    'tipus' => 'munkadij',
                    'megnevezes' => 'Munkaora',
                    'mennyiseg' => 1,
                    'netto_egyseg_ar' => 1000,
                    'afa_kulcs' => 27,
                ],
            ],
        ])->assertStatus(200);

        Notification::assertSentTo(
            [$customer],
            OfferUpdated::class
        );

        $this->markTestIncomplete('Email notification on quote creation is expected but not implemented yet.');
    }
}
