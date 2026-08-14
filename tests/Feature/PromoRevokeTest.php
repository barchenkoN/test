<?php

namespace Tests\Feature;

use App\Models\PromoCode;
use App\Models\PromoClaim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PromoRevokeTest extends TestCase
{
    use RefreshDatabase;

    private User $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->player = User::query()->create([
            'name' => 'Test Player',
            'email' => 'player@example.com',
            'password' => Hash::make('password'),
            'balance_minor' => 10000,
        ]);

        Sanctum::actingAs($this->player);
    }

    public function test_applied_bonus_can_be_revoked_only_once(): void
    {
        PromoCode::query()->create([
            'code' => 'WELCOME10',
            'amount_minor' => 1250,
            'expires_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $claimResponse = $this->postJson('/api/promo/claim', ['code' => 'WELCOME10']);
        $claimId = $claimResponse->json('data.claim_id');

        $revoke = $this->patchJson("/api/promo/{$claimId}/revoke");

        $revoke->assertOk()
            ->assertJsonPath('data.deducted_amount', '12.50')
            ->assertJsonPath('data.balance', '100.00')
            ->assertJsonPath('data.claim.status', PromoClaim::STATUS_REVOKED);

        $this->assertDatabaseHas('users', [
            'id' => $this->player->id,
            'balance_minor' => 10000,
        ]);

        $secondRevoke = $this->patchJson("/api/promo/{$claimId}/revoke");

        $secondRevoke->assertStatus(409)->assertJsonPath('error.code', 'claim_not_reversible');
        $this->assertDatabaseHas('users', [
            'id' => $this->player->id,
            'balance_minor' => 10000,
        ]);
    }

    public function test_player_cannot_revoke_another_players_claim(): void
    {
        PromoCode::query()->create([
            'code' => 'WELCOME10',
            'amount_minor' => 1000,
            'expires_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $claimId = $this->postJson('/api/promo/claim', ['code' => 'WELCOME10'])->json('data.claim_id');
        $otherPlayer = User::query()->create([
            'name' => 'Other Player',
            'email' => 'other@example.com',
            'password' => Hash::make('password'),
            'balance_minor' => 7500,
        ]);
        Sanctum::actingAs($otherPlayer);

        $this->patchJson("/api/promo/{$claimId}/revoke")
            ->assertNotFound();

        $this->assertDatabaseHas('promo_claims', [
            'id' => $claimId,
            'status' => PromoClaim::STATUS_APPLIED,
        ]);
    }

    public function test_revoke_refuses_to_create_a_negative_balance(): void
    {
        PromoCode::query()->create([
            'code' => 'WELCOME10',
            'amount_minor' => 1000,
            'expires_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $claimId = $this->postJson('/api/promo/claim', ['code' => 'WELCOME10'])->json('data.claim_id');
        $this->player->update(['balance_minor' => 0]);

        $this->patchJson("/api/promo/{$claimId}/revoke")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'insufficient_balance');

        $this->assertDatabaseHas('promo_claims', [
            'id' => $claimId,
            'status' => PromoClaim::STATUS_APPLIED,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $this->player->id,
            'balance_minor' => 0,
        ]);
    }
}
