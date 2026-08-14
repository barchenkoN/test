<?php

namespace Database\Seeders;

use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Demo Player',
                'password' => Hash::make('password'),
                'balance_minor' => 10000,
            ],
        );

        PromoCode::query()->updateOrCreate(
            ['code' => 'WELCOME10'],
            [
                'amount_minor' => 1000,
                'expires_at' => now()->addYear(),
                'is_active' => true,
            ],
        );

        PromoCode::query()->updateOrCreate(
            ['code' => 'SPORTS25'],
            [
                'amount_minor' => 2500,
                'expires_at' => now()->addYear(),
                'is_active' => true,
            ],
        );

        PromoCode::query()->updateOrCreate(
            ['code' => 'EXPIRED1'],
            [
                'amount_minor' => 500,
                'expires_at' => now()->subDay(),
                'is_active' => true,
            ],
        );
    }
}
