<?php

namespace NineteenNinetyFour\Reviews\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use NineteenNinetyFour\Reviews\ReviewStatus;
use NineteenNinetyFour\Reviews\Tags\ReviewsTags;
use NineteenNinetyFour\Reviews\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Collection;
use Statamic\Facades\Site;

/**
 * The ready-made views ({{ reviews:display }}) and their default styles.
 */
class DisplayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->product();
    }

    protected function render(string $template = '{{ reviews:display }}'): string
    {
        return $this->antlers($template, ['id' => 'book']);
    }

    protected function flat(string $html): string
    {
        return trim(preg_replace(['/\s+/', '/>\s+</'], [' ', '><'], $html));
    }

    #[Test]
    public function the_section_has_the_summary_the_reviews_and_the_form(): void
    {
        $wonderful = $this->review(['rating' => 5, 'title' => 'Wonderful', 'helpful' => 3]);
        $this->review(['rating' => 3, 'title' => 'Fine']);
        $this->reviews()->reply($wonderful, 'Thank you!');

        $html = $this->flat($this->render());

        $this->assertStringContainsString('<span class="reviews-ui__reply-label">Reply from '.Site::current()->name().'</span>', $html);

        $this->assertStringContainsString('<section class="reviews-ui" id="reviews" aria-labelledby="reviews-heading">', $html);
        $this->assertStringContainsString('<h2 class="reviews-ui__heading" id="reviews-heading">Reviews</h2>', $html);
        $this->assertStringContainsString('<span class="reviews-ui__average-number">4</span>', $html);
        $this->assertStringContainsString('aria-label="Rated 4 out of 5"', $html);
        $this->assertStringContainsString('From 2 reviews', $html);
        $this->assertStringContainsString('href="?rating=5#reviews-list">5 stars</a>', $html);
        $this->assertStringContainsString('href="?rating=1#reviews-list">1 star</a>', $html);
        $this->assertStringContainsString('<h3 class="reviews-ui__review-title">Wonderful</h3>', $html);
        $this->assertStringContainsString('Helpful (3)', $html);
        $this->assertStringContainsString('action="http://localhost/!/reviews/submit"', $html);
        $this->assertStringContainsString('class="reviews-ui__form"', $html);
        $this->assertStringContainsString('Reviews are checked before they appear.', $html);
        // Most helpful first, by default.
        $this->assertLessThan(strpos($html, 'Fine'), strpos($html, 'Wonderful'));
    }

    #[Test]
    public function with_no_reviews_it_invites_the_first(): void
    {
        $html = $this->flat($this->render());

        $this->assertStringContainsString('No reviews yet. Be the first.', $html);
        $this->assertStringNotContainsString('reviews-ui__average-number', $html);
    }

    #[Test]
    public function what_reviewers_wrote_is_escaped(): void
    {
        $review = $this->review(['title' => '<script>alert(1)</script>', 'body' => '<img src=x onerror=alert(1)>', 'name' => '<b>Bold</b> Name']);
        $this->reviews()->reply($review, '<i>Thanks</i>');

        $html = $this->render();

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('<b>Bold', $html);
        $this->assertStringNotContainsString('<i>Thanks', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    #[Test]
    public function visitors_choose_the_order_and_a_rating_from_the_address(): void
    {
        $this->review(['rating' => 5, 'title' => 'Top', 'created_at' => '2026-01-01']);
        $this->review(['rating' => 1, 'title' => 'Bottom', 'created_at' => '2026-02-01']);

        request()->query->replace(['sort' => 'lowest']);
        $html = $this->flat($this->render('{{ reviews:display_list }}'));
        $this->assertLessThan(strpos($html, 'Top'), strpos($html, 'Bottom'));
        $this->assertStringContainsString('<option value="lowest" selected>Lowest rated</option>', $html);

        request()->query->replace(['rating' => '5']);
        $html = $this->flat($this->render('{{ reviews:display_list }}'));
        $this->assertStringContainsString('Top', $html);
        $this->assertStringNotContainsString('Bottom', $html);
        $this->assertStringContainsString('Showing 5-star reviews', $html);

        // Anything else is ignored.
        request()->query->replace(['sort' => '"><script>', 'rating' => '9']);
        $html = $this->render('{{ reviews:display_list }}');
        $this->assertStringNotContainsString('"><script>', $html);
        $this->assertStringContainsString('Top', $html);
        $this->assertStringContainsString('Bottom', $html);
    }

    #[Test]
    public function the_reviews_come_in_pages(): void
    {
        foreach (range(1, 4) as $i) {
            $this->review(['title' => "Review {$i}"]);
        }

        $html = $this->flat($this->render('{{ reviews:display_list per_page="3" }}'));

        $this->assertSame(3, substr_count($html, '<article class="reviews-ui__review"'));
        $this->assertStringContainsString('Page 1 of 2', $html);
        $this->assertStringContainsString('reviews_page=2#reviews-list">Next →</a>', $html);
    }

    #[Test]
    public function each_part_works_on_its_own_in_its_own_wrapper(): void
    {
        $this->review();

        foreach (['display_summary' => 'reviews-ui__summary', 'display_list' => 'reviews-ui__list-wrap', 'display_form' => 'reviews-ui__form'] as $tag => $class) {
            $html = $this->flat($this->render("{{ reviews:{$tag} }}"));

            $this->assertStringStartsWith('<div class="reviews-ui">', $html, $tag);
            $this->assertStringContainsString($class, $html, $tag);
        }

        $this->assertStringNotContainsString('reviews-ui__form', $this->render('{{ reviews:display form="false" heading="What readers say" }}'));
        $this->assertStringContainsString('What readers say', $this->render('{{ reviews:display heading="What readers say" }}'));
    }

    #[Test]
    public function the_form_says_why_someone_cant_review(): void
    {
        config(['reviews.form.who' => 'users']);

        $html = $this->flat($this->render('{{ reviews:display_form }}'));

        $this->assertStringContainsString('<p>Sign in to write a review.</p>', $html);
        $this->assertStringNotContainsString('name="rating"', $html);
    }

    #[Test]
    public function the_form_has_criteria_photos_and_the_optional_fields(): void
    {
        config(['reviews.criteria' => [['handle' => 'value', 'label' => 'Value']], 'reviews.photos.enabled' => true]);

        $html = $this->flat($this->render('{{ reviews:display_form }}'));

        $this->assertStringContainsString('<input type="radio" id="reviews-rating-5" name="rating" value="5" required', $html);
        $this->assertStringContainsString('<span class="reviews-ui__sr-only">5 stars</span>', $html);
        $this->assertStringContainsString('name="ratings[value]"', $html);
        $this->assertStringContainsString('Headline <span class="reviews-ui__muted">(optional)</span>', $html);
        $this->assertStringContainsString('name="photos[]"', $html);
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
    }

    #[Test]
    public function the_form_doesnt_promise_checking_when_reviews_go_straight_up(): void
    {
        config(['reviews.auto_approve' => 'users']);

        $this->assertStringNotContainsString('checked before they appear', $this->render('{{ reviews:display_form }}'));
    }

    #[Test]
    public function the_form_sends_people_back_to_the_reviews(): void
    {
        // A real page, with Statamic filling in the {{ nocache }} region.
        $dir = config('view.paths')[0];
        file_put_contents($dir.'/display-page.antlers.html', '{{ reviews:display_form }}');
        file_put_contents($dir.'/layout.antlers.html', '{{ template_content }}');
        Collection::find('products')->template('display-page')->save();

        try {
            $html = $this->get('/products/the-long-field')->assertOk()->getContent();
        } finally {
            @unlink($dir.'/display-page.antlers.html');
            @unlink($dir.'/layout.antlers.html');
        }

        $this->assertStringNotContainsString('NOCACHE_PLACEHOLDER', $html);
        $this->assertStringContainsString('name="_redirect" value="http://localhost/products/the-long-field#reviews"', $html);
        $this->assertStringContainsString('name="_error_redirect" value="http://localhost/products/the-long-field#write-a-review"', $html);
    }

    #[Test]
    public function the_views_can_be_published_and_changed(): void
    {
        $dir = config('view.paths')[0].'/vendor/reviews/display';
        @mkdir($dir, 0755, true);
        file_put_contents($dir.'/summary.antlers.html', '<p class="mine">My summary for {{ entry }}</p>');
        // Laravel looks in resources/views/vendor/reviews first, when the folder
        // is there as the site boots; here it's made after, so add it as boot would.
        view()->prependNamespace('reviews', dirname($dir));

        try {
            $this->assertStringContainsString('<p class="mine">My summary for book</p>', $this->render('{{ reviews:display_summary }}'));
        } finally {
            @unlink($dir.'/summary.antlers.html');
            @rmdir($dir);
            @rmdir(dirname($dir));
            @rmdir(dirname($dir, 2));
        }
    }

    #[Test]
    public function the_styles_tag_links_the_stylesheet_with_its_version(): void
    {
        $html = $this->render('{{ reviews:styles }}');

        $this->assertMatchesRegularExpression('~^<link rel="stylesheet" href="http://localhost/!/reviews/reviews\.css\?v=[0-9a-f]{12}">$~', $html);
        $this->assertSame(ReviewsTags::stylesheetUrl(), $this->render('{{ reviews:styles_url }}'));
    }

    #[Test]
    public function the_stylesheet_is_served_and_cached_by_version(): void
    {
        $versioned = $this->get(ReviewsTags::stylesheetUrl())
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=utf-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringContainsString('immutable', $versioned->headers->get('Cache-Control'));
        $this->assertStringContainsString('@layer reviews', $versioned->getContent());

        $this->assertStringNotContainsString('immutable', $this->get('/!/reviews/reviews.css')->assertOk()->headers->get('Cache-Control'));
    }

    #[Test]
    public function the_stylesheet_inherits_and_is_made_of_variables(): void
    {
        $css = file_get_contents(ReviewsTags::stylesheetPath());

        $this->assertStringContainsString('--reviews-font: inherit;', $css);
        $this->assertStringContainsString('--reviews-text: currentColor;', $css);
        $this->assertStringContainsString('--reviews-font-size: inherit;', $css);

        // Every colour is a variable, set once, at the top.
        foreach (preg_split('/\R/', $css) as $line) {
            if (preg_match('/#[0-9a-f]{3,8}\b/i', $line)) {
                $this->assertMatchesRegularExpression('/^\s*--reviews-[a-z-]+:/', $line, 'A colour is used directly rather than through a --reviews- variable.');
            }
        }

        // No dark mode of its own: it follows the site's text colour.
        $this->assertStringNotContainsString('prefers-color-scheme', $css);

        // Nothing outranks the site's own CSS.
        $this->assertStringNotContainsString('!important;', str_replace('transition: none !important;', '', $css));
    }

    #[Test]
    public function the_stylesheet_can_be_published(): void
    {
        $this->artisan('vendor:publish', ['--tag' => 'reviews-css', '--force' => true])->assertSuccessful();

        $this->assertFileEquals(ReviewsTags::stylesheetPath(), resource_path('css/reviews.css'));

        @unlink(resource_path('css/reviews.css'));
    }

    #[Test]
    public function the_wording_can_be_published_and_changed(): void
    {
        $this->artisan('vendor:publish', ['--tag' => 'reviews-translations', '--force' => true])->assertSuccessful();

        $this->assertFileExists(lang_path('vendor/reviews/en/messages.php'));

        (new Filesystem)->deleteDirectory(lang_path('vendor/reviews'));
    }

    #[Test]
    public function schema_data_is_the_rating_and_reviews_alone(): void
    {
        $this->review(['rating' => 4, 'body' => 'Fine </script><script>alert(1)</script>']);
        $this->review(['rating' => 2, 'status' => ReviewStatus::Approved]);

        $json = $this->render('{{ reviews:schema_data }}');
        $this->assertStringNotContainsString('</script>', $json);

        $data = json_decode($json, true);
        $this->assertSame(['aggregateRating', 'review'], array_keys($data));
        $this->assertSame('AggregateRating', $data['aggregateRating']['@type']);
        $this->assertEquals(3, $data['aggregateRating']['ratingValue']);
        $this->assertCount(2, $data['review']);

        $aggregate = json_decode($this->render('{{ reviews:schema_data only="aggregate" }}'), true);
        $this->assertSame(2, $aggregate['reviewCount']);

        $this->assertArrayNotHasKey('review', json_decode($this->render('{{ reviews:schema_data reviews="0" }}'), true));
    }

    #[Test]
    public function schema_properties_keep_json_valid_with_reviews_or_without(): void
    {
        // As in SEO Pro's JSON-LD Schema field, which is parsed as Antlers.
        $template = '{ {{ reviews:schema_data format="properties" }} "@context": "https://schema.org", "@type": "Product", "name": "{{ title }}" }';

        $without = json_decode($this->antlers($template, ['id' => 'book', 'title' => 'The Long Field']), true);
        $this->assertSame(['@context', '@type', 'name'], array_keys($without));

        $this->review(['rating' => 4]);

        $with = json_decode($this->antlers($template, ['id' => 'book', 'title' => 'The Long Field']), true);
        $this->assertSame(['aggregateRating', 'review', '@context', '@type', 'name'], array_keys($with));
        $this->assertEquals(4, $with['aggregateRating']['ratingValue']);

        $aggregate = json_decode('{'.$this->antlers('{{ reviews:schema_data only="aggregate" format="properties" }}', ['id' => 'book']).'"x":1}', true);
        $this->assertSame(['aggregateRating', 'x'], array_keys($aggregate));
    }

    #[Test]
    public function schema_data_is_empty_without_approved_reviews(): void
    {
        $this->review(['status' => ReviewStatus::Pending]);

        $this->assertSame('', $this->render('{{ reviews:schema_data }}'));
    }

    #[Test]
    public function the_documented_blade_schema_id_example(): void
    {
        $this->review();

        $html = Blade::render('<s:reviews:schema :id="$permalink.\'#product\'" entry="book" />', ['permalink' => 'https://example.com/products/the-long-field']);

        $this->assertStringContainsString('"@id": "https://example.com/products/the-long-field#product"', $html);
    }

    #[Test]
    public function the_ready_made_views_work_in_blade(): void
    {
        $this->review(['title' => 'From Blade']);

        $html = Blade::render('<s:reviews:display entry="book" />');

        $this->assertStringContainsString('From Blade', $html);
        $this->assertStringContainsString('class="reviews-ui__form"', $html);
    }
}
