<?php

namespace NineteenNinetyFour\Reviews\Tests\Feature;

use NineteenNinetyFour\Reviews\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Antlers;

/**
 * {{ reviews:schema_data }} in Antlers written in content, as SEO Pro parses
 * its JSON-LD Schema field: allowed by the site's antlers config, with a
 * trailing * as Statamic matches tag patterns.
 */
class SchemaInContentTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('statamic.antlers.allowedContentTags', ['@default', 'reviews:schema_data*']);
    }

    #[Test]
    public function it_works_in_content_once_allowed(): void
    {
        $this->product();
        $this->review(['rating' => 5]);

        $json = (string) Antlers::parse('{ {{ reviews:schema_data format="properties" }} "@type": "Product" }', ['id' => 'book']);

        $this->assertSame(5, json_decode($json, true)['aggregateRating']['ratingValue']);
    }

    #[Test]
    public function the_other_tags_stay_out_of_content(): void
    {
        $this->product();
        $this->review();

        $this->assertSame('', trim((string) Antlers::parse('{{ reviews:count }}', ['id' => 'book'])));
    }
}
