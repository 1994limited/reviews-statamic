<?php

namespace NineteenNinetyFour\Reviews\Tests\Feature;

use Illuminate\Http\Client\Request as SentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use NineteenNinetyFour\Reviews\Captcha\Turnstile;
use NineteenNinetyFour\Reviews\Contracts\Captcha;
use NineteenNinetyFour\Reviews\ReviewQuery;
use NineteenNinetyFour\Reviews\Spam;
use NineteenNinetyFour\Reviews\Summary;
use NineteenNinetyFour\Reviews\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class CaptchaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->product();
    }

    protected function captcha(string $driver, array $extra = []): void
    {
        config(['reviews.spam.captcha' => array_merge(['driver' => $driver, 'site_key' => 'site-key', 'secret_key' => 'secret-key', 'score' => 0.5], $extra)]);
    }

    protected function total(): int
    {
        return $this->reviews()->repository()->count(ReviewQuery::make());
    }

    public static function drivers(): array
    {
        return [
            'turnstile' => ['turnstile', 'cf-turnstile-response', 'challenges.cloudflare.com/turnstile/v0/siteverify', 'class="cf-turnstile"', 'challenges.cloudflare.com/turnstile/v0/api.js'],
            'hcaptcha' => ['hcaptcha', 'h-captcha-response', 'api.hcaptcha.com/siteverify', 'class="h-captcha"', 'js.hcaptcha.com/1/api.js'],
            'recaptcha' => ['recaptcha', 'g-recaptcha-response', 'www.google.com/recaptcha/api/siteverify', 'class="g-recaptcha"', 'www.google.com/recaptcha/api.js'],
        ];
    }

    #[Test]
    #[DataProvider('drivers')]
    public function each_captcha_is_drawn_in_the_form_and_checked(string $driver, string $field, string $endpoint, string $widget, string $script): void
    {
        $this->captcha($driver);
        Http::fake(['*' => Http::response(['success' => true])]);

        $html = $this->antlers('{{ reviews:form }}<button>Send</button>{{ /reviews:form }}', ['id' => 'book']);
        $this->assertStringContainsString($widget, $html);
        $this->assertStringContainsString('data-sitekey="site-key"', $html);
        $this->assertStringContainsString($script, $html);
        $this->assertStringNotContainsString('secret-key', $html);

        $this->post('/!/reviews/submit', $this->submission([$field => 'good-token']))->assertSessionHasNoErrors();

        $this->assertSame(1, $this->total());
        Http::assertSent(fn (SentRequest $request) => str_contains($request->url(), $endpoint)
            && $request['secret'] === 'secret-key'
            && $request['response'] === 'good-token');
    }

    #[Test]
    #[DataProvider('drivers')]
    public function a_failed_or_missing_captcha_is_refused(string $driver, string $field, string $endpoint, string $widget, string $script): void
    {
        $this->captcha($driver);
        Http::fake(['*' => Http::response(['success' => false])]);

        $this->post('/!/reviews/submit', $this->submission([$field => 'bad-token']))
            ->assertSessionHasErrors(['captcha' => 'Please confirm you’re not a robot, then send your review again.'], null, 'reviews')
            ->assertSessionMissing('_old_input.'.$field);

        $this->post('/!/reviews/submit', $this->submission(['email' => 'b@example.com']))
            ->assertSessionHasErrors('captcha', null, 'reviews');

        $this->assertSame(0, $this->total());
        Http::assertSentCount(1);
    }

    #[Test]
    public function the_provider_being_down_is_a_refusal_not_an_error(): void
    {
        $this->captcha('turnstile');
        Http::fake(['*' => Http::response('Bad gateway', 502)]);

        $this->post('/!/reviews/submit', $this->submission(['cf-turnstile-response' => 'token']))
            ->assertSessionHasErrors('captcha', null, 'reviews');
    }

    #[Test]
    public function hcaptcha_also_sends_the_site_key(): void
    {
        $this->captcha('hcaptcha');
        Http::fake(['*' => Http::response(['success' => true])]);

        $this->post('/!/reviews/submit', $this->submission(['h-captcha-response' => 'token']));

        Http::assertSent(fn (SentRequest $request) => $request['sitekey'] === 'site-key');
    }

    #[Test]
    public function recaptcha_v3_needs_a_good_enough_score_for_reviews(): void
    {
        $this->captcha('recaptcha_v3', ['score' => 0.7]);

        $html = $this->antlers('{{ reviews:form }}{{ /reviews:form }}', ['id' => 'book']);
        $this->assertStringContainsString('api.js?render=site-key', $html);
        $this->assertStringContainsString('name="g-recaptcha-response"', $html);
        // Its script is in the file the form loads, not inline.
        $this->assertStringContainsString('data-reviews-recaptcha="site-key"', $html);
        $this->assertStringNotContainsString('grecaptcha.execute', $html);
        $this->get('/!/reviews/reviews.js')->assertOk()->assertSee('grecaptcha.execute', false);

        $outcomes = [
            [['success' => true, 'score' => 0.9, 'action' => 'review'], true],
            [['success' => true, 'score' => 0.6, 'action' => 'review'], false],
            [['success' => true, 'score' => 0.9, 'action' => 'login'], false],
            [['success' => true, 'action' => 'review'], false],
            [['success' => false, 'score' => 0.9, 'action' => 'review'], false],
        ];

        $sequence = Http::sequence();
        foreach ($outcomes as [$result]) {
            $sequence->push($result);
        }
        Http::fake(['*' => $sequence]);

        foreach ($outcomes as $i => [$result, $passes]) {
            $this->assertSame($passes, app(Spam::class)->passesCaptcha(Request::create('/', 'POST', ['g-recaptcha-response' => "token-{$i}"])), json_encode($result));
        }
    }

    #[Test]
    public function a_template_can_place_the_captcha_itself(): void
    {
        $this->captcha('turnstile');

        $html = $this->antlers('{{ reviews:form }}<p>{{ captcha }}</p><button>Send</button>{{ /reviews:form }}', ['id' => 'book']);

        $this->assertSame(1, substr_count($html, 'class="cf-turnstile"'));
        $this->assertMatchesRegularExpression('~<p><div class="cf-turnstile"~', $html);
    }

    #[Test]
    public function without_both_keys_there_is_no_captcha(): void
    {
        config(['reviews.spam.captcha' => ['driver' => 'turnstile', 'site_key' => 'site-key', 'secret_key' => null]]);

        $this->assertNull(app(Spam::class)->captcha());
        $this->assertStringNotContainsString('cf-turnstile', $this->antlers('{{ reviews:form }}{{ /reviews:form }}', ['id' => 'book']));
        $this->post('/!/reviews/submit', $this->submission())->assertSessionHasNoErrors();
    }

    #[Test]
    public function an_unknown_driver_is_reported(): void
    {
        config(['reviews.spam.captcha.driver' => 'nope']);

        $this->expectException(InvalidArgumentException::class);

        app(Spam::class)->captcha();
    }

    #[Test]
    public function a_site_can_use_its_own_captcha(): void
    {
        config(['reviews.spam.captcha' => ['driver' => MathsCaptcha::class]]);

        $this->assertStringContainsString('What is 2 + 2?', $this->antlers('{{ reviews:form }}{{ /reviews:form }}', ['id' => 'book']));

        $this->post('/!/reviews/submit', $this->submission(['sum' => '5']))->assertSessionHasErrors('captcha', null, 'reviews');
        $this->post('/!/reviews/submit', $this->submission(['sum' => '4']))->assertSessionHasNoErrors();
    }
}

class MathsCaptcha implements Captcha
{
    public function widget(): string
    {
        return '<label data-reviews-captcha>What is 2 + 2? <input name="sum"></label>';
    }

    public function verify(Request $request): bool
    {
        return $request->input('sum') === '4';
    }

    public function fields(): array
    {
        return ['sum'];
    }

    #[Test]
    public function the_1_1_captcha_classes_still_work_for_code_that_extends_them(): void
    {
        $this->assertSame(Turnstile::class, Spam::CAPTCHAS['turnstile']);

        $captcha = new class('site', 'secret') extends Turnstile {};
        $this->assertStringContainsString('data-sitekey="site"', $captcha->widget());
        $this->assertSame(['cf-turnstile-response'], $captcha->fields());
    }

    #[Test]
    public function a_summary_follows_changes_to_its_numbers(): void
    {
        $summary = new Summary([5 => 1, 4 => 0, 3 => 0, 2 => 0, 1 => 0]);
        $summary->stars[1] = 1;

        $this->assertSame(2, $summary->count());
        $this->assertSame(3.0, $summary->average());
    }
}
