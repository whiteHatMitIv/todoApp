<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class TokenValidationController extends Controller
{
    /**
     * Validate a Sanctum personal access token.
     *
     * Accepts token via Authorization: Bearer <token> or JSON body { token: '...' }.
     * Returns JSON { valid: bool, reason?: string, user?: {id,email}, abilities?: array, expires_at?: string }
     */
    public function validateToken(Request $request)
    {
        $token = $request->bearerToken() ?? $request->input('token');

        if (empty($token)) {
            return response()->json(['valid' => false, 'reason' => 'missing_token'], 400);
        }

        $instance = PersonalAccessToken::findToken($token);

        if (! $instance) {
            return response()->json(['valid' => false, 'reason' => 'invalid_token'], 200);
        }

        // Check expiration
        if ($instance->expires_at && now()->greaterThan($instance->expires_at)) {
            return response()->json(['valid' => false, 'reason' => 'expired'], 200);
        }

        // Check tokenable (user) exists
        $user = $instance->tokenable;

        if (! $user) {
            return response()->json(['valid' => false, 'reason' => 'invalid_tokenable'], 200);
        }

        return response()->json([
            'valid' => true,
            'user' => [
                'id' => $user->id,
                'email' => $user->email ?? null,
                'name' => $user->name ?? null,
            ],
            'abilities' => $instance->abilities ?? [],
            'expires_at' => $instance->expires_at ? $instance->expires_at->toDateTimeString() : null,
        ], 200);
    }
}
