<?php

namespace NineteenNinetyFour\Reviews\Tests\Feature;

use Inertia\Testing\AssertableInertia;
use NineteenNinetyFour\Reviews\Import\ImportOptions;
use NineteenNinetyFour\Reviews\Import\Imports;
use NineteenNinetyFour\Reviews\Review;
use NineteenNinetyFour\Reviews\ReviewQuery;
use NineteenNinetyFour\Reviews\ReviewStatus;
use NineteenNinetyFour\Reviews\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Entry;

class ImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->product();
        Entry::make()->collection('pages')->id('about')->slug('about')->data(['title' => 'What customers say'])->save();
    }

    protected function imports(): Imports
    {
        return app(Imports::class);
    }

    /**
     * @return array<int, Review>
     */
    protected function all(): array
    {
        return $this->reviews()->repository()->get(ReviewQuery::make()->sort('oldest'))->all();
    }

    protected function yotpo(string ...$rows): string
    {
        return "Review ID,Review Creation Date,Review Status,Review Score,Review Title,Review Content,Reviewer Display Name,Reviewer Email,Reviewer Type,Product Handle,Product SKU,Comment Content\n".implode("\n", $rows)."\n";
    }

    #[Test]
    public function a_yotpo_export_is_matched_by_slug_and_added_once(): void
    {
        $file = $this->yotpo(
            '101,2026-03-01,Published,5,Wonderful,Couldn’t put it down,Ann Lee,ann@example.com,verified_buyer,the-long-field,LF-1,Thank you Ann',
            '102,2026-03-02,Published,4,Good,Solid,Ben,,anonymous_user,no-such-book,NO-1,',
            '103,2026-03-03,Deleted,1,Spam,Buy pills,Bot,,anonymous_user,the-long-field,LF-1,',
        );

        $result = $this->imports()->read($file);
        $this->assertSame('yotpo', $result->format?->key());

        $preview = $this->imports()->preview($result->reviews, new ImportOptions);
        $this->assertSame(1, $preview['matched']);
        $this->assertSame(1, $preview['unpublished']);
        $this->assertSame(['sku NO-1, handle no-such-book'], $preview['unmatched']);
        $this->assertSame([], $this->all());

        $report = $this->imports()->import($result->reviews, new ImportOptions);
        $this->assertSame(1, $report->added);

        $review = $this->all()[0];
        $this->assertSame('book', $review->entry);
        $this->assertSame('yotpo', $review->source);
        $this->assertSame('Thank you Ann', $review->reply);
        $this->assertTrue($review->verified);
        $this->assertFalse($review->counted);
        $this->assertSame(ReviewStatus::Approved, $review->status);
        $this->assertSame('2026-03-01', $review->createdAt->toDateString());

        // Not counted: the average leaves it out.
        $this->assertSame(0, $this->reviews()->summary('book')->count());

        // The same file again adds nothing.
        $this->assertSame(0, $this->imports()->import($this->imports()->read($file)->reviews, new ImportOptions)->added);
        $this->assertCount(1, $this->all());
    }

    #[Test]
    public function reviews_counted_as_your_own_count(): void
    {
        $this->imports()->import($this->imports()->read($this->yotpo('201,2026-03-01,Published,4,Fine,Fine,Cy,,verified_buyer,the-long-field,,'))->reviews, new ImportOptions(counted: true));

        $this->assertSame(1, $this->reviews()->summary('book')->count());
    }

    #[Test]
    public function reviews_of_the_business_go_where_they_re_told_or_nowhere(): void
    {
        $file = $this->yotpo('301,2026-03-01,Published,5,Great shop,Fast,Di,,verified_buyer,,yotpo_site_reviews,');

        $this->assertCount(1, $this->imports()->import($this->imports()->read($file)->reviews, new ImportOptions)->unmatched);
        $this->assertSame(1, $this->imports()->import($this->imports()->read($file)->reviews, new ImportOptions(businessEntry: 'about'))->added);
        $this->assertSame('about', $this->all()[0]->entry);
    }

    #[Test]
    public function the_export_imports_into_another_site(): void
    {
        $this->review(['rating' => 4, 'title' => 'There and back', 'email' => 'trip@example.com']);
        $file = sys_get_temp_dir().'/reviews-export-'.uniqid().'.csv';

        $this->artisan('reviews:export-all', ['file' => $file])->assertSuccessful();
        $csv = (string) file_get_contents($file);

        $result = $this->imports()->read($csv);
        $this->assertSame('reviews', $result->format?->key());
        $this->assertSame(['id' => 'book', 'name' => 'The Long Field'], $result->reviews[0]->product);

        // On a fresh site, by the entry's ID.
        foreach ($this->all() as $review) {
            $this->reviews()->repository()->delete($review);
        }

        $this->artisan('reviews:import', ['file' => $file, '--counted' => true])->assertSuccessful();
        $this->assertSame('There and back', $this->all()[0]->title);
        $this->assertSame(1, $this->reviews()->summary('book')->count());
    }

    #[Test]
    public function imported_reviews_show_where_they_came_from_and_get_no_votes(): void
    {
        Entry::find('book')->set('sku', 'LF-1')->save();

        $this->imports()->import($this->imports()->read("Review Id,Review Created (UTC),Review Stars,Review Title,Review Content,Consumer Name,Review URL,Product SKU\ntp1,2026-02-01,4,Nice,Fine,Ivy,https://uk.trustpilot.com/reviews/9,LF-1\n")->reviews, new ImportOptions);
        $review = $this->all()[0];

        $html = $this->antlers('{{ reviews:display_list }}', ['id' => 'book']);
        $this->assertStringContainsString('From Trustpilot', $html);
        $this->assertStringContainsString('https://uk.trustpilot.com/reviews/9', $html);
        $this->assertStringNotContainsString('reviews/helpful', $html);

        $this->postJson('/!/reviews/'.$review->id.'/helpful')->assertStatus(422);
    }

    #[Test]
    public function the_control_panel_shows_where_it_came_from_and_its_photos(): void
    {
        Entry::find('book')->set('sku', 'LF-1')->save();
        $this->imports()->import($this->imports()->read("Review Id,Review Created (UTC),Review Stars,Review Title,Review Content,Consumer Name,Review URL,Product SKU\ntp1,2026-02-01,4,Nice,Fine,Ivy,https://uk.trustpilot.com/reviews/9,LF-1\n")->reviews, new ImportOptions);
        $review = $this->all()[0];
        $review->photos = [['url' => 'https://images.example/p.jpg']];
        $this->reviews()->repository()->save($review);

        $this->actingAs($this->editor())
            ->get(cp_route('reviews.show', $review->id))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('review.source', 'Trustpilot')
                ->where('review.source_url', 'https://uk.trustpilot.com/reviews/9')
                ->where('review.counted', false)
                ->where('review.photos.0.url', 'https://images.example/p.jpg')
                ->etc());
    }

    #[Test]
    public function schema_never_lists_reviews_that_don_t_count(): void
    {
        $this->review(['rating' => 5, 'title' => 'Ours', 'created_at' => now()->subDays(10)->toIso8601String()]);

        foreach (range(1, 6) as $n) {
            $this->review(['rating' => 1, 'title' => 'Elsewhere '.$n, 'source' => 'trustpilot', 'counted' => false]);
        }

        $data = app(\NineteenNinetyFour\Reviews\Schema::class)->data(Entry::find('book'));

        $this->assertSame(1, $data['aggregateRating']['reviewCount']);
        $this->assertSame(['Ours'], array_column($data['review'], 'name'));
    }

    #[Test]
    public function an_entry_id_outside_the_reviewed_collections_matches_nothing(): void
    {
        config(['reviews.collections' => ['products']]);
        $csv = "id,entry,entry_title,rating,title,body,name,email,status,verified,created_at\n"
            ."r1,about,About,5,Hi,Fine,Ann,,approved,0,2026-01-01\n";

        $report = $this->imports()->import($this->imports()->read($csv)->reviews, new ImportOptions);

        $this->assertSame(0, $report->added);
        $this->assertSame([], $this->all());
    }
}
