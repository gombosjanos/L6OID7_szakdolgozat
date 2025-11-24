<?php

namespace Tests\Feature;

use App\Models\Munkalap;
use App\Models\MunkalapNaplo;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

class WorkorderTest extends FeatureTestCase
{
    public function test_f4_creating_workorder_persists_and_lists_it(): void
    {
        Carbon::setTestNow('2025-01-02 10:00:00');

        $admin = $this->createUser(['jogosultsag' => 'admin']);
        $customer = $this->createUser(['jogosultsag' => 'Ugyfel']);
        $gep = $this->createGep();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/munkalapok', [
            'user_id' => $customer->id,
            'gep_id' => $gep->ID,
            'hibaleiras' => 'Nem indul a gep',
            'megjegyzes' => 'Surget',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'user_id' => $customer->id,
                'gep_id' => $gep->ID,
                'letrehozta' => $admin->id,
            ]);

        $createdId = $response->json('ID');
        $this->assertNotNull($createdId);
        $this->assertEquals('2025-1', $response->json('munkalapsorsz'));

        $this->assertDatabaseHas('munkalapok', [
            'ID' => $createdId,
            'letrehozta' => $admin->id,
            'user_id' => $customer->id,
        ]);

        $list = $this->getJson('/api/munkalapok');
        $list->assertStatus(200)
            ->assertJsonFragment([
                'ID' => $createdId,
                'munkalapsorsz' => '2025-1',
            ]);
    }

    public function test_f5_updating_workorder_logs_status_change(): void
    {
        Carbon::setTestNow('2025-01-03 09:00:00');

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

        $response = $this->patchJson('/api/munkalapok/' . $workorder->ID, [
            'statusz' => 'folyamatban',
            'megjegyzes' => 'Diagnosztika elkezdve',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'statusz' => 'folyamatban',
                'megjegyzes' => 'Diagnosztika elkezdve',
            ]);

        $this->assertDatabaseHas('munkalapok', [
            'ID' => $workorder->ID,
            'statusz' => 'folyamatban',
            'megjegyzes' => 'Diagnosztika elkezdve',
        ]);

        $this->assertDatabaseHas('munkalap_naplo', [
            'munkalap_id' => $workorder->ID,
            'tipus' => 'statusz',
        ]);

        $log = MunkalapNaplo::where('munkalap_id', $workorder->ID)->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('folyamatban', $log->uzenet);
    }

    public function test_f8_unauthorized_user_cannot_view_foreign_workorder(): void
    {
        $owner = $this->createUser(['jogosultsag' => 'Ugyfel']);
        $intruder = $this->createUser(['jogosultsag' => 'Ugyfel']);
        $gep = $this->createGep();
        $workorder = $this->createMunkalap([
            'user_id' => $owner->id,
            'gep_id' => $gep->ID,
            'letrehozta' => $owner->id,
        ]);

        Sanctum::actingAs($intruder);

        $response = $this->getJson('/api/munkalapok/' . $workorder->ID);
        $response->assertStatus(403);

        $message = $response->json('message');
        $this->assertNotEmpty($message);
        $this->assertStringNotContainsString((string) $workorder->munkalapsorsz, json_encode($response->json()));
    }
}
