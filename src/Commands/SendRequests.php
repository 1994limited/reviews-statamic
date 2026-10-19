<?php

namespace NineteenNinetyFour\Reviews\Commands;

use DuncanMcClean\Cargo\Contracts\Orders\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Traits\Localizable;
use NineteenNinetyFour\Reviews\Contracts\RequestLog;
use NineteenNinetyFour\Reviews\Integrations\Cargo;
use NineteenNinetyFour\Reviews\Links;
use NineteenNinetyFour\Reviews\Notifications\ReviewRequest;
use NineteenNinetyFour\Reviews\Reviews;
use NineteenNinetyFour\Reviews\Settings;
use Statamic\Console\RunsInPlease;
use Statamic\Contracts\Auth\User;
use Statamic\Facades\Site;
use Throwable;

/**
 * Email customers asking them to review what they bought (with Cargo).
 * Each order is asked once, a set number of days after it was placed, and
 * only about products they haven't reviewed. Scheduled hourly.
 */
class SendRequests extends Command
{
    use Localizable;
    use RunsInPlease;

    protected $signature = 'reviews:send-requests
        {--dry-run : List who would be emailed, without emailing them}';

    protected $description = 'Email customers asking for reviews of what they bought (Cargo)';

    public function handle(Cargo $cargo, RequestLog $log, Reviews $reviews): int
    {
        if (! $cargo->installed()) {
            $this->components->info('Cargo isn\'t installed, so there are no orders to ask about.');

            return self::SUCCESS;
        }

        if (! Settings::bool('requests_enabled')) {
            $this->components->info('Review requests are turned off (Reviews settings → Cargo).');

            return self::SUCCESS;
        }

        $orders = $cargo->ordersDue(
            Settings::int('requests_delay_days', 14, 0, 365),
            max(1, (int) config('reviews.cargo.requests.window_days', 14)),
            (string) Settings::string('requests_status', 'payment_received'),
        );

        $sent = 0;
        $dry = (bool) $this->option('dry-run');

        foreach ($orders as $order) {
            $id = (string) $order->id();

            if ($log->sent($id)) {
                continue;
            }

            // In the order's site's language, whatever runs the command.
            $language = ($order->site() ?? Site::default())->lang();
            $details = $this->withLocale($language, fn () => $this->details($order, $cargo, $log, $reviews));

            if ($details === null) {
                $dry || $log->markSent($id);

                continue;
            }

            if ($dry) {
                $this->line("Order {$id}: {$details['email']}, ".count($details['products']).' products');
                $sent++;

                continue;
            }

            try {
                Notification::route('mail', $details['email'])->notify((new ReviewRequest($details))->locale($language));
                $log->markSent($id);
                $sent++;
            } catch (Throwable $e) {
                report($e);
                $this->components->error("Couldn't email about order {$id}: {$e->getMessage()}");
            }
        }

        $this->components->info(($dry ? 'Would send ' : 'Sent ').$sent.' review '.($sent === 1 ? 'request' : 'requests').'.');

        return self::SUCCESS;
    }

    /**
     * What to send about an order, or null when there's nothing to ask: no
     * email address, an address that opted out, or nothing left to review.
     *
     * @return array<string, mixed>|null
     */
    protected function details(Order $order, Cargo $cargo, RequestLog $log, Reviews $reviews): ?array
    {
        $customer = $cargo->customer($order);
        $email = $customer['email'];

        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL) || $log->optedOut($email)) {
            return null;
        }

        $user = null;

        try {
            $customerObject = $order->customer();
            $user = $customerObject instanceof User ? $customerObject : null;
        } catch (Throwable) {
        }

        $products = collect($cargo->products($order))
            ->map(fn ($id) => $reviews->entry($id, $order->site()?->handle()))
            ->filter(fn ($entry) => $entry && $reviews->reviewable($entry))
            ->reject(fn ($entry) => $reviews->existing($reviews->entryId($entry), $user, $email, $order->site()?->handle()) !== null)
            ->map(fn ($entry) => [
                'title' => (string) $entry->get('title'),
                'url' => Links::order((string) $order->id(), $reviews->entryId($entry)),
            ])
            ->values()
            ->all();

        if ($products === []) {
            return null;
        }

        $site = $order->site() ?? Site::default();
        $replace = fn (string $text) => str_replace(':site', $site->name(), $text);

        return [
            'order' => (string) $order->id(),
            'email' => $email,
            'first_name' => $customer['name'] ? explode(' ', $customer['name'])[0] : null,
            'subject' => $replace(Settings::string('request_subject') ?? (string) __('reviews::messages.mail.request_subject')),
            'intro' => $replace(Settings::string('request_intro') ?? (string) __('reviews::messages.mail.request_intro')),
            'products' => $products,
            'opt_out_url' => Links::optOut($email),
        ];
    }
}
