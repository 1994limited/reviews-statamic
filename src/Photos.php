<?php

namespace NineteenNinetyFour\Reviews;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Contracts\Assets\AssetContainer as ContainerContract;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Throwable;

/**
 * Reviewers' photos. They wait on a private disk while the review is
 * moderated, so nobody can see them before an editor has, and move into
 * an asset container when it's approved (and back out if it's rejected
 * later). Each is re-saved as a JPEG no bigger than it needs to be, which
 * leaves the camera's metadata, location included, behind.
 */
class Photos
{
    public const TYPES = ['jpg', 'jpeg', 'png', 'webp'];

    /** The most pixels a photo may have before it's decoded. */
    public const MAX_PIXELS = 40_000_000;

    public function enabled(): bool
    {
        return Settings::bool('photos_enabled') && extension_loaded('gd');
    }

    public function max(): int
    {
        return Settings::int('photos_max', 4, 1, 10);
    }

    /**
     * The largest photo accepted, in kilobytes.
     */
    public function maxSize(): int
    {
        return max(100, (int) config('reviews.photos.max_size', 8192));
    }

    /**
     * Re-save uploaded photos on the private disk, against the review.
     * Anything that isn't a readable image is left out.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function store(Review $review, array $files): void
    {
        $disk = $this->pendingDisk();
        $directory = trim((string) config('reviews.photos.pending_directory', 'reviews/pending'), '/');

        foreach (array_slice($files, 0, $this->max() - count($review->photos)) as $file) {
            $contents = $this->reencode($file->getRealPath());

            if ($contents === null) {
                continue;
            }

            $path = $directory.'/'.$review->id.'/'.Str::lower(Str::random(16)).'.jpg';
            Storage::disk($disk)->put($path, $contents);

            $review->photos[] = ['disk' => $disk, 'path' => $path];
        }
    }

    /**
     * An image as a JPEG within the size limit, without its metadata. Null
     * if it can't be read as an image.
     */
    public function reencode(string|false $path): ?string
    {
        if (! $path || ! is_file($path)) {
            return null;
        }

        // A small file can unpack into an enormous image ("decompression
        // bomb"): refuse anything over 40 megapixels before decoding it.
        $size = @getimagesize($path);

        if (! is_array($size) || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > self::MAX_PIXELS) {
            return null;
        }

        try {
            $manager = new ImageManager(new Driver);
            $image = method_exists($manager, 'decode') ? $manager->decode($path) : $manager->read($path);

            // Intervention 4 leaves turning the photo the right way up to us.
            if (method_exists($manager, 'decode') && method_exists($image, 'orient')) {
                $image = $image->orient();
            }

            $max = max(200, (int) config('reviews.photos.max_dimension', 2000));
            $image = $image->scaleDown($max, $max);

            return (string) $image->encode(new JpegEncoder(quality: 85));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Move waiting photos into the asset container.
     */
    public function publish(Review $review): void
    {
        if (! $review->hasPendingPhotos()) {
            return;
        }

        $container = $this->container();

        if (! $container) {
            return;
        }

        $folder = trim((string) config('reviews.photos.folder', 'reviews'), '/');

        $review->photos = collect($review->photos)->map(function (array $photo, int $i) use ($container, $folder, $review) {
            if (! isset($photo['disk'])) {
                return $photo;
            }

            try {
                $contents = Storage::disk($photo['disk'])->get($photo['path']);

                if ($contents === null) {
                    return null;
                }

                $path = ltrim($folder.'/'.$review->id.'-'.($i + 1).'-'.Str::lower(Str::random(6)).'.jpg', '/');
                $container->disk()->put($path, $contents);

                $asset = Asset::make()->container($container->handle())->path($path);
                $asset->set('alt', __('reviews::messages.photo_alt', ['name' => Reviews::displayName($review->name)]));
                $asset->save();

                Storage::disk($photo['disk'])->delete($photo['path']);

                return ['asset' => $asset->id()];
            } catch (Throwable $e) {
                report($e);

                return $photo;
            }
        })->filter()->values()->all();
    }

    /**
     * Take published photos back off the site, to the private disk.
     */
    public function unpublish(Review $review): void
    {
        $disk = $this->pendingDisk();
        $directory = trim((string) config('reviews.photos.pending_directory', 'reviews/pending'), '/');

        $review->photos = collect($review->photos)->map(function (array $photo) use ($disk, $directory, $review) {
            if (! isset($photo['asset'])) {
                return $photo;
            }

            $asset = Asset::find($photo['asset']);

            if (! $asset) {
                return null;
            }

            try {
                $path = $directory.'/'.$review->id.'/'.Str::lower(Str::random(16)).'.jpg';
                Storage::disk($disk)->put($path, $asset->contents());
                $asset->delete();

                return ['disk' => $disk, 'path' => $path];
            } catch (Throwable $e) {
                report($e);

                return $photo;
            }
        })->filter()->values()->all();
    }

    /**
     * Remove a review's photos, wherever they are.
     */
    public function delete(Review $review): void
    {
        foreach ($review->photos as $photo) {
            try {
                if (isset($photo['asset'])) {
                    Asset::find($photo['asset'])?->delete();
                } else {
                    Storage::disk($photo['disk'])->delete($photo['path']);
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        $review->photos = [];
    }

    /**
     * The approved photos, as assets.
     *
     * @return array<int, AssetContract>
     */
    public function assets(Review $review): array
    {
        return collect($review->photos)
            ->filter(fn ($photo) => isset($photo['asset']))
            ->map(fn ($photo) => Asset::find($photo['asset']))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * A waiting photo's contents, for the Control Panel.
     */
    public function pendingContents(Review $review, int $index): ?string
    {
        $photo = $review->photos[$index] ?? null;

        if (! is_array($photo) || ! isset($photo['disk'])) {
            return null;
        }

        try {
            return Storage::disk($photo['disk'])->get($photo['path']);
        } catch (Throwable) {
            return null;
        }
    }

    protected function pendingDisk(): string
    {
        return (string) config('reviews.photos.pending_disk', 'local');
    }

    public function container(): ?ContainerContract
    {
        $handle = Settings::string('photos_container', 'assets');

        return $handle ? AssetContainer::findByHandle($handle) : null;
    }
}
