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
 */
#[Fillable(['name', 'email', 'password', 'role', 'phone', 'city', 'avatar', 'is_vip', 'free_post_quota', 'bonus_post_quota'])]
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
        return $this->is_vip || $this->subscriptions()->active()->exists();
    }

    public function remainingPostQuota(): int
    {
        $activeItemsCount = $this->items()->where('status', 'active')->count();
        return max(0, $this->free_post_quota + $this->bonus_post_quota - $activeItemsCount);
    }

    public function canPost(): bool
    {
        return $this->isVip() || $this->remainingPostQuota() > 0;
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
