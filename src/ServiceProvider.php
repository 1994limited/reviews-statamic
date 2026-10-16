<?php

namespace NineteenNinetyFour\Reviews;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use NineteenNinetyFour\Reviews\Commands\ExportReviews;
use NineteenNinetyFour\Reviews\Commands\ForgetReviews;
use NineteenNinetyFour\Reviews\Commands\SendRequests;
use NineteenNinetyFour\Reviews\Commands\TransferReviews;
use NineteenNinetyFour\Reviews\Contracts\RequestLog;
use NineteenNinetyFour\Reviews\Contracts\ReviewRepository;
use NineteenNinetyFour\Reviews\Database\EloquentReviewRepository;
use NineteenNinetyFour\Reviews\Fieldtypes\ReviewsFieldtype;
use NineteenNinetyFour\Reviews\Integrations\Cargo;
use NineteenNinetyFour\Reviews\Listeners\Notifier;
use NineteenNinetyFour\Reviews\Requests\DatabaseRequestLog;
use NineteenNinetyFour\Reviews\Requests\FileRequestLog;
use NineteenNinetyFour\Reviews\Stache\ReviewsStore;
use NineteenNinetyFour\Reviews\Stache\StacheReviewRepository;
use NineteenNinetyFour\Reviews\Tags\ReviewsTags;
use NineteenNinetyFour\Reviews\Widgets\PendingReviews;
use Statamic\Events\AddonSettingsSaved;
use Statamic\Events\UserDeleted;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Facades\Stache;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Statamic;

class ServiceProvider extends AddonServiceProvider
{
    /** The Control Panel nav icon (resources/brand/nav-icon.svg). */
    public const ICON = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14"><path d="M2 2.5H9V5H12V9.5H6L3.5 12V9.5H2Z" stroke="currentColor" stroke-width="1" stroke-linejoin="round"></path><path d="M9.5 2.5L12 4.5H9.5Z" stroke="currentColor" stroke-width="1" stroke-linejoin="round"></path><path d="M6.9 4.3L7.4 5.5L8.7 5.6L7.7 6.4L8 7.7L6.9 7L5.8 7.7L6.1 6.4L5.1 5.6L6.4 5.5Z" fill="currentColor"></path></svg>';

    protected $tags = [
        ReviewsTags::class,
    ];

    protected $commands = [
        TransferReviews::class,
        ExportReviews::class,
        ForgetReviews::class,
        SendRequests::class,
    ];

    protected $fieldtypes = [
        ReviewsFieldtype::class,
    ];

    protected $widgets = [
        PendingReviews::class,
    ];

    protected $subscribe = [
        Notifier::class,
    ];

    protected $vite = [
        'input' => [
            'resources/js/cp.js',
            'resources/css/cp.css',
        ],
        'publicDirectory' => 'resources/dist',
        'hotFile' => __DIR__.'/../resources/dist/hot',
    ];

    /**
     * The package is 1994/reviews-statamic; its config, views and
     * translations are read as plain "reviews", so they're loaded by hand.
     * Routes too: actions live at /!/reviews, not /!/reviews-statamic.
     */
    protected $config = false;

    protected $translations = false;

    protected $routes = [
        'web' => null,
        'cp' => __DIR__.'/../routes/cp.php',
        'actions' => null,
    ];

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/reviews.php', 'reviews');

        // The file driver's reviews are cached by the Stache, and Laravel
        // 13's cache unserialises only the classes it is told about.
        $this->registerSerializableClasses([Review::class, ReviewStatus::class, CarbonImmutable::class]);

        $this->app->singleton(ReviewRepository::class, fn ($app) => $app->make(static::repositoryFor(config('reviews.driver', 'file'))));
        $this->app->singleton(RequestLog::class, fn ($app) => $app->make(config('reviews.driver') === 'database' ? DatabaseRequestLog::class : FileRequestLog::class));
        $this->app->singleton(Reviews::class);
        $this->app->singleton(Photos::class);
        $this->app->singleton(Spam::class);
        $this->app->singleton(Cargo::class);
        $this->app->singleton(Presenter::class);
        $this->app->singleton(Schema::class);
        $this->app->singleton(Submissions::class);
    }

    /**
     * The repository class for a driver name.
     */
    public static function repositoryFor(string $driver): string
    {
        return match ($driver) {
            'file' => StacheReviewRepository::class,
            'database' => EloquentReviewRepository::class,
            default => throw new InvalidArgumentException("Unknown reviews driver [{$driver}]. Use file or database."),
        };
    }

    public function bootAddon(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'reviews');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'reviews');

        $this->publishes([__DIR__.'/../config/reviews.php' => config_path('reviews.php')], 'reviews-config');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/reviews')], 'reviews-views');
        $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'reviews-migrations');
        $this->publishes([__DIR__.'/../lang' => $this->app->langPath('vendor/reviews')], 'reviews-translations');
        // The default styles, to build into your own CSS and edit.
        $this->publishes([__DIR__.'/../resources/css/reviews.css' => resource_path('css/reviews.css')], 'reviews-css');

        // The database driver's tables are migrated with the site's own
        // migrations. With the file driver they aren't made at all.
        if (config('reviews.driver') === 'database') {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $this->registerStacheStore();
        $this->registerFrontendRoutes();
        $this->registerSchedule();

        Event::listen(AddonSettingsSaved::class, fn () => Settings::flush());

        Event::listen(UserDeleted::class, function (UserDeleted $event) {
            app(Reviews::class)->forget($event->user->email(), (string) $event->user->id(), config('reviews.deleted_users') === 'delete');
        });

        Permission::extend(function () {
            Permission::group('reviews', __('reviews::messages.cp.reviews'), function () {
                Permission::register('view reviews', function ($permission) {
                    $permission->label(__('reviews::messages.permissions.view'))->children([
                        Permission::make('moderate reviews')->label(__('reviews::messages.permissions.moderate')),
                        Permission::make('reply to reviews')->label(__('reviews::messages.permissions.reply')),
                        Permission::make('delete reviews')->label(__('reviews::messages.permissions.delete')),
                    ]);
                });
            });
        });

        Nav::extend(function ($nav) {
            $nav->content(__('reviews::messages.cp.reviews'))
                ->route('reviews.index')
                ->icon(self::ICON)
                ->can('view reviews');
        });
    }

    /**
     * The file driver's reviews are a Stache store of their own, so they're
     * indexed and cached as entries are, and warmed and cleared with
     * `php please stache:*`. Registered for the transfer command too.
     */
    protected function registerStacheStore(): void
    {
        $store = app(ReviewsStore::class)->directory(config('reviews.drivers.file.directory', storage_path('reviews')));

        Stache::registerStore($store);
    }

    protected function registerFrontendRoutes(): void
    {
        // Reviews are limited per visitor per ten minutes, votes per minute.
        RateLimiter::for('reviews-submit', fn (Request $request) => Limit::perMinutes(10, max(1, (int) config('reviews.throttle.submit', 5)))->by($request->ip()));
        RateLimiter::for('reviews-votes', fn (Request $request) => Limit::perMinute(max(1, (int) config('reviews.throttle.votes', 30)))->by($request->ip()));

        Statamic::pushActionRoutes(function () {
            Route::prefix('reviews')->name('reviews.')->group(__DIR__.'/../routes/actions.php');
        });
    }

    protected function registerSchedule(): void
    {
        if (! config('reviews.schedule', true)) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('reviews:send-requests')->hourly()->withoutOverlapping();
        });
    }
}
