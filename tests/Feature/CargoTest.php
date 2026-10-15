<?php

namespace NineteenNinetyFour\Reviews\Tests\Feature;

use DuncanMcClean\Cargo\Facades\Order;
use DuncanMcClean\Cargo\Orders\OrderStatus;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use NineteenNinetyFour\Reviews\Contracts\RequestLog;
use NineteenNinetyFour\Reviews\Integrations\Cargo;
use NineteenNinetyFour\Reviews\Links;
use NineteenNinetyFour\Reviews\Notifications\ReviewRequest;
use NineteenNinetyFour\Reviews\ReviewQuery;
use NineteenNinetyFour\Reviews\ReviewStatus;
use NineteenNinetyFour\Reviews\Tests\CargoTestCase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Entry;

#[RunTestsInSeparateProcesses]
class CargoTest extends CargoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->book();
    }

    protected function latest()
    {
        return $this->reviews()->repository()->get(ReviewQuery::make())->first();
    }

    #[Test]
    public function it_works_with_cargo_1_15_up_to_2(): void
    {
        $cargo = app(Cargo::class);

        $this->assertTrue($cargo->installed());
        $this->assertTrue($cargo->supported('1.15.0'));
        $this->assertTrue($cargo->supported('1.99.3'));
        $this->assertTrue($cargo->supported('dev-main'));
        $this->assertFalse($cargo->supported('1.14.9'));
        $this->assertFalse($cargo->supported('2.0.0'));
    }

    #[Test]
    public function a_signed_in_customer_who_bought_it_is_a_verified_buyer(): void
    {
        $user = $this->user('ada@example.com', name: 'Ada Lovelace');
        $this->order(customer: $user);

        $this->actingAs($user)->post('/!/reviews/submit', $this->submission())->assertSessionHasNoErrors();

        $review = $this->latest();
        $this->assertTrue($review->verified);
        $this->assertNotNull($review->order);

        $html = $this->antlers('{{ reviews status="any" }}{{ /reviews }}{{ reviews:form }}{{ /reviews:form }}', ['id' => 'book']);
        $this->assertIsString($html);
    }

    #[Test]
    public function variants_count_as_their_product(): void
    {
        $this->book('novel', 'A Novel', variants: true);
        $user = $this->user('ada@example.com');
        $this->order([['product' => 'novel', 'variant' => 'Paperback']], $user);

        $this->actingAs($user)->post('/!/reviews/submit', $this->submission(['entry' => 'novel']))->assertSessionHasNoErrors();

        $this->assertTrue($this->latest()->verified);
    }

    #[Test]
    public function someone_who_didnt_buy_it_isnt_verified(): void
    {
        $this->order(customer: $this->user('ada@example.com'));
        $this->book('other', 'Another Book');

        $this->actingAs($this->user('sam@example.com'))->post('/!/reviews/submit', $this->submission())->assertSessionHasNoErrors();
        $this->assertFalse($this->latest()->verified);

        $this->actingAs($this->user('ada@example.com'))->post('/!/reviews/submit', $this->submission(['entry' => 'other']))->assertSessionHasNoErrors();
        $this->assertFalse($this->reviews()->repository()->get(ReviewQuery::make()->entry('other'))->first()->verified);
    }

    #[Test]
    public function only_buyers_can_review_products_when_set(): void
    {
        config(['reviews.cargo.buyers_only' => true]);

        $this->post('/!/reviews/submit', $this->submission())
            ->assertSessionHasErrors(['review' => 'Only customers who bought this can review it.'], null, 'reviews');

        $html = $this->antlers('{{ reviews:form }}{{ reason }}{{ /reviews:form }}', ['id' => 'book']);
        $this->assertStringContainsString('buyers_only', $html);

        $user = $this->user('ada@example.com');
        $this->order(customer: $user);
        $this->actingAs($user)->post('/!/reviews/submit', $this->submission())->assertSessionHasNoErrors();

        // Pages that aren't products are open to anyone.
        Entry::make()->collection('pages')->id('about')->slug('about')->data(['title' => 'About'])->save();
        $this->post('/!/reviews/submit', $this->submission(['entry' => 'about', 'email' => 'x@example.com']))->assertSessionHasNoErrors();
    }

    #[Test]
    public function the_link_in_a_review_request_verifies_a_guest(): void
    {
        config(['reviews.cargo.buyers_only' => true]);
        $order = $this->order();

        $this->get(Links::order($order->id(), 'book'))->assertRedirect('/products/the-long-field#review');

        $form = $this->antlers('{{ reviews:form }}{{ can_review }}|{{ old:name }}|{{ old:email }}{{ /reviews:form }}', ['id' => 'book']);
        $this->assertStringContainsString('1|Ada Lovelace|ada@example.com', $form);

        $this->post('/!/reviews/submit', $this->submission(['name' => 'Ada', 'email' => 'ada@example.com']))->assertSessionHasNoErrors();

        $review = $this->latest();
        $this->assertTrue($review->verified);
        $this->assertSame((string) $order->id(), $review->order);
    }

    #[Test]
    public function order_links_must_be_signed_and_for_a_product_on_the_order(): void
    {
        $order = $this->order();
        $this->book('other', 'Another Book');

        $this->get('/!/reviews/order/'.$order->id().'/book')->assertForbidden();
        $this->get(Links::order($order->id(), 'other'))->assertNotFound();
        $this->get(Links::order('missing', 'book'))->assertNotFound();
        $this->get(str_replace('/book', '/other', Links::order($order->id(), 'book')))->assertForbidden();
    }

    #[Test]
    public function verified_buyers_can_be_published_straight_away(): void
    {
        config(['reviews.auto_approve' => 'verified']);
        $user = $this->user('ada@example.com');
        $this->order(customer: $user);

        $this->actingAs($user)->post('/!/reviews/submit', $this->submission())->assertSessionHas('reviews_success', 'Thank you. Your review is now live.');
        $this->assertSame(ReviewStatus::Approved, $this->latest()->status);

        $this->actingAs($this->user('sam@example.com'))->post('/!/reviews/submit', $this->submission());
        $this->assertSame(1, $this->reviews()->repository()->count(ReviewQuery::make()->status('pending')));
    }

    #[Test]
    public function products_get_product_schema(): void
    {
        $this->review();

        $this->assertStringContainsString('"@type": "Product"', $this->antlers('{{ reviews:schema }}', ['id' => 'book']));
    }

    /*
    |--------------------------------------------------------------------------
    | Review requests
    |--------------------------------------------------------------------------
    */

    protected function aged($order, int $days)
    {
        $order = Order::find($order->id());
        $order->date(now()->subDays($days))->saveQuietly();

        return Order::find($order->id());
    }

    #[Test]
    public function requests_are_off_unless_turned_on(): void
    {
        Notification::fake();
        $this->aged($this->order(), 15);

        $this->artisan('reviews:send-requests')->expectsOutputToContain('turned off')->assertSuccessful();

        Notification::assertNothingSent();
    }

    #[Test]
    public function customers_are_asked_once_after_the_delay(): void
    {
        Notification::fake();
        config(['reviews.cargo.requests.enabled' => true, 'reviews.cargo.requests.delay_days' => 14]);

        $this->book('second', 'Second Book');
        $due = $this->aged($this->order([['product' => 'book'], ['product' => 'second']]), 15);
        $tooSoon = $this->aged($this->order(customer: ['name' => 'Sam', 'email' => 'sam@example.com']), 3);
        $tooOld = $this->aged($this->order(customer: ['name' => 'Old', 'email' => 'old@example.com']), 90);

        $this->artisan('reviews:send-requests')->expectsOutputToContain('Sent 1 review request.')->assertSuccessful();

        Notification::assertSentOnDemandTimes(ReviewRequest::class, 1);
        Notification::assertSentOnDemand(ReviewRequest::class, function (ReviewRequest $notification, array $channels, AnonymousNotifiable $notifiable) use ($due) {
            $mail = $notification->toMail($notifiable);

            $this->assertSame('ada@example.com', $notifiable->routes['mail']);
            $this->assertSame('How was your order from Dog-ear Books?', $mail->subject);
            $this->assertSame('Hello Ada,', (string) $mail->greeting);
            $this->assertSame('Review The Long Field', $mail->actionText);
            $this->assertStringContainsString('/!/reviews/order/'.$due->id().'/book', $mail->actionUrl);
            $this->assertStringContainsString('signature=', $mail->actionUrl);
            $this->assertStringContainsString('Review Second Book', implode(' ', array_map('strval', $mail->outroLines)));
            $this->assertStringContainsString('/!/reviews/opt-out', (string) $mail->salutation);

            return true;
        });

        $this->artisan('reviews:send-requests')->expectsOutputToContain('Sent 0 review requests.');
        Notification::assertSentOnDemandTimes(ReviewRequest::class, 1);

        $this->assertTrue(app(RequestLog::class)->sent((string) $due->id()));
        $this->assertFalse(app(RequestLog::class)->sent((string) $tooSoon->id()));
        $this->assertFalse(app(RequestLog::class)->sent((string) $tooOld->id()));
    }

    #[Test]
    public function products_already_reviewed_are_left_out(): void
    {
        Notification::fake();
        config(['reviews.cargo.requests.enabled' => true]);
        $this->review(['email' => 'ada@example.com']);
        $order = $this->aged($this->order(), 15);

        $this->artisan('reviews:send-requests')->expectsOutputToContain('Sent 0 review requests.');

        Notification::assertNothingSent();
        $this->assertTrue(app(RequestLog::class)->sent((string) $order->id()));
    }

    #[Test]
    public function customers_can_stop_requests(): void
    {
        Notification::fake();
        config(['reviews.cargo.requests.enabled' => true]);
        $this->aged($this->order(), 15);

        $url = Links::optOut('ada@example.com');

        // Following the link only asks; a mail scanner opening it changes nothing.
        $this->get($url)->assertOk()->assertSee('Stop sending review requests to ada@example.com?');
        $this->assertFalse(app(RequestLog::class)->optedOut('ada@example.com'));

        $this->post($url)->assertOk()->assertSee('We won’t ask you for reviews again.', false);
        $this->assertTrue(app(RequestLog::class)->optedOut('ADA@example.com'));

        $this->post('/!/reviews/opt-out?email=sam@example.com')->assertForbidden();

        $this->artisan('reviews:send-requests');
        Notification::assertNothingSent();
    }

    #[Test]
    public function a_dry_run_sends_nothing(): void
    {
        Notification::fake();
        config(['reviews.cargo.requests.enabled' => true]);
        $order = $this->aged($this->order(), 15);

        $this->artisan('reviews:send-requests', ['--dry-run' => true])->expectsOutputToContain('Would send 1 review request.');

        Notification::assertNothingSent();
        $this->assertFalse(app(RequestLog::class)->sent((string) $order->id()));
    }

    #[Test]
    public function requests_wait_for_the_chosen_status(): void
    {
        Notification::fake();
        config(['reviews.cargo.requests.enabled' => true, 'reviews.cargo.requests.status' => 'shipped']);
        $order = $this->aged($this->order(), 15);

        $this->artisan('reviews:send-requests');
        Notification::assertNothingSent();

        $order->status(OrderStatus::Shipped)->saveQuietly();

        $this->artisan('reviews:send-requests');
        Notification::assertSentOnDemandTimes(ReviewRequest::class, 1);
    }
}
