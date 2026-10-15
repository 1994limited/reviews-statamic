<?php

namespace NineteenNinetyFour\Reviews\Tests\Feature;

use NineteenNinetyFour\Reviews\Http\Controllers\ActionsController;
use NineteenNinetyFour\Reviews\ReviewStatus;
use NineteenNinetyFour\Reviews\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class VotesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->product();
    }

    #[Test]
    public function a_guest_votes_once_with_a_cookie(): void
    {
        $review = $this->review();

        $first = $this->from('/products/the-long-field')->post("/!/reviews/{$review->id}/helpful")
            ->assertRedirect('/products/the-long-field')
            ->assertSessionHas('reviews_success', 'Thanks for letting us know.')
            ->assertCookie(ActionsController::VOTER_COOKIE);

        $token = $first->getCookie(ActionsController::VOTER_COOKIE)->getValue();

        $this->withCookie(ActionsController::VOTER_COOKIE, $token)
            ->post("/!/reviews/{$review->id}/helpful")
            ->assertSessionHas('reviews_success', 'You’ve already said this was helpful.');

        $this->assertSame(1, $this->reviews()->find($review->id)->helpful);

        // Another guest.
        $this->withCookie(ActionsController::VOTER_COOKIE, str_repeat('b', 32))->post("/!/reviews/{$review->id}/helpful");
        $this->assertSame(2, $this->reviews()->find($review->id)->helpful);
    }

    #[Test]
    public function a_user_votes_once(): void
    {
        $review = $this->review();
        $this->actingAs($this->user());

        $this->postJson("/!/reviews/{$review->id}/helpful")->assertOk()->assertJsonPath('helpful', 1);
        $this->postJson("/!/reviews/{$review->id}/helpful")->assertOk()->assertJsonPath('helpful', 1);
    }

    #[Test]
    public function only_approved_reviews_take_votes(): void
    {
        $review = $this->review(['status' => ReviewStatus::Pending]);

        $this->postJson("/!/reviews/{$review->id}/helpful")->assertStatus(422);
        $this->postJson('/!/reviews/missing/helpful')->assertStatus(422);
        $this->post('/!/reviews/..%2F..%2Fx/helpful')->assertNotFound();

        $this->assertSame(0, $this->reviews()->find($review->id)->helpful);
    }

    #[Test]
    public function votes_are_rate_limited(): void
    {
        config(['reviews.throttle.votes' => 2]);
        $review = $this->review();

        $this->post("/!/reviews/{$review->id}/helpful")->assertRedirect();
        $this->post("/!/reviews/{$review->id}/helpful")->assertRedirect();
        $this->post("/!/reviews/{$review->id}/helpful")->assertStatus(429);
    }

    #[Test]
    public function votes_dont_change_the_average(): void
    {
        $review = $this->review(['rating' => 2]);
        $this->reviews()->summary('book');

        $this->post("/!/reviews/{$review->id}/helpful");

        $this->assertEquals(2, $this->reviews()->summary('book')->average());
    }
}
