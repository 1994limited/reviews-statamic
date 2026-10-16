<?php

namespace NineteenNinetyFour\Reviews\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use NineteenNinetyFour\Reviews\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The database driver on a connection of its own (REVIEWS_DB_CONNECTION).
 */
class DatabaseConnectionTest extends TestCase
{
    protected string $driver = 'database';

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('database.connections.reviews', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('reviews.drivers.database.connection', 'reviews');
    }

    #[Test]
    public function the_tables_are_made_and_used_on_the_configured_connection(): void
    {
        foreach (['reviews', 'review_votes', 'review_requests'] as $table) {
            $this->assertTrue(Schema::connection('reviews')->hasTable($table), "No [{$table}] table on the reviews connection.");
            $this->assertFalse(Schema::connection('sqlite')->hasTable($table), "[{$table}] was made on the default connection.");
        }

        $this->product();
        $review = $this->review();
        $this->assertSame($review->id, $this->reviews()->find($review->id)?->id);
    }
}
