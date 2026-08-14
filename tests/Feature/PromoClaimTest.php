<?php

namespace Tests\Feature;

use App\Models\PromoCode;
use App\Models\PromoClaim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PromoClaimTest extends TestCase
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

    public function test_player_can_claim_an_active_promo_once(): void
    {
        PromoCode::query()->create([
            'code' => 'WELCOME10',
            'amount_minor' => 1250,
            'expires_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/promo/claim', ['code' => 'welcome10']);

        $response->assertCreated()
            ->assertJsonPath('data.bonus', '12.50')
            ->assertJsonPath('data.balance', '112.50');

        $this->assertDatabaseHas('users', [
            'id' => $this->player->id,
            'balance_minor' => 11250,
        ]);
        $this->assertDatabaseHas('promo_claims', [
            'user_id' => $this->player->id,
            'code' => 'WELCOME10',
            'status' => PromoClaim::STATUS_APPLIED,
        ]);

        $duplicate = $this->postJson('/api/promo/claim', ['code' => 'WELCOME10']);

        $duplicate->assertStatus(409)->assertJsonPath('error.code', 'promo_already_used');
        $this->assertDatabaseHas('users', [
            'id' => $this->player->id,
            'balance_minor' => 11250,
        ]);
    }

    public function test_invalid_format_is_rejected_by_validation_without_a_claim_record(): void
    {
        $response = $this->postJson('/api/promo/claim', ['code' => 'bad-code']);

        $response->assertStatus(422)->assertJsonValidationErrors('code');
        $this->assertDatabaseCount('promo_claims', 0);
        $this->assertDatabaseHas('users', [
            'id' => $this->player->id,
            'balance_minor' => 10000,
        ]);
    }

    public function test_not_found_and_expired_promos_have_explicit_rejection_reasons(): void
    {
        $notFound = $this->postJson('/api/promo/claim', ['code' => 'MISSING1']);

        $notFound->assertStatus(422)
            ->assertJsonPath('error.code', 'promo_not_found')
            ->assertJsonPath('error.reason', 'Промокод не знайдено.');

        PromoCode::query()->create([
            'code' => 'EXPIRED1',
            'amount_minor' => 500,
            'expires_at' => now()->subMinute(),
            'is_active' => true,
        ]);

        $expired = $this->postJson('/api/promo/claim', ['code' => 'EXPIRED1']);

        $expired->assertStatus(422)
            ->assertJsonPath('error.code', 'promo_expired')
            ->assertJsonPath('error.reason', 'Термін дії промокоду минув.');

        $this->assertDatabaseCount('promo_claims', 2);
        $this->assertDatabaseHas('users', [
            'id' => $this->player->id,
            'balance_minor' => 10000,
        ]);
    }

    public function test_history_can_be_filtered_and_is_scoped_to_the_authenticated_player(): void
    {
        PromoCode::query()->create([
            'code' => 'WELCOME10',
            'amount_minor' => 1000,
            'expires_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $this->postJson('/api/promo/claim', ['code' => 'WELCOME10'])->assertCreated();
        $this->postJson('/api/promo/claim', ['code' => 'MISSING1'])->assertStatus(422);

        $history = $this->getJson('/api/promo/history?status=rejected');

        $history->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', PromoClaim::STATUS_REJECTED);

        $otherPlayer = User::query()->create([
            'name' => 'Other Player',
            'email' => 'other@example.com',
            'password' => Hash::make('password'),
            'balance_minor' => 0,
        ]);
        Sanctum::actingAs($otherPlayer);

        $this->getJson('/api/promo/history')->assertOk()->assertJsonCount(0, 'data');
    }
}
