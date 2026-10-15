<?php

namespace NineteenNinetyFour\Reviews\Stache;

use Illuminate\Support\Facades\Log;
use NineteenNinetyFour\Reviews\Review;
use Statamic\Facades\YAML;
use Statamic\Stache\Stores\BasicStore;
use Statamic\Yaml\ParseException;

/**
 * The file driver's reviews, one YAML file each, named by the review's ID.
 * Indexed by entry, status, user and email, so an entry's reviews are
 * found without reading every file.
 */
class ReviewsStore extends BasicStore
{
    protected $storeIndexes = ['entry', 'status', 'user', 'email', 'order'];

    public function key()
    {
        return 'reviews';
    }

    public function makeItemFromFile($path, $contents)
    {
        try {
            $data = YAML::file($path)->parse($contents);
        } catch (ParseException) {
            Log::warning('Could not parse review file: '.$path);
            $data = [];
        }

        return Review::fromArray(pathinfo($path, PATHINFO_FILENAME), is_array($data) ? $data : [])->initialPath($path);
    }
}
