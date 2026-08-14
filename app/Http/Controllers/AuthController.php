<?php

namespace App\Http\Controllers;

use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Перевірте дані для входу.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = $request->string('email')->toString();
        $password = $request->string('password')->toString();
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return response()->json([
                'message' => 'Неправильний email або пароль.',
            ], 401);
        }

        $token = $user->createToken('promo-wallet-web')->plainTextToken;

        return response()->json([
            'data' => $this->userPayload($user),
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Ви вийшли з акаунта.']);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'balance' => Money::format($user->balance_minor),
        ];
    }
}
