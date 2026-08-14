<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promo_code_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 12);
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->string('status', 20);
            $table->string('rejection_reason')->nullable();
            $table->timestamp('claimed_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'id']);
            $table->index(['user_id', 'promo_code_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_claims');
    }
};
