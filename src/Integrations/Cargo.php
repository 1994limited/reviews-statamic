<?php

namespace NineteenNinetyFour\Reviews\Integrations;

use Composer\InstalledVersions;
use DuncanMcClean\Cargo\Contracts\Orders\Order as OrderContract;
use DuncanMcClean\Cargo\Facades\Order;
use DuncanMcClean\Cargo\Orders\OrderStatus;
use DuncanMcClean\Cargo\ServiceProvider;
use Illuminate\Support\Collection;
use Statamic\Contracts\Auth\User;
use Statamic\Contracts\Entries\Entry;
use Throwable;

/**
 * Reviews with Cargo (duncanmcclean/statamic-cargo): verified purchases,
 * reviews from buyers only, and emails asking customers for a review. It
 * all switches off when Cargo isn't installed.
 */
class Cargo
{
    /** The Cargo releases this works with: 1.15 up to (not including) 2.0. */
    public const MINIMUM_VERSION = '1.15.0';

    public const BEFORE_VERSION = '2.0.0';

    /** Orders in these statuses count as bought. */
    public const BOUGHT = ['payment_received', 'shipped', 'delivered'];

    protected ?bool $supported = null;

    public function installed(): bool
    {
        return class_exists(ServiceProvider::class)
            && app()->providerIsLoaded(ServiceProvider::class)
            && $this->supported();
    }

    /**
     * Whether the installed Cargo is a version this was built for. An older
     * or newer major one is left alone, so the site carries on without the
     * integration rather than failing.
     */
    public function supported(?string $version = null): bool
    {
        if ($version === null && $this->supported !== null) {
            return $this->supported;
        }

        $version ??= class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('duncanmcclean/statamic-cargo')
            ? InstalledVersions::getVersion('duncanmcclean/statamic-cargo')
            : null;

        $supported = class_exists(OrderStatus::class) && (
            // A development checkout: trust it if the classes are there.
            $version === null || ! preg_match('/^\d/', $version)
            || (version_compare($version, self::MINIMUM_VERSION, '>=') && version_compare($version, self::BEFORE_VERSION, '<'))
        );

        return func_num_args() > 0 ? $supported : $this->supported = $supported;
    }

    /**
     * Whether an entry is a product Cargo sells.
     */
    public function isProduct(mixed $entry): bool
    {
        return $this->installed()
            && $entry instanceof Entry
            && in_array($entry->collectionHandle(), (array) config('statamic.cargo.products.collections', ['products']), true);
    }

    public function order(?string $id): ?OrderContract
    {
        if (! $this->installed() || $id === null || $id === '') {
            return null;
        }

        try {
            return Order::find($id);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Whether an order was paid for (and not cancelled or returned).
     */
    public function paid(OrderContract $order): bool
    {
        $status = $order->status();

        return in_array($status instanceof OrderStatus ? $status->value : (string) $status, self::BOUGHT, true);
    }

    /**
     * The IDs of the products on an order, variants counted as their product.
     *
     * @return array<int, string>
     */
    public function products(OrderContract $order): array
    {
        return collect($order->lineItems()->all())
            ->map(function ($lineItem) {
                try {
                    return $lineItem->product()?->id();
                } catch (Throwable) {
                    return null;
                }
            })
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The order that shows someone bought a product: theirs as a signed-in
     * customer, or one they came from a review request email for.
     *
     * @param  array<int, string>  $orders  Orders opened from review request emails.
     */
    public function purchase(string $product, ?User $user, array $orders = []): ?OrderContract
    {
        if (! $this->installed()) {
            return null;
        }

        $candidates = collect($orders)->map(fn ($id) => $this->order($id))->filter();

        if ($user) {
            $candidates = $candidates->merge($this->ordersFor($user));
        }

        return $candidates->first(fn (OrderContract $order) => $this->paid($order) && in_array($product, $this->products($order), true));
    }

    /**
     * A signed-in customer's orders.
     *
     * @return Collection<int, OrderContract>
     */
    public function ordersFor(User $user): Collection
    {
        try {
            return Order::query()->where('customer', $user->id())->get();
        } catch (Throwable $e) {
            report($e);

            return collect();
        }
    }

    /**
     * The customer's email address and name on an order.
     *
     * @return array{email: ?string, name: ?string}
     */
    public function customer(OrderContract $order): array
    {
        try {
            $customer = $order->customer();
        } catch (Throwable) {
            $customer = null;
        }

        $email = $customer && method_exists($customer, 'email') ? $customer->email() : null;
        $name = $customer && method_exists($customer, 'name') ? $customer->name() : null;

        return ['email' => is_string($email) && $email !== '' ? $email : null, 'name' => is_string($name) && trim($name) !== '' ? trim($name) : null];
    }

    /**
     * Orders that may be due a review request: placed between the window's
     * start and the delay ago, and paid. What's been sent already is the
     * caller's to check.
     *
     * @return Collection<int, OrderContract>
     */
    public function ordersDue(int $delayDays, int $windowDays, string $status): Collection
    {
        if (! $this->installed()) {
            return collect();
        }

        $until = now()->subDays($delayDays);
        $from = now()->subDays($delayDays + $windowDays);

        $statuses = match ($status) {
            'delivered' => ['delivered'],
            'shipped' => ['shipped', 'delivered'],
            default => self::BOUGHT,
        };

        return Order::query()
            ->where('date', '>=', $from)
            ->where('date', '<=', $until)
            ->get()
            ->filter(function (OrderContract $order) use ($statuses) {
                $value = $order->status() instanceof OrderStatus ? $order->status()->value : (string) $order->status();

                return in_array($value, $statuses, true);
            })
            ->values();
    }
}
