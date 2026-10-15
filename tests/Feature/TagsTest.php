<?php

namespace NineteenNinetyFour\Reviews\Tests\Feature;

use NineteenNinetyFour\Reviews\ReviewStatus;
use NineteenNinetyFour\Reviews\Spam;
use NineteenNinetyFour\Reviews\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Entry;

class TagsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->product();
    }

    protected function page(string $template): string
    {
        return preg_replace('/\s+/', ' ', trim($this->antlers($template, ['id' => 'book'])));
    }

    #[Test]
    public function it_lists_an_entrys_approved_reviews(): void
    {
        $this->review(['title' => 'Approved one', 'created_at' => '2026-01-02']);
        $this->review(['title' => 'Waiting', 'status' => ReviewStatus::Pending]);
        $this->review(['title' => 'Junk', 'status' => ReviewStatus::Spam]);
        $this->review(['title' => 'Another book', 'entry' => 'other']);
        $this->review(['title' => 'Older', 'created_at' => '2026-01-01']);

        $html = $this->page('{{ reviews }}[{{ title }}]{{ /reviews }}');

        $this->assertSame('[Approved one][Older]', $html);
    }

    #[Test]
    public function it_takes_the_entry_from_a_parameter(): void
    {
        $this->product('other', 'Other Book');
        $this->review(['entry' => 'other', 'title' => 'Other']);

        $this->assertSame('[Other]', $this->page('{{ reviews entry="other" }}[{{ title }}]{{ /reviews }}'));
    }

    #[Test]
    public function visitors_text_comes_out_escaped(): void
    {
        $this->review([
            'title' => '<script>alert(1)</script>',
            'body' => "Line one <b>bold</b>\nLine two",
            'name' => '<img src=x onerror=alert(1)> Smith',
        ]);
        $review = $this->reviews()->repository()->all()->first();
        $this->reviews()->reply($review, "Thanks <i>so</i> much\nThe team");

        $html = $this->page('{{ reviews }}{{ title }}|{{ body }}|{{ name }}|{{ reply }}{{ /reviews }}');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<i>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('Line one &lt;b&gt;bold&lt;/b&gt;<br> Line two', $html);
        $this->assertStringContainsString('Thanks &lt;i&gt;so&lt;/i&gt; much<br> The team', $html);
    }

    #[Test]
    public function names_are_shortened_unless_set_to_full(): void
    {
        $this->review(['name' => 'Jo Anne Bloggs']);

        $this->assertSame('Jo B.', $this->page('{{ reviews }}{{ name }}{{ /reviews }}'));

        config(['reviews.name_display' => 'full']);

        $this->assertSame('Jo Anne Bloggs', $this->page('{{ reviews }}{{ name }}{{ /reviews }}'));
    }

    #[Test]
    public function it_sorts_filters_and_limits(): void
    {
        $this->review(['title' => 'A', 'rating' => 3, 'created_at' => '2026-01-01']);
        $this->review(['title' => 'B', 'rating' => 5, 'created_at' => '2026-01-02', 'verified' => true]);
        $this->review(['title' => 'C', 'rating' => 1, 'created_at' => '2026-01-03', 'helpful' => 4]);

        $this->assertSame('BAC', $this->page('{{ reviews sort="highest" }}{{ title }}{{ /reviews }}'));
        $this->assertSame('CAB', $this->page('{{ reviews sort="lowest" }}{{ title }}{{ /reviews }}'));
        $this->assertSame('C', $this->page('{{ reviews sort="helpful" limit="1" }}{{ title }}{{ /reviews }}'));
        $this->assertSame('AB', $this->page('{{ reviews rating="3|5" sort="oldest" }}{{ title }}{{ /reviews }}'));
        $this->assertSame('B', $this->page('{{ reviews verified="true" }}{{ title }}{{ /reviews }}'));
    }

    #[Test]
    public function it_paginates(): void
    {
        foreach (range(1, 5) as $i) {
            $this->review(['title' => "R{$i}", 'created_at' => "2026-01-0{$i}"]);
        }

        $template = '{{ reviews paginate="2" as="items" }}{{ items }}{{ title }}{{ /items }}|{{ paginate }}{{ current_page }}/{{ total_pages }}/{{ total_items }}{{ /paginate }}{{ /reviews }}';

        $this->assertSame('R5R4|1/3/5', $this->page($template));

        $this->get('/?reviews_page=2');
        request()->query->set('reviews_page', '2');

        $this->assertSame('R3R2|2/3/5', $this->page($template));
    }

    #[Test]
    public function no_reviews_shows_no_results(): void
    {
        $this->assertSame('None yet', $this->page('{{ reviews }}{{ if no_results }}None yet{{ /if }}{{ /reviews }}'));
    }

    #[Test]
    public function the_summary_has_the_average_count_and_breakdown(): void
    {
        $this->review(['rating' => 5]);
        $this->review(['rating' => 5]);
        $this->review(['rating' => 4]);
        $this->review(['rating' => 1]);
        $this->review(['rating' => 1, 'status' => ReviewStatus::Pending]);

        $html = $this->page('{{ reviews:summary }}{{ average }} from {{ count }} ({{ percent }}%){{ breakdown }} {{ rating }}:{{ total }}:{{ percent }}{{ /breakdown }}{{ /reviews:summary }}');

        $this->assertSame('3.8 from 4 (75%) 5:2:50 4:1:25 3:0:0 2:0:0 1:1:25', $html);
        $this->assertSame('4', $this->page('{{ reviews:count }}'));
        $this->assertSame('3.8', $this->page('{{ reviews:average }}'));
    }

    #[Test]
    public function the_summary_follows_moderation(): void
    {
        $review = $this->review(['rating' => 2, 'status' => ReviewStatus::Pending]);

        $this->assertSame('0', $this->page('{{ reviews:count }}'));

        $this->reviews()->approve($review);

        $this->assertSame('1 2', $this->page('{{ reviews:summary }}{{ count }} {{ average }}{{ /reviews:summary }}'));

        $this->reviews()->spam($review);

        $this->assertSame('0', $this->page('{{ reviews:count }}'));
    }

    #[Test]
    public function the_summary_has_each_criterions_average(): void
    {
        config(['reviews.criteria' => [['handle' => 'value', 'label' => 'Value <for money>']]]);
        $this->review(['ratings' => ['value' => 4]]);
        $this->review(['ratings' => ['value' => 3]]);

        $this->assertSame(
            'Value &lt;for money&gt;: 3.5',
            $this->page('{{ reviews:summary }}{{ criteria }}{{ label }}: {{ average }}{{ /criteria }}{{ /reviews:summary }}')
        );
    }

    #[Test]
    public function stars_are_full_half_or_empty(): void
    {
        $template = '{{ reviews:stars rating="3.5" }}{{ if full }}F{{ elseif half }}H{{ else }}E{{ /if }}{{ /reviews:stars }}';

        $this->assertSame('FFFHE', $this->page($template));
        $this->assertSame('FFFFF', $this->page(str_replace('3.5', '4.8', $template)));
        $this->assertSame('EEEEE', $this->page(str_replace('3.5', 'nope', $template)));
        $this->review(['rating' => 4]);
        $this->assertSame('FFFFE', $this->page('{{ reviews }}{{ stars }}{{ if full }}F{{ else }}E{{ /if }}{{ /stars }}{{ /reviews }}'));
    }

    #[Test]
    public function the_form_posts_to_the_action_with_spam_checks(): void
    {
        $html = $this->antlers('{{ reviews:form redirect="/thanks" class="review-form" }}<button>Send</button>{{ /reviews:form }}', ['id' => 'book']);

        $this->assertStringContainsString('action="http://localhost/!/reviews/submit"', $html);
        $this->assertStringContainsString('method="POST"', $html);
        $this->assertStringContainsString('class="review-form"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('<input type="hidden" name="entry" value="book">', $html);
        $this->assertStringContainsString('<input type="hidden" name="_redirect" value="/thanks">', $html);
        $this->assertStringContainsString('name="website"', $html);
        $this->assertStringContainsString('name="'.Spam::TIME_FIELD.'"', $html);
        $this->assertStringContainsString('<button>Send</button>', $html);
        $this->assertStringNotContainsString('enctype', $html);
    }

    #[Test]
    public function the_form_is_multipart_when_photos_are_on(): void
    {
        config(['reviews.photos.enabled' => true]);

        $html = $this->antlers('{{ reviews:form }}{{ if photos_enabled }}up to {{ max_photos }}{{ /if }}{{ /reviews:form }}', ['id' => 'book']);

        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        $this->assertStringContainsString('up to 4', $html);
    }

    #[Test]
    public function the_form_says_why_someone_cant_review(): void
    {
        config(['reviews.form.who' => 'users']);

        $template = '{{ reviews:form }}{{ if can_review }}OK{{ else }}{{ reason }}: {{ reason_message }}{{ /if }}{{ /reviews:form }}';

        $this->assertStringContainsString('sign_in: Sign in to write a review.', $this->antlers($template, ['id' => 'book']));

        $this->actingAs($this->user());

        $this->assertStringContainsString('OK', $this->antlers($template, ['id' => 'book']));
        $this->assertSame('', $this->antlers($template, ['id' => 'nope']));
    }

    #[Test]
    public function the_form_shows_errors_and_old_input_escaped(): void
    {
        $this->from('/products/the-long-field')->post('/!/reviews/submit', $this->submission([
            'rating' => '',
            'title' => '"><script>alert(1)</script>',
        ]));

        $this->app['session']->start();
        $this->app['request']->setLaravelSession($this->app['session.store']);

        $html = $this->antlers('{{ reviews:form }}[{{ errors:rating }}][{{ old:title }}]{{ error_list }}<{{ value }}>{{ /error_list }}{{ /reviews:form }}', ['id' => 'book']);

        $this->assertStringContainsString('[Choose a rating from 1 to 5 stars.]', $html);
        $this->assertStringContainsString('[&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;]', $html);
        $this->assertStringNotContainsString('<script>alert', $html);
    }

    #[Test]
    public function the_form_lists_the_criteria(): void
    {
        config(['reviews.criteria' => [['handle' => 'quality', 'label' => 'Quality']]]);

        $html = $this->antlers('{{ reviews:form }}{{ criteria }}{{ label }}={{ name }};{{ /criteria }}{{ /reviews:form }}', ['id' => 'book']);

        $this->assertStringContainsString('Quality=ratings[quality];', $html);
    }

    #[Test]
    public function the_form_fills_in_a_signed_in_reviewers_own_review(): void
    {
        $this->review(['user' => 'sam', 'title' => 'Mine', 'rating' => 3]);
        $this->actingAs($this->user('sam@example.com'));

        $html = $this->antlers('{{ reviews:form }}{{ if editing }}Editing {{ old:title }} {{ old:rating }}{{ /if }}{{ /reviews:form }}', ['id' => 'book']);

        $this->assertStringContainsString('Editing Mine 3', $html);
    }

    #[Test]
    public function the_helpful_and_delete_forms_go_inside_the_list(): void
    {
        $review = $this->review(['user' => 'sam']);
        $this->actingAs($this->user('sam@example.com'));

        $html = $this->antlers('{{ reviews }}{{ reviews:helpful }}Helpful ({{ helpful }}){{ /reviews:helpful }}{{ if is_mine }}{{ reviews:delete }}Delete{{ /reviews:delete }}{{ /if }}{{ /reviews }}', ['id' => 'book']);

        $this->assertStringContainsString('action="http://localhost/!/reviews/'.$review->id.'/helpful"', $html);
        $this->assertStringContainsString('Helpful (0)', $html);
        $this->assertStringContainsString('action="http://localhost/!/reviews/delete"', $html);
        $this->assertStringContainsString('name="id" value="'.$review->id.'"', $html);
    }

    #[Test]
    public function success_and_errors_tags_read_the_session(): void
    {
        session()->flash('reviews_success', 'Thanks <b>you</b>');

        $this->assertSame('Thanks &lt;b&gt;you&lt;/b&gt;', $this->page('{{ reviews:success }}'));
    }

    #[Test]
    public function the_schema_has_the_average_and_reviews_and_cant_be_broken_out_of(): void
    {
        $this->review(['rating' => 5, 'title' => 'Great', 'body' => 'Loved it </script><script>alert(1)</script>', 'name' => 'Jo Bloggs', 'created_at' => '2026-05-01']);
        $this->review(['rating' => 4, 'status' => ReviewStatus::Approved]);

        $html = $this->antlers('{{ reviews:schema }}', ['id' => 'book']);

        $this->assertStringStartsWith('<script type="application/ld+json">', $html);
        $this->assertSame(1, substr_count($html, '</script>'));

        $json = json_decode(str_replace(['<script type="application/ld+json">', '</script>'], '', $html), true);

        $this->assertSame('https://schema.org', $json['@context']);
        $this->assertSame('Thing', $json['@type']);
        $this->assertSame('The Long Field', $json['name']);
        $this->assertSame('http://localhost/products/the-long-field', $json['url']);
        $this->assertEquals(4.5, $json['aggregateRating']['ratingValue']);
        $this->assertSame(2, $json['aggregateRating']['reviewCount']);
        $this->assertCount(2, $json['review']);
        $this->assertSame('Jo B.', $json['review'][1]['author']['name']);
        $this->assertSame('Loved it </script><script>alert(1)</script>', $json['review'][1]['reviewBody']);
    }

    #[Test]
    public function the_schema_type_comes_from_config_or_the_tag(): void
    {
        $this->review();
        config(['reviews.schema.types' => ['products' => 'Book']]);

        $this->assertStringContainsString('"@type": "Book"', $this->antlers('{{ reviews:schema }}', ['id' => 'book']));
        $this->assertStringContainsString('"@type": "Course"', $this->antlers('{{ reviews:schema type="Course" id="#course" reviews="0" }}', ['id' => 'book']));
        $this->assertStringContainsString('"@id": "#course"', $this->antlers('{{ reviews:schema type="Course" id="#course" }}', ['id' => 'book']));
        $this->assertStringNotContainsString('"review"', $this->antlers('{{ reviews:schema reviews="0" }}', ['id' => 'book']));
        $this->assertStringContainsString('"@type": "Book"', $this->antlers('{{ reviews:schema type="Bad Type!" }}', ['id' => 'book']));
    }

    #[Test]
    public function there_is_no_schema_without_reviews(): void
    {
        $this->review(['status' => ReviewStatus::Pending]);

        $this->assertSame('', $this->antlers('{{ reviews:schema }}', ['id' => 'book']));
    }

    #[Test]
    public function an_unpublished_entrys_form_takes_no_reviews(): void
    {
        Entry::find('book')->published(false)->save();

        $html = $this->antlers('{{ reviews:form entry="book" }}{{ if can_review }}OPEN{{ else }}{{ reason }}{{ /if }}{{ /reviews:form }}');

        $this->assertStringContainsString('unavailable', $html);
        $this->assertStringNotContainsString('OPEN', $html);
        $this->assertSame('', $this->antlers('{{ if {reviews:can_review entry="book"} }}yes{{ /if }}'));
    }
}
