<?php

namespace NineteenNinetyFour\Reviews\Tests\Feature;

use NineteenNinetyFour\Reviews\ReviewQuery;
use NineteenNinetyFour\Reviews\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SmokeTest extends TestCase
{
    #[Test]
    public function it_boots_and_submits_a_review(): void
    {
        $this->product();

        $this->post('/!/reviews/submit', $this->submission())->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, $this->reviews()->repository()->count(ReviewQuery::make()));
    }
}
