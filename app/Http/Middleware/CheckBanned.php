<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckBanned
{
    /**
     * Handle an incoming request.
     * If the authenticated user is banned, revoke all their tokens and return 403.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isBanned()) {
            // Revoke all tokens so any remaining sessions are invalidated
            $user->tokens()->delete();

            $message = 'Akun Anda telah dinonaktifkan oleh Admin AdaBarter.';
            if ($user->ban_reason) {
                $message .= ' Alasan: ' . $user->ban_reason;
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => null,
                'error_code' => 'ACCOUNT_BANNED',
            ], 403);
        }

        return $next($request);
    }
}
