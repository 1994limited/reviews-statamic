<?php

namespace NineteenNinetyFour\Reviews\Tests\Feature;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use NineteenNinetyFour\Reviews\ReviewStatus;
use NineteenNinetyFour\Reviews\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * The examples in the README and docs, rendered for real, in Antlers and
 * Blade. Each file in tests/__fixtures__/views/docs is copied word for
 * word into the docs, and both versions must say the same.
 */
class DocsExamplesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        view()->addNamespace('docs', __DIR__.'/../__fixtures__/views/docs');
        $this->product();
    }

    protected function render(string $view, string $engine): string
    {
        $path = __DIR__."/../__fixtures__/views/docs/{$view}.".($engine === 'antlers' ? 'antlers.html' : 'blade.php');

        $html = $engine === 'antlers'
            ? $this->antlers(file_get_contents($path), ['id' => 'book'])
            : view("docs::{$view}", ['id' => 'book'])->render();

        // Compare what's shown, not how it's spaced.
        return trim(preg_replace(['/\s+/', '/>\s+</', '/\s+</', '/>\s+/', '/\s+>/'], [' ', '><', '<', '>', '>'], $html));
    }

    public static function engines(): array
    {
        return ['antlers' => ['antlers'], 'blade' => ['blade']];
    }

    #[Test]
    #[DataProvider('engines')]
    public function the_summary(string $engine): void
    {
        $this->assertStringContainsString('No reviews yet.', $this->render('summary', $engine));

        $this->review(['rating' => 5]);
        $this->review(['rating' => 4]);

        $html = $this->render('summary', $engine);

        $this->assertStringContainsString('★★★★⯪', $html);
        $this->assertStringContainsString('4.5 out of 5, from 2 reviews', $html);
        $this->assertStringContainsString('<li>5 stars: 1 (50%)</li>', $html);
        $this->assertStringContainsString('<li>1 stars: 0 (0%)</li>', $html);
    }

    #[Test]
    #[DataProvider('engines')]
    public function the_list(string $engine): void
    {
        $this->assertStringContainsString('Be the first to review this.', $this->render('list', $engine));

        $review = $this->review(['rating' => 4, 'title' => 'Fish & <chips>', 'body' => "One\nTwo", 'name' => 'Jo Bloggs', 'verified' => true, 'created_at' => '2026-05-04']);
        $this->reviews()->reply($review, 'Thank you!');

        foreach (range(1, 10) as $i) {
            $this->review(['created_at' => '2026-04-0'.min(9, $i)]);
        }

        $html = $this->render('list', $engine);

        $this->assertStringContainsString('<article id="review-'.$review->id.'">', $html);
        $this->assertStringContainsString('<p aria-label="4 out of 5">★★★★☆</p>', $html);
        $this->assertStringContainsString('<h3>Fish &amp; &lt;chips&gt;</h3>', $html);
        $this->assertStringContainsString('<p>One<br>Two</p>', $html);
        $this->assertStringContainsString('Jo B., 4 May 2026 · Verified purchase', $html);
        $this->assertStringContainsString('<blockquote><strong>Our reply:</strong>Thank you!</blockquote>', $html);
        $this->assertStringContainsString('/!/reviews/'.$review->id.'/helpful', $html);
        $this->assertStringContainsString('<button>Helpful (0)</button>', $html);
        $this->assertStringContainsString('reviews_page=2">Older</a>', $html);
        $this->assertStringNotContainsString('Newer', $html);
        $this->assertSame(10, substr_count($html, '<article'));
    }

    #[Test]
    #[DataProvider('engines')]
    public function the_form(string $engine): void
    {
        config(['reviews.criteria' => [['handle' => 'value', 'label' => 'Value']]]);

        $html = $this->render('form', $engine);

        $this->assertStringContainsString('action="http://localhost/!/reviews/submit"', $html);
        $this->assertStringContainsString('<input type="hidden" name="_redirect" value="#review">', $html);
        $this->assertStringContainsString('<input type="radio" name="rating" value="5" required>5 stars</label>', $html);
        $this->assertStringContainsString('<input type="radio" name="rating" value="1" required>1 star</label>', $html);
        $this->assertStringContainsString('<label>Value<select name="ratings[value]">', $html);
        $this->assertStringContainsString('<input name="name" value="" required>', $html);
        $this->assertStringContainsString('Send your review', $html);
        $this->assertStringNotContainsString('type="file"', $html);
    }

    #[Test]
    #[DataProvider('engines')]
    public function the_form_after_a_mistake(string $engine): void
    {
        session()->flashInput(['rating' => '4', 'title' => 'Tom & "Jerry"', 'body' => '<b>hi</b>', 'name' => 'Sam', 'email' => 'sam@example.com']);
        session()->put('errors', (new ViewErrorBag)->put('reviews', new MessageBag(['email' => 'The email address must be valid <x>.'])));

        $html = $this->render('form', $engine);

        $this->assertStringContainsString('<ul role="alert"><li>The email address must be valid &lt;x&gt;.</li></ul>', $html);
        $this->assertStringContainsString('value="4" required checked>', $html);
        $this->assertStringContainsString('<input name="title" value="Tom &amp; &quot;Jerry&quot;"', $html);
        $this->assertStringContainsString('required>&lt;b&gt;hi&lt;/b&gt;</textarea>', $html);
    }

    #[Test]
    #[DataProvider('engines')]
    public function the_form_when_someone_cant_review(string $engine): void
    {
        config(['reviews.form.who' => 'users']);

        $html = $this->render('form', $engine);

        $this->assertStringContainsString('<p>Sign in to write a review.</p>', $html);
        $this->assertStringNotContainsString('name="rating"', $html);
    }

    #[Test]
    #[DataProvider('engines')]
    public function the_form_with_photos(string $engine): void
    {
        config(['reviews.photos.enabled' => true]);

        $html = $this->render('form', $engine);

        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        $this->assertStringContainsString('Photos (up to 4)<input type="file" name="photos[]"', $html);
    }

    #[Test]
    #[DataProvider('engines')]
    public function the_schema(string $engine): void
    {
        $this->review(['rating' => 3, 'status' => ReviewStatus::Approved]);

        $html = $this->render('schema', $engine);

        $this->assertStringContainsString('<script type="application/ld+json">', $html);
        $this->assertStringContainsString('"@type": "Book"', $html);
        $this->assertStringContainsString('"ratingValue": 3', $html);
    }

    #[Test]
    public function both_versions_of_each_example_show_the_same(): void
    {
        $this->review(['rating' => 4, 'title' => 'A & B', 'body' => "x\ny", 'created_at' => '2026-05-04']);

        foreach (['summary', 'list', 'form', 'schema'] as $view) {
            $this->assertSame(
                $this->normalise($this->render($view, 'antlers')),
                $this->normalise($this->render($view, 'blade')),
                "The Antlers and Blade versions of [{$view}] differ."
            );
        }
    }

    /**
     * Each render has its own CSRF token and time-trap value.
     */
    protected function normalise(string $html): string
    {
        return preg_replace(['/name="_token" value="[^"]*"/', '/name="_reviews_t" value="[^"]*"/', '/ (checked|selected)(?=[ >])/'], ['', '', ' $1'], $html);
    }
}
