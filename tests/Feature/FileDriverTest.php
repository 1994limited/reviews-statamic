<?php

namespace NineteenNinetyFour\Reviews\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use NineteenNinetyFour\Reviews\ReviewQuery;
use NineteenNinetyFour\Reviews\Stache\ReviewsStore;
use NineteenNinetyFour\Reviews\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Stache;

/**
 * The file driver on disk, as a site has it: files written, and the Stache
 * watching them for changes made by hand.
 */
class FileDriverTest extends TestCase
{
    protected string $directory = __DIR__.'/../__fixtures__/dev-null/reviews-on-disk';

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('reviews.drivers.file.directory', $this->directory);
        $app['config']->set('statamic.stache.watcher', true);
    }

    protected function setUp(): void
    {
        (new Filesystem)->deleteDirectory($this->directory);

        parent::setUp();

        // Write for real.
        Stache::store('reviews')->directory($this->directory);
        $this->product();
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->directory);

        parent::tearDown();
    }

    #[Test]
    public function every_review_has_a_path_in_the_stache(): void
    {
        $review = $this->review();

        // The Stache compares this index with the files on disk on every
        // request, to notice reviews changed by hand.
        $this->assertSame([$review->id => $review->path()], Stache::store('reviews')->index('path')->items()->all());
        $this->assertFileExists($review->path());
    }

    #[Test]
    public function a_second_request_reads_reviews_from_disk(): void
    {
        $review = $this->review(['title' => 'Kept']);
        $this->reviews()->repository()->count(ReviewQuery::make());

        // As a new request would: nothing in memory, only the cache.
        $store = app(ReviewsStore::class)->directory($this->directory);
        Stache::registerStore($store);

        $this->assertSame('Kept', $this->reviews()->repository()->find($review->id)?->title);
        $this->assertSame(1, $this->reviews()->repository()->count(ReviewQuery::make()->entry('book')));
    }
}
