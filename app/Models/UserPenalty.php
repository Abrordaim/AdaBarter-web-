<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPenalty extends Model
{
    protected $fillable = [ 
        'user_id',
        'report_id',
        'admin_id',
        'points',
        'category',
        'reason',
        'status',
        'expires_at',
        'appeal_status',
        'appeal_reason',
        'appeal_evidence',
        'appealed_at',
        'appeal_resolved_by',
        'appeal_resolved_at',
        'appeal_admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'expires_at' => 'datetime',
            'appealed_at' => 'datetime',
            'appeal_resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function appealResolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'appeal_resolved_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    public function isAppealed(): bool
    {
        return $this->appeal_status === 'pending';
    }

    public function canAppeal(): bool
    {
        return in_array($this->appeal_status, ['none', null])
            && $this->status === 'active'
            && $this->created_at->greaterThanOrEqualTo(now()->subDays(7));
    }
}

