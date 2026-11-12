<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Report;
use App\Models\User;
use App\Models\UserPenalty;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PenaltyService
{
    /**
     * Award penalty points to a user and recalculate account tier & restrictions.
     */
    public function awardPoints(
        User $user,
        int $points,
        string $category,
        string $reason,
        ?int $reportId = null,
        ?int $adminId = null
    ): UserPenalty {
        return DB::transaction(function () use ($user, $points, $category, $reason, $reportId, $adminId) {
            $penalty = UserPenalty::create([
                'user_id' => $user->id,
                'report_id' => $reportId,
                'admin_id' => $adminId,
                'points' => $points,
                'category' => $category,
                'reason' => $reason,
                'status' => 'active',
                'expires_at' => now()->addDays(90),
                'appeal_status' => 'none',
            ]);

            // If linked to a report, mark report as resolved
            if ($reportId) {
                $report = Report::find($reportId);
                if ($report) {
                    $report->status = 'resolved';
                    $report->penalty_points_awarded = $points;
                    $report->action_taken = $points >= 15 ? 'user_banned' : 'penalized';
                    $report->resolved_by = $adminId;
                    $report->resolved_at = now();
                    $report->save();
                }
            }

            // Recalculate tier & restrictions for the user
            $this->recalculateUserPoints($user);

            return $penalty;
        });
    }

    /**
     * Recalculate active penalty points, tier, and restrictions for a user.
     */
    public function recalculateUserPoints(User $user): void
    {
        $activePoints = (int) UserPenalty::where('user_id', $user->id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->sum('points');

        $user->penalty_points = $activePoints;

        if ($activePoints >= 15) {
            // Tier 4: Permanent Ban
            $user->account_status = 'banned';
            $user->restriction_tier = 4;
            $user->restricted_until = null;
            $user->active_restrictions = ['no_post', 'no_offer', 'no_direct_chat', 'no_boost', 'de_rank', 'hide_items'];
            $user->is_banned = true;
            $user->banned_at = now();
            $user->ban_reason = "Akumulasi poin penalti mencapai batas pemblokiran ({$activePoints} poin)";
            $user->save();

            // Revoke all tokens immediately
            $user->tokens()->delete();

            // Moderate all active items
            Item::where('user_id', $user->id)->where('status', 'active')->update(['status' => 'moderated']);
        } elseif ($activePoints >= 9) {
            // Tier 3: Heavy Restriction
            $user->account_status = 'restricted';
            $user->restriction_tier = 3;
            $user->restricted_until = now()->addDays(28);
            $user->active_restrictions = ['no_post', 'no_offer', 'no_direct_chat', 'no_boost', 'de_rank', 'hide_items'];
            $user->is_banned = false;
            $user->save();
        } elseif ($activePoints >= 6) {
            // Tier 2: Moderate Restriction
            $user->account_status = 'restricted';
            $user->restriction_tier = 2;
            $user->restricted_until = now()->addDays(28);
            $user->active_restrictions = ['no_post', 'no_offer', 'no_boost', 'de_rank'];
            $user->is_banned = false;
            $user->save();
        } elseif ($activePoints >= 3) {
            // Tier 1: Light Restriction
            $user->account_status = 'restricted';
            $user->restriction_tier = 1;
            $user->restricted_until = now()->addDays(28);
            $user->active_restrictions = ['no_boost', 'de_rank'];
            $user->is_banned = false;
            $user->save();
        } elseif ($activePoints >= 1) {
            // Tier 0: Warning
            $user->account_status = 'warned';
            $user->restriction_tier = 0;
            $user->restricted_until = null;
            $user->active_restrictions = [];
            $user->is_banned = false;
            $user->save();
        } else {
            // Clean Account
            $user->account_status = 'active';
            $user->restriction_tier = 0;
            $user->restricted_until = null;
            $user->active_restrictions = [];
            // Only unban if was banned due to penalty points
            if ($user->is_banned && str_contains($user->ban_reason ?? '', 'poin penalti')) {
                $user->is_banned = false;
                $user->banned_at = null;
                $user->ban_reason = null;
            }
            $user->save();
        }
    }

    /**
     * Submit an appeal for a penalty point.
     */
    public function submitAppeal(
        User $user,
        int $penaltyId,
        string $reason,
        ?UploadedFile $evidence = null
    ): UserPenalty {
        $penalty = UserPenalty::where('id', $penaltyId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (!$penalty->canAppeal()) {
            throw ValidationException::withMessages([
                'penalty' => ['Penalti ini tidak memenuhi syarat untuk diajukan banding atau batas waktu banding (7 hari) telah terlewati.'],
            ]);
        }

        $evidencePath = null;
        if ($evidence) {
            $evidencePath = $evidence->store('penalty_appeals', 'public');
        }

        $penalty->appeal_status = 'pending';
        $penalty->appeal_reason = $reason;
        if ($evidencePath) {
            $penalty->appeal_evidence = $evidencePath;
        }
        $penalty->appealed_at = now();
        $penalty->save();

        return $penalty;
    }

    /**
     * Resolve an appeal (accept or reject) by an admin.
     */
    public function resolveAppeal(
        int $penaltyId,
        bool $accept,
        string $notes,
        int $adminId
    ): UserPenalty {
        return DB::transaction(function () use ($penaltyId, $accept, $notes, $adminId) {
            $penalty = UserPenalty::with('user')->findOrFail($penaltyId);

            if ($penalty->appeal_status !== 'pending') {
                throw new \Exception('Banding tidak dalam status menunggu peninjauan.');
            }

            $penalty->appeal_resolved_by = $adminId;
            $penalty->appeal_resolved_at = now();
            $penalty->appeal_admin_notes = $notes;

            if ($accept) {
                $penalty->appeal_status = 'accepted';
                $penalty->status = 'revoked';
                $penalty->save();

                // Recalculate user points after revocation
                $this->recalculateUserPoints($penalty->user);
            } else {
                $penalty->appeal_status = 'rejected';
                $penalty->save();
            }

            return $penalty;
        });
    }

    /**
     * Expire old penalty points past 90 days.
     */
    public function expirePenalties(): int
    {
        $expiredPenalties = UserPenalty::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        if ($expiredPenalties->isEmpty()) {
            return 0;
        }

        $userIds = $expiredPenalties->pluck('user_id')->unique();

        UserPenalty::whereIn('id', $expiredPenalties->pluck('id'))
            ->update(['status' => 'expired']);

        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if ($user) {
                $this->recalculateUserPoints($user);
            }
        }

        return $expiredPenalties->count();
    }

    /**
     * Get account health summary for a user.
     */
    public function getAccountHealth(User $user): array
    {
        $user->refresh();

        $tierNames = [
            0 => $user->penalty_points > 0 ? 'Peringatan' : 'Akun Sehat',
            1 => 'Pembatasan Ringan',
            2 => 'Pembatasan Sedang',
            3 => 'Pembatasan Berat',
            4 => 'Pemblokiran Permanen',
        ];

        $healthLevels = [
            0 => $user->penalty_points > 0 ? 'warning' : 'good',
            1 => 'restricted',
            2 => 'restricted',
            3 => 'restricted',
            4 => 'banned',
        ];

        $penalties = UserPenalty::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'points' => $p->points,
                    'category' => $p->category,
                    'reason' => $p->reason,
                    'status' => $p->status,
                    'expires_at' => $p->expires_at?->toISOString(),
                    'appeal_status' => $p->appeal_status,
                    'appeal_reason' => $p->appeal_reason,
                    'appeal_admin_notes' => $p->appeal_admin_notes,
                    'can_appeal' => $p->canAppeal(),
                    'created_at' => $p->created_at->toISOString(),
                ];
            });

        return [
            'penalty_points' => (int) $user->penalty_points,
            'account_status' => $user->account_status ?? 'active',
            'restriction_tier' => (int) ($user->restriction_tier ?? 0),
            'tier_name' => $tierNames[$user->restriction_tier ?? 0] ?? 'Akun Sehat',
            'health_level' => $healthLevels[$user->restriction_tier ?? 0] ?? 'good',
            'restricted_until' => $user->restricted_until?->toISOString(),
            'active_restrictions' => $user->active_restrictions ?? [],
            'penalties' => $penalties,
        ];
    }
}
