<?php

namespace NineteenNinetyFour\Reviews\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use NineteenNinetyFour\Reviews\ReviewQuery;
use NineteenNinetyFour\Reviews\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;

class PhotosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('assets');
        AssetContainer::make('assets')->disk('assets')->save();

        config(['reviews.photos.enabled' => true]);
        $this->product();
    }

    /**
     * A JPEG with camera metadata (EXIF) in it, including where it was taken.
     */
    protected function photoWithLocation(string $name = 'holiday.jpg'): UploadedFile
    {
        $image = imagecreatetruecolor(400, 300);
        ob_start();
        imagejpeg($image);
        $jpeg = ob_get_clean();

        $payload = "Exif\0\0".'MM'."\0\x2A\0\0\0\x08".'GPS 51.5007N 0.1246W SECRET-LOCATION';
        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
        $jpeg = substr($jpeg, 0, 2).$segment.substr($jpeg, 2);

        $path = tempnam(sys_get_temp_dir(), 'rv').'.jpg';
        file_put_contents($path, $jpeg);

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
    }

    protected function submitted()
    {
        return $this->reviews()->repository()->get(ReviewQuery::make())->first();
    }

    #[Test]
    public function photos_wait_privately_re_saved_without_their_metadata(): void
    {
        $this->post('/!/reviews/submit', $this->submission(['photos' => [$this->photoWithLocation(), UploadedFile::fake()->image('big.png', 3000, 1500)]]))
            ->assertSessionHasNoErrors();

        $review = $this->submitted();
        $this->assertCount(2, $review->photos);

        foreach ($review->photos as $photo) {
            $this->assertSame('local', $photo['disk']);
            $this->assertStringStartsWith('reviews/pending/'.$review->id.'/', $photo['path']);
            $this->assertStringEndsWith('.jpg', $photo['path']);

            $contents = Storage::disk('local')->get($photo['path']);
            $this->assertStringNotContainsString('SECRET-LOCATION', $contents);
            $this->assertStringNotContainsString('Exif', $contents);
        }

        [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($review->photos[1]['path']));
        $this->assertSame([2000, 1000], [$width, $height]);

        // Nothing is public yet.
        $this->assertSame([], Storage::disk('assets')->allFiles());
    }

    #[Test]
    public function approving_publishes_photos_and_rejecting_takes_them_back(): void
    {
        $this->post('/!/reviews/submit', $this->submission(['photos' => [UploadedFile::fake()->image('a.jpg', 100, 100)]]));
        $review = $this->submitted();
        $pending = $review->photos[0]['path'];

        $this->reviews()->approve($review);

        $review = $this->reviews()->find($review->id);
        $this->assertArrayHasKey('asset', $review->photos[0]);
        $asset = Asset::find($review->photos[0]['asset']);
        $this->assertNotNull($asset);
        $this->assertStringStartsWith('reviews/', $asset->path());
        $this->assertSame('Photo from Sam R.’s review', $asset->get('alt'));
        Storage::disk('local')->assertMissing($pending);

        $html = $this->antlers('{{ reviews }}{{ photos }}<img src="{{ url }}">{{ /photos }}{{ /reviews }}', ['id' => 'book']);
        $this->assertStringContainsString('<img src="'.$asset->url().'">', $html);

        $this->reviews()->reject($review);

        $review = $this->reviews()->find($review->id);
        $this->assertArrayHasKey('disk', $review->photos[0]);
        Storage::disk('local')->assertExists($review->photos[0]['path']);
        $this->assertSame([], Storage::disk('assets')->allFiles('reviews'));
    }

    #[Test]
    public function deleting_a_review_deletes_its_photos(): void
    {
        $this->post('/!/reviews/submit', $this->submission(['photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]]));
        $review = $this->submitted();
        $this->reviews()->approve($review);
        $this->reviews()->hold($review = $this->reviews()->find($review->id));
        $this->reviews()->approve($review = $this->reviews()->find($review->id));

        $this->reviews()->delete($this->reviews()->find($review->id));

        $this->assertSame([], Storage::disk('assets')->allFiles('reviews'));
        $this->assertSame([], Storage::disk('local')->allFiles('reviews/pending'));
    }

    #[Test]
    public function photos_are_ignored_when_turned_off(): void
    {
        config(['reviews.photos.enabled' => false]);

        $this->post('/!/reviews/submit', $this->submission(['photos' => [UploadedFile::fake()->image('a.jpg')]]))->assertSessionHasNoErrors();

        $this->assertSame([], $this->submitted()->photos);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    #[Test]
    public function only_images_within_the_limits_are_taken(): void
    {
        config(['reviews.photos.max' => 2, 'reviews.photos.max_size' => 100]);

        $this->post('/!/reviews/submit', $this->submission(['photos' => [UploadedFile::fake()->create('evil.php', 10, 'image/jpeg')]]))
            ->assertSessionHasErrors('photos.0', null, 'reviews');

        $this->post('/!/reviews/submit', $this->submission(['photos' => [UploadedFile::fake()->image('a.gif')]]))
            ->assertSessionHasErrors('photos.0', null, 'reviews');

        $this->post('/!/reviews/submit', $this->submission(['photos' => [UploadedFile::fake()->image('a.jpg')->size(200)]]))
            ->assertSessionHasErrors('photos.0', null, 'reviews');

        $this->post('/!/reviews/submit', $this->submission(['photos' => array_fill(0, 3, UploadedFile::fake()->image('a.jpg'))]))
            ->assertSessionHasErrors('photos', null, 'reviews');

        $this->assertNull($this->submitted());
    }

    #[Test]
    public function a_reviewer_editing_their_review_can_remove_and_add_photos(): void
    {
        $user = $this->user('sam@example.com', name: 'Sam Reader');
        $this->actingAs($user)->post('/!/reviews/submit', $this->submission(['photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]]));
        $first = $this->submitted()->photos;

        $this->actingAs($user)->post('/!/reviews/submit', $this->submission(['remove_photos' => ['0'], 'photos' => [UploadedFile::fake()->image('c.jpg')]]))
            ->assertSessionHasNoErrors();

        $photos = $this->submitted()->photos;
        $this->assertCount(2, $photos);
        $this->assertSame($first[1], $photos[0]);
        Storage::disk('local')->assertMissing($first[0]['path']);
    }

    #[Test]
    public function editors_see_waiting_photos_and_nobody_else_does(): void
    {
        $this->post('/!/reviews/submit', $this->submission(['photos' => [UploadedFile::fake()->image('a.jpg')]]));
        $review = $this->submitted();
        $url = cp_route('reviews.photo', [$review->id, 0]);

        $this->get($url)->assertRedirect();
        // Statamic sends the Control Panel's unauthorised back to the dashboard.
        $denied = $this->actingAs($this->user('nobody@example.com', ['access cp']))->get($url);
        $this->assertContains($denied->getStatusCode(), [302, 403]);
        $this->assertNotSame('image/jpeg', $denied->headers->get('Content-Type'));

        $this->actingAs($this->editor())->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->actingAs($this->editor())->get(cp_route('reviews.photo', [$review->id, 5]))->assertNotFound();
    }
}
