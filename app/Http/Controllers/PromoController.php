<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClaimPromoRequest;
use App\Models\PromoClaim;
use App\Models\PromoCode;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromoController extends Controller
{
    public function claim(ClaimPromoRequest $request): JsonResponse
    {
        $code = strtoupper($request->string('code')->toString());

        return DB::transaction(function () use ($request, $code): JsonResponse {
            // The player row is the per-player mutex. Every balance mutation for
            // this flow is serialized, including two different promo codes.
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $promo = PromoCode::query()->where('code', $code)->lockForUpdate()->first();

            if (! $promo) {
                return $this->rejected(
                    $user,
                    $code,
                    'Промокод не знайдено.',
                    'promo_not_found',
                );
            }

            $alreadyUsed = PromoClaim::query()
                ->forUser($user->id)
                ->where('promo_code_id', $promo->id)
                ->whereIn('status', [PromoClaim::STATUS_APPLIED, PromoClaim::STATUS_REVOKED])
                ->exists();

            if ($alreadyUsed) {
                return $this->rejected(
                    $user,
                    $code,
                    'Цей промокод уже був використаний вами.',
                    'promo_already_used',
                    $promo,
                    409,
                );
            }

            if (! $promo->is_active) {
                return $this->rejected(
                    $user,
                    $code,
                    'Промокод деактивований.',
                    'promo_inactive',
                    $promo,
                );
            }

            if ($promo->expires_at?->isPast()) {
                return $this->rejected(
                    $user,
                    $code,
                    'Термін дії промокоду минув.',
                    'promo_expired',
                    $promo,
                );
            }

            if ($promo->amount_minor <= 0) {
                return $this->rejected(
                    $user,
                    $code,
                    'Промокод має некоректну суму бонусу.',
                    'promo_invalid_amount',
                    $promo,
                    422,
                );
            }

            $user->increment('balance_minor', $promo->amount_minor);
            $user->refresh();

            $claim = PromoClaim::query()->create([
                'user_id' => $user->id,
                'promo_code_id' => $promo->id,
                'code' => $code,
                'amount_minor' => $promo->amount_minor,
                'status' => PromoClaim::STATUS_APPLIED,
                'claimed_at' => now(),
            ]);

            return response()->json([
                'message' => 'Бонус успішно нараховано.',
                'data' => [
                    'claim_id' => $claim->id,
                    'code' => $claim->code,
                    'bonus' => Money::format($claim->amount_minor),
                    'balance' => Money::format($user->balance_minor),
                ],
            ], 201);
        });
    }

    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:applied,rejected,revoked'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $claims = PromoClaim::query()
            ->forUser($request->user()->id)
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return response()->json([
            'data' => collect($claims->items())->map(fn (PromoClaim $claim) => $this->claimPayload($claim))->values(),
            'meta' => [
                'current_page' => $claims->currentPage(),
                'last_page' => $claims->lastPage(),
                'per_page' => $claims->perPage(),
                'total' => $claims->total(),
            ],
        ]);
    }

    private function rejected(
        User $user,
        string $code,
        string $reason,
        string $errorCode,
        ?PromoCode $promo = null,
        int $status = 422,
    ): JsonResponse {
        PromoClaim::query()->create([
            'user_id' => $user->id,
            'promo_code_id' => $promo?->id,
            'code' => $code,
            'amount_minor' => $promo?->amount_minor ?? 0,
            'status' => PromoClaim::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'claimed_at' => now(),
        ]);

        return response()->json([
            'message' => $reason,
            'error' => [
                'code' => $errorCode,
                'reason' => $reason,
            ],
        ], $status);
    }

    private function claimPayload(PromoClaim $claim): array
    {
        return [
            'id' => $claim->id,
            'code' => $claim->code,
            'amount' => Money::format($claim->amount_minor),
            'status' => $claim->status,
            'reason' => $claim->rejection_reason,
            'claimed_at' => $claim->claimed_at?->toIso8601String(),
            'revoked_at' => $claim->revoked_at?->toIso8601String(),
        ];
    }
}
