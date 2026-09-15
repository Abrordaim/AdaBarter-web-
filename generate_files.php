<?php
$baseDir = '/home/abror/Data/Magang/ada barter/dev/web/';

$migrations = [
    'database/migrations/0001_01_01_000001_create_categories_table.php' => <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
PHP,
    'database/migrations/0001_01_01_000002_create_items_table.php' => <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->enum('condition', ['baru', 'bekas_seperti_baru', 'bekas_baik', 'bekas_layak_pakai']);
            $table->text('desired_items')->nullable();
            $table->decimal('estimated_price', 12, 2)->nullable();
            $table->string('location')->nullable();
            $table->string('city')->nullable();
            $table->enum('status', ['active', 'inactive', 'moderated', 'traded'])->default('active');
            $table->boolean('is_boosted')->default(false);
            $table->timestamp('boost_expires_at')->nullable();
            $table->json('images')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('city');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
PHP,
    'database/migrations/0001_01_01_000003_create_offers_table.php' => <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offerer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('offerer_item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('cash_supplement', 12, 2)->nullable();
            $table->enum('cash_supplement_by', ['offerer', 'target_owner'])->nullable();
            $table->enum('status', ['pending', 'matched', 'rejected', 'completed', 'cancelled'])->default('pending');
            $table->boolean('offerer_approved')->default(false);
            $table->boolean('target_approved')->default(false);
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
PHP,
    'database/migrations/0001_01_01_000004_create_chats_table.php' => <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->enum('type', ['text', 'system', 'image'])->default('text');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
PHP,
    'database/migrations/0001_01_01_000005_create_banners_table.php' => <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('image_url');
            $table->string('redirect_url')->nullable();
            $table->string('advertiser_name')->nullable();
            $table->enum('position', ['home_top', 'home_bottom', 'detail_page'])->default('home_top');
            $table->boolean('is_active')->default(true);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->unsignedInteger('click_count')->default(0);
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
PHP,
    'database/migrations/0001_01_01_000006_create_subscriptions_table.php' => <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('plan', ['vip', 'gold']);
            $table->decimal('price_paid', 10, 2)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('expired_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
PHP,
    'database/migrations/0001_01_01_000007_create_vouchers_table.php' => <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('quota_amount');
            $table->unsignedInteger('max_claims');
            $table->unsignedInteger('claimed_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
PHP,
    'database/migrations/0001_01_01_000008_create_voucher_claims_table.php' => <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->timestamp('claimed_at');
            $table->timestamps();
            
            $table->unique(['user_id', 'voucher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_claims');
    }
};
PHP,
    'database/migrations/0001_01_01_000009_create_transactions_table.php' => <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['pay_per_post', 'subscription', 'boost']);
            $table->decimal('amount', 10, 2);
            $table->string('description')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->string('payment_method')->nullable();
            $table->string('payment_ref')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
PHP,
    'app/Models/Category.php' => <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $icon
 * @property string|null $description
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Item> $items
 */
#[Fillable(['name', 'slug', 'icon', 'description', 'is_active'])]
class Category extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
PHP,
    'app/Models/Item.php' => <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property int $user_id
 * @property int $category_id
 * @property string $title
 * @property string $description
 * @property string $condition
 * @property string|null $desired_items
 * @property float|null $estimated_price
 * @property string|null $location
 * @property string|null $city
 * @property string $status
 * @property bool $is_boosted
 * @property \Illuminate\Support\Carbon|null $boost_expires_at
 * @property array|null $images
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
#[Fillable([
    'user_id', 'category_id', 'title', 'description', 'condition',
    'desired_items', 'estimated_price', 'location', 'city',
    'status', 'is_boosted', 'boost_expires_at', 'images'
])]
class Item extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'estimated_price' => 'decimal:2',
            'is_boosted' => 'boolean',
            'boost_expires_at' => 'datetime',
            'images' => 'array',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function offersAsTarget(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Offer::class, 'target_item_id');
    }

    public function offersAsOfferer(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Offer::class, 'offerer_item_id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function scopeByCity(Builder $query, string $city): void
    {
        $query->where('city', $city);
    }

    public function scopeBoosted(Builder $query): void
    {
        $query->where('is_boosted', true)
              ->where(function ($q) {
                  $q->whereNull('boost_expires_at')
                    ->orWhere('boost_expires_at', '>', now());
              });
    }
}
PHP,
    'app/Models/Offer.php' => <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property int $id
 * @property int $offerer_user_id
 * @property int $offerer_item_id
 * @property int $target_user_id
 * @property int $target_item_id
 * @property float|null $cash_supplement
 * @property string|null $cash_supplement_by
 * @property string $status
 * @property bool $offerer_approved
 * @property bool $target_approved
 * @property \Illuminate\Support\Carbon|null $matched_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property string|null $rejection_reason
 */
#[Fillable([
    'offerer_user_id', 'offerer_item_id', 'target_user_id', 'target_item_id',
    'cash_supplement', 'cash_supplement_by', 'status', 'offerer_approved',
    'target_approved', 'matched_at', 'completed_at', 'rejection_reason'
])]
class Offer extends Model
{
    protected function casts(): array
    {
        return [
            'cash_supplement' => 'decimal:2',
            'offerer_approved' => 'boolean',
            'target_approved' => 'boolean',
            'matched_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function offerer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'offerer_user_id');
    }

    public function targetOwner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function offererItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Item::class, 'offerer_item_id');
    }

    public function targetItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Item::class, 'target_item_id');
    }

    public function chats(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Chat::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isMatched(): bool
    {
        return $this->status === 'matched';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
PHP,
    'app/Models/Chat.php' => <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property int $id
 * @property int $offer_id
 * @property int $sender_id
 * @property string $message
 * @property string $type
 * @property \Illuminate\Support\Carbon|null $read_at
 */
#[Fillable(['offer_id', 'sender_id', 'message', 'type', 'read_at'])]
class Chat extends Model
{
    protected $table = 'chats';

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function offer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function sender(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
PHP,
    'app/Models/Banner.php' => <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property string $title
 * @property string $image_url
 * @property string|null $redirect_url
 * @property string|null $advertiser_name
 * @property string $position
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $expired_at
 * @property int $click_count
 * @property int $view_count
 */
#[Fillable([
    'title', 'image_url', 'redirect_url', 'advertiser_name', 'position',
    'is_active', 'started_at', 'expired_at', 'click_count', 'view_count'
])]
class Banner extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'started_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeNotExpired(Builder $query): void
    {
        $query->where(function ($q) {
            $q->whereNull('expired_at')
              ->orWhere('expired_at', '>', now());
        });
    }
}
PHP,
    'app/Models/Subscription.php' => <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property int $user_id
 * @property string $plan
 * @property float|null $price_paid
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon $expired_at
 * @property bool $is_active
 */
#[Fillable(['user_id', 'plan', 'price_paid', 'started_at', 'expired_at', 'is_active'])]
class Subscription extends Model
{
    protected function casts(): array
    {
        return [
            'price_paid' => 'decimal:2',
            'started_at' => 'datetime',
            'expired_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)
              ->where('expired_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->expired_at->isPast();
    }
}
PHP,
    'app/Models/Voucher.php' => <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property string $code
 * @property string|null $description
 * @property int $quota_amount
 * @property int $max_claims
 * @property int $claimed_count
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $expired_at
 */
#[Fillable([
    'code', 'description', 'quota_amount', 'max_claims',
    'claimed_count', 'is_active', 'expired_at'
])]
class Voucher extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expired_at' => 'datetime',
        ];
    }

    public function claims(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VoucherClaim::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeAvailable(Builder $query): void
    {
        $query->where('is_active', true)
              ->whereColumn('claimed_count', '<', 'max_claims')
              ->where(function ($q) {
                  $q->whereNull('expired_at')
                    ->orWhere('expired_at', '>', now());
              });
    }

    public function isExpired(): bool
    {
        return $this->expired_at && $this->expired_at->isPast();
    }

    public function isClaimable(): bool
    {
        return $this->is_active && $this->claimed_count < $this->max_claims && !$this->isExpired();
    }
}
PHP,
    'app/Models/VoucherClaim.php' => <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property int $id
 * @property int $user_id
 * @property int $voucher_id
 * @property \Illuminate\Support\Carbon $claimed_at
 */
#[Fillable(['user_id', 'voucher_id', 'claimed_at'])]
class VoucherClaim extends Model
{
    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function voucher(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
PHP,
    'app/Models/Transaction.php' => <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property float $amount
 * @property string|null $description
 * @property string $status
 * @property string|null $payment_method
 * @property string|null $payment_ref
 */
#[Fillable([
    'user_id', 'type', 'amount', 'description',
    'status', 'payment_method', 'payment_ref'
])]
class Transaction extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', 'completed');
    }

    public function scopeByType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }
}
PHP,
    'database/seeders/CategorySeeder.php' => <<<'PHP'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Elektronik', 'icon' => '📱'],
            ['name' => 'Fashion & Pakaian', 'icon' => '👕'],
            ['name' => 'Furniture & Interior', 'icon' => '🪑'],
            ['name' => 'Olahraga & Outdoor', 'icon' => '⚽'],
            ['name' => 'Buku & Edukasi', 'icon' => '📚'],
            ['name' => 'Mainan & Hobi', 'icon' => '🎮'],
            ['name' => 'Peralatan Rumah', 'icon' => '🏠'],
            ['name' => 'Otomotif', 'icon' => '🚗'],
            ['name' => 'Kesehatan & Kecantikan', 'icon' => '💊'],
            ['name' => 'Lainnya', 'icon' => '📦'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['slug' => Str::slug($cat['name'])],
                [
                    'name' => $cat['name'],
                    'icon' => $cat['icon'],
                    'is_active' => true,
                ]
            );
        }
    }
}
PHP,
    'database/seeders/VoucherSeeder.php' => <<<'PHP'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Voucher;

class VoucherSeeder extends Seeder
{
    public function run(): void
    {
        Voucher::firstOrCreate(
            ['code' => 'WELCOME2024'],
            [
                'description' => 'Voucher selamat datang',
                'quota_amount' => 2,
                'max_claims' => 100,
            ]
        );

        Voucher::firstOrCreate(
            ['code' => 'BARTERFEST'],
            [
                'description' => 'Promo Barter Festival',
                'quota_amount' => 3,
                'max_claims' => 50,
            ]
        );
    }
}
PHP,
];

foreach ($migrations as $path => $content) {
    $fullPath = $baseDir . $path;
    $dir = dirname($fullPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($fullPath, $content);
    echo "Created: $path\n";
}
