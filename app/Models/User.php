<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property string $role
 * @property string|null $phone
 * @property string|null $city
 * @property string|null $avatar
 * @property bool $is_vip
 * @property bool $is_banned
 * @property Carbon|null $banned_at
 * @property string|null $ban_reason
 * @property int|null $province_id
 * @property int|null $regency_id
 * @property int|null $district_id
 * @property string|null $province_name
 * @property string|null $regency_name
 * @property string|null $district_name
 * @property int $free_post_quota
 * @property int $bonus_post_quota
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Item> $items
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Offer> $sentOffers
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Offer> $receivedOffers
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Subscription> $subscriptions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\VoucherClaim> $voucherClaims
 * @property-read \App\Models\Province|null $province
 * @property-read \App\Models\Regency|null $regency
 * @property-read \App\Models\District|null $district
 */
#[Fillable(['name', 'email', 'password', 'role', 'phone', 'city', 'province_id', 'regency_id', 'district_id', 'province_name', 'regency_name', 'district_name', 'avatar', 'is_vip', 'vip_expires_at', 'boost_quota', 'is_banned', 'banned_at', 'ban_reason', 'free_post_quota', 'bonus_post_quota', 'penalty_points', 'account_status', 'restriction_tier', 'restricted_until', 'active_restrictions'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_vip' => 'boolean',
            'vip_expires_at' => 'datetime',
            'boost_quota' => 'integer',
            'is_banned' => 'boolean',
            'banned_at' => 'datetime',
            'penalty_points' => 'integer',
            'restriction_tier' => 'integer',
            'restricted_until' => 'datetime',
            'active_restrictions' => 'array',
            'province_id' => 'integer',
            'regency_id' => 'integer',
            'district_id' => 'integer',
        ];
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function sentOffers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Offer::class, 'offerer_user_id');
    }

    public function receivedOffers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Offer::class, 'target_user_id');
    }

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function voucherClaims(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VoucherClaim::class);
    }

    public function province(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function regency(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Regency::class);
    }

    public function district(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function wallet(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function withdrawals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function getOrCreateWallet(): Wallet
    {
        return $this->wallet()->firstOrCreate([], ['balance' => 0]);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isVip(): bool
    {
        if ($this->vip_expires_at && $this->vip_expires_at->isFuture()) {
            return true;
        }

        return (bool) $this->is_vip || $this->subscriptions()->active()->exists();
    }

    public function penalties(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserPenalty::class);
    }

    public function isBanned(): bool
    {
        return (bool) $this->is_banned || $this->account_status === 'banned' || (int) $this->restriction_tier >= 4;
    }

    public function isRestricted(): bool
    {
        if ($this->isBanned()) {
            return true;
        }

        if ($this->restriction_tier > 0) {
            if ($this->restricted_until !== null && $this->restricted_until->isPast()) {
                return false;
            }
            return true;
        }

        return false;
    }

    public function hasRestriction(string $feature): bool
    {
        if ($this->isBanned()) {
            return true;
        }

        if (! $this->isRestricted()) {
            return false;
        }

        return in_array($feature, $this->active_restrictions ?? []);
    }

    public function remainingPostQuota(): int
    {
        $activeItemsCount = $this->items()->where('status', 'active')->count();
        return max(0, $this->free_post_quota + $this->bonus_post_quota - $activeItemsCount);
    }

    public function canPost(): bool
    {
        if ($this->hasRestriction('no_post')) {
            return false;
        }

        return $this->isVip() || $this->remainingPostQuota() > 0;
    }

    public function canOffer(): bool
    {
        return ! $this->hasRestriction('no_offer');
    }

    public function canDirectChat(): bool
    {
        return ! $this->hasRestriction('no_direct_chat');
    }

    public function canBoost(): bool
    {
        return ! $this->hasRestriction('no_boost');
    }

    public function ratingsReceived(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Rating::class, 'rated_user_id');
    }

    public function ratingsGiven(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Rating::class, 'rater_id');
    }

    public function averageRating(): ?float
    {
        $avg = $this->ratingsReceived()->avg('rating');
        return $avg !== null ? round((float) $avg, 1) : null;
    }

    public function ratingsCount(): int
    {
        return $this->ratingsReceived()->count();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
