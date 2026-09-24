<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Contracts
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\ItemRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\OfferRepositoryInterface;
use App\Repositories\Contracts\ChatRepositoryInterface;
use App\Repositories\Contracts\VoucherRepositoryInterface;
use App\Repositories\Contracts\BannerRepositoryInterface;

// Implementations
use App\Repositories\UserRepository;
use App\Repositories\ItemRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\OfferRepository;
use App\Repositories\ChatRepository;
use App\Repositories\VoucherRepository;
use App\Repositories\BannerRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * All repository bindings.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        UserRepositoryInterface::class => UserRepository::class,
        ItemRepositoryInterface::class => ItemRepository::class,
        CategoryRepositoryInterface::class => CategoryRepository::class,
        OfferRepositoryInterface::class => OfferRepository::class,
        ChatRepositoryInterface::class => ChatRepository::class,
        VoucherRepositoryInterface::class => VoucherRepository::class,
        BannerRepositoryInterface::class => BannerRepository::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        // Bindings are registered via the $bindings property
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
