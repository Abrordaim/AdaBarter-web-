<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRestriction
{
    /**
     * Handle an incoming request.
     * Check if the authenticated user has a specific restriction active.
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if ($user && $user->hasRestriction($feature)) {
            $messages = [
                'no_post' => 'Akun Anda sedang dibatasi dari mengunggah barang baru karena akumulasi poin penalti.',
                'no_offer' => 'Akun Anda sedang dibatasi dari membuat penawaran barter baru karena akumulasi poin penalti.',
                'no_direct_chat' => 'Akun Anda sedang dibatasi dari memulai obrolan langsung baru karena akumulasi poin penalti.',
                'no_boost' => 'Fitur Iklan Sorotan (Boost) sedang dinonaktifkan pada akun Anda karena akumulasi poin penalti.',
            ];

            $msg = $messages[$feature] ?? 'Fitur ini sedang dibatasi pada akun Anda karena akumulasi poin penalti.';

            if ($user->restricted_until) {
                $msg .= ' Sanksi berlaku hingga ' . $user->restricted_until->format('d M Y, H:i') . '.';
            }

            return response()->json([
                'success' => false,
                'message' => $msg,
                'data' => [
                    'restriction' => $feature,
                    'restricted_until' => $user->restricted_until?->toISOString(),
                    'penalty_points' => (int) $user->penalty_points,
                    'restriction_tier' => (int) $user->restriction_tier,
                ],
                'error_code' => 'FEATURE_RESTRICTED',
            ], 403);
        }

        return $next($request);
    }
}

