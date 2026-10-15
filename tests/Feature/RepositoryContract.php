<?php

namespace NineteenNinetyFour\Reviews\Tests\Feature;

use NineteenNinetyFour\Reviews\Contracts\ReviewRepository;
use NineteenNinetyFour\Reviews\Review;
use NineteenNinetyFour\Reviews\ReviewQuery;
use NineteenNinetyFour\Reviews\ReviewStatus;
use PHPUnit\Framework\Attributes\Test;

/**
 * What every driver must do the same way. Used by the file and database
 * driver tests.
 */
trait RepositoryContract
{
    protected function repository(): ReviewRepository
    {
        return app(ReviewRepository::class);
    }

    #[Test]
    public function it_saves_finds_and_deletes_a_review(): void
    {
        $review = $this->review(['ratings' => ['quality' => 4], 'photos' => [['asset' => 'assets::reviews/a.jpg']]]);

        $found = $this->repository()->find($review->id);

        $this->assertNotNull($found);
        $this->assertSame('book', $found->entry);
        $this->assertSame(5, $found->rating);
        $this->assertSame(['quality' => 4], $found->ratings);
        $this->assertSame('Lovely', $found->title);
        $this->assertSame('A very good read.', $found->body);
        $this->assertSame('Jo Bloggs', $found->name);
        $this->assertSame('jo@example.com', $found->email);
        $this->assertSame(ReviewStatus::Approved, $found->status);
        $this->assertSame([['asset' => 'assets::reviews/a.jpg']], $found->photos);
        $this->assertNotNull($found->createdAt);

        $this->repository()->delete($found);

        $this->assertNull($this->repository()->find($review->id));
    }

    #[Test]
    public function it_returns_null_for_an_unknown_or_malformed_id(): void
    {
        $this->assertNull($this->repository()->find('nope'));
        $this->assertNull($this->repository()->find('../../etc/passwd'));
    }

    #[Test]
    public function it_filters_by_entry_status_rating_and_more(): void
    {
        $this->review(['entry' => 'book', 'rating' => 5, 'verified' => true]);
        $this->review(['entry' => 'book', 'rating' => 2, 'status' => ReviewStatus::Pending]);
        $this->review(['entry' => 'book', 'rating' => 4, 'photos' => [['asset' => 'assets::a.jpg']]]);
        $this->review(['entry' => 'other', 'rating' => 1, 'user' => 'sam', 'email' => 'SAM@example.com']);

        $count = fn (ReviewQuery $query) => $this->repository()->count($query);

        $this->assertSame(3, $count(ReviewQuery::make()->entry('book')));
        $this->assertSame(2, $count(ReviewQuery::make()->entry('book')->approved()));
        $this->assertSame(1, $count(ReviewQuery::make()->status('pending')));
        $this->assertSame(2, $count(ReviewQuery::make()->rating([4, 5])));
        $this->assertSame(1, $count(ReviewQuery::make()->verified()));
        $this->assertSame(1, $count(ReviewQuery::make()->withPhotos()));
        $this->assertSame(1, $count(ReviewQuery::make()->user('sam')));
        $this->assertSame(1, $count(ReviewQuery::make()->email('sam@EXAMPLE.com')));
        $this->assertSame(4, $count(ReviewQuery::make()));
    }

    #[Test]
    public function it_searches_title_text_name_and_email_literally(): void
    {
        $this->review(['title' => 'Great value', 'body' => 'Fine.']);
        $this->review(['title' => 'Meh', 'body' => '100% wonderful', 'name' => 'Ana Lee']);

        $this->assertSame(1, $this->repository()->count(ReviewQuery::make()->search('VALUE')));
        $this->assertSame(1, $this->repository()->count(ReviewQuery::make()->search('100%')));
        $this->assertSame(0, $this->repository()->count(ReviewQuery::make()->search('%_')));
        $this->assertSame(1, $this->repository()->count(ReviewQuery::make()->search('ana')));
    }

    #[Test]
    public function it_sorts(): void
    {
        $a = $this->review(['rating' => 3, 'created_at' => '2026-01-01', 'helpful' => 1]);
        $b = $this->review(['rating' => 5, 'created_at' => '2026-03-01']);
        $c = $this->review(['rating' => 1, 'created_at' => '2026-02-01', 'helpful' => 7]);

        $ids = fn (string $sort) => $this->repository()->get(ReviewQuery::make()->sort($sort))->map->id->all();

        $this->assertSame([$b->id, $c->id, $a->id], $ids('newest'));
        $this->assertSame([$a->id, $c->id, $b->id], $ids('oldest'));
        $this->assertSame([$b->id, $a->id, $c->id], $ids('highest'));
        $this->assertSame([$c->id, $a->id, $b->id], $ids('lowest'));
        $this->assertSame([$c->id, $a->id, $b->id], $ids('helpful'));
        $this->assertSame([$b->id, $c->id, $a->id], $ids('nonsense'));
    }

    #[Test]
    public function it_paginates(): void
    {
        foreach (range(1, 5) as $i) {
            $this->review(['created_at' => "2026-01-0{$i}"]);
        }

        $page = $this->repository()->paginate(ReviewQuery::make(), 2, 3);

        $this->assertSame(5, $page->total());
        $this->assertSame(3, $page->lastPage());
        $this->assertCount(1, $page->items());
    }

    #[Test]
    public function it_tallies_approved_ratings_for_an_entry(): void
    {
        $this->review(['rating' => 5, 'ratings' => ['value' => 4]]);
        $this->review(['rating' => 5, 'ratings' => ['value' => 2, 'quality' => 5]]);
        $this->review(['rating' => 3]);
        $this->review(['rating' => 1, 'status' => ReviewStatus::Pending, 'ratings' => ['value' => 1]]);
        $this->review(['rating' => 1, 'status' => ReviewStatus::Spam]);
        $this->review(['rating' => 1, 'entry' => 'other']);
        $this->review(['rating' => 4, 'site' => 'french']);

        $tally = $this->repository()->tally('book');

        $this->assertSame([5 => 2, 4 => 1, 3 => 1, 2 => 0, 1 => 0], $tally['stars']);
        $this->assertEquals(['value' => ['sum' => 6, 'count' => 2], 'quality' => ['sum' => 5, 'count' => 1]], $tally['criteria']);
        $this->assertSame([5 => 2, 4 => 0, 3 => 1, 2 => 0, 1 => 0], $this->repository()->tally('book', 'default')['stars']);
    }

    #[Test]
    public function it_counts_reviews_in_each_status(): void
    {
        $this->review();
        $this->review(['status' => ReviewStatus::Pending]);
        $this->review(['status' => ReviewStatus::Pending]);
        $this->review(['status' => ReviewStatus::Spam]);

        $this->assertSame(['pending' => 2, 'approved' => 1, 'rejected' => 0, 'spam' => 1], $this->repository()->statusCounts());
    }

    #[Test]
    public function a_voter_counts_once(): void
    {
        $review = $this->review();

        $this->assertTrue($this->repository()->vote($review, 'voter-a'));
        $this->assertFalse($this->repository()->vote($review, 'voter-a'));
        $this->assertTrue($this->repository()->vote($review, 'voter-b'));

        $this->assertSame(2, $review->helpful);
        $this->assertSame(2, $this->repository()->find($review->id)->helpful);
    }

    #[Test]
    public function all_includes_voters(): void
    {
        $review = $this->review();
        $this->repository()->vote($review, 'voter-a');

        $all = $this->repository()->all()->all();

        $this->assertCount(1, $all);
        $this->assertSame(['voter-a'], $all[0]->voters);
        $this->assertInstanceOf(Review::class, $all[0]);
    }
}
