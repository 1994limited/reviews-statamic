<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Where reviews are kept: "file" (YAML files, one per review, indexed by
    | the Stache) or "database" (your app's database, with the migrations
    | published by `php artisan vendor:publish --tag=reviews-migrations`).
    | Move between them with `php please reviews:transfer`.
    |
    */

    'driver' => env('REVIEWS_DRIVER', 'file'),

    'drivers' => [
        'file' => [
            'directory' => storage_path('reviews'),
            // Which orders have been sent a review request, and who asked
            // not to be sent any more (with Cargo).
            'requests' => storage_path('reviews-requests.json'),
            // The platforms reviews are synced from, with their keys (secret
            // ones encrypted with your app key).
            'sources' => storage_path('reviews-sources.yaml'),
        ],

        'database' => [
            // Null for the app's default connection.
            'connection' => env('REVIEWS_DB_CONNECTION'),
            'tables' => [
                'reviews' => 'reviews',
                'votes' => 'review_votes',
                'requests' => 'review_requests',
                'sources' => 'review_sources',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | What can be reviewed
    |--------------------------------------------------------------------------
    |
    | The collections whose entries take reviews. Empty for every collection
    | with pages on the site. Also editable in the Control Panel, under
    | Tools → Addons → Reviews → Settings, which wins over this.
    |
    */

    'collections' => [],

    /*
    |--------------------------------------------------------------------------
    | Reviews across sites
    |--------------------------------------------------------------------------
    |
    | On a multi-site install: "shared", where an entry has the same reviews
    | on every site (its translations), or "separate", where each site has
    | its own reviews, average and schema (sites that are their own shops).
    | Also editable in the Control Panel.
    |
    */

    'sites' => 'shared',

    /*
    |--------------------------------------------------------------------------
    | The review form
    |--------------------------------------------------------------------------
    */

    'form' => [
        // Who may write a review: "anyone" (guests give a name and email),
        // or "users" (signed in only).
        'who' => 'anyone',

        // The longest title, review and name accepted, in characters.
        'title_limit' => 120,
        'body_limit' => 5000,
        'body_minimum' => 0,
        'name_limit' => 60,

        // Whether a title is needed, and whether a review needs any text
        // at all (a rating on its own is fine when false).
        'require_title' => false,
        'require_body' => true,

        // Reviews with more links than this are refused.
        'max_links' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Ratings
    |--------------------------------------------------------------------------
    |
    | Every review has an overall rating out of five. Criteria add ratings of
    | their own, such as Quality and Value, each with a handle and a label,
    | and optionally only for some collections. Also editable in the
    | Control Panel.
    |
    */

    'criteria' => [
        // ['handle' => 'quality', 'label' => 'Quality', 'collections' => ['products']],
    ],

    /*
    |--------------------------------------------------------------------------
    | Moderation
    |--------------------------------------------------------------------------
    |
    | "never" holds every review for an editor. "users" publishes reviews by
    | signed-in users straight away, and "verified" those by verified
    | buyers (with Cargo). Reviews are never published or held by their
    | star rating.
    |
    */

    'auto_approve' => 'never',

    /*
    |--------------------------------------------------------------------------
    | Names
    |--------------------------------------------------------------------------
    |
    | How reviewers' names are shown: "full" as given, or "short", the first
    | name and the initial of the last ("Jo B.").
    |
    */

    'name_display' => 'short',

    /*
    |--------------------------------------------------------------------------
    | Photos
    |--------------------------------------------------------------------------
    |
    | Off unless turned on. Photos wait on a private disk until the review is
    | approved, then move into the asset container. They're re-saved as
    | JPEG, which drops the camera's metadata (location included).
    |
    */

    'photos' => [
        'enabled' => false,
        'max' => 4,
        // Kilobytes per photo.
        'max_size' => 8192,
        // The longest side, in pixels, once saved.
        'max_dimension' => 2000,
        // Where photos wait for moderation. A private disk.
        'pending_disk' => 'local',
        'pending_directory' => 'reviews/pending',
        // The asset container approved photos move into, and its folder.
        'container' => 'assets',
        'folder' => 'reviews',
    ],

    /*
    |--------------------------------------------------------------------------
    | Spam
    |--------------------------------------------------------------------------
    */

    'spam' => [
        // A field hidden from people. Anything typed in it is a bot's.
        'honeypot' => 'website',

        // Forms sent sooner than this many seconds after the page loaded
        // are a bot's.
        'minimum_seconds' => 3,

        // A captcha on the form: "turnstile" (Cloudflare), "hcaptcha",
        // "recaptcha" (Google's v2 checkbox), "recaptcha_v3" (scored, with
        // nothing to tick), or the class of your own (implementing
        // NineteenNinetyFour\Reviews\Contracts\Captcha). Off when null,
        // or until both keys are set. Captchas need JavaScript.
        'captcha' => [
            'driver' => env('REVIEWS_CAPTCHA'),
            'site_key' => env('REVIEWS_CAPTCHA_SITE_KEY'),
            'secret_key' => env('REVIEWS_CAPTCHA_SECRET_KEY'),
            // reCAPTCHA v3 only: the lowest score taken as a person.
            'score' => 0.5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limits
    |--------------------------------------------------------------------------
    |
    | Per visitor (IP address, which is kept only in the cache, for the
    | length of the limit).
    |
    */

    'throttle' => [
        // Reviews per ten minutes.
        'submit' => 5,
        // "Helpful" votes per minute.
        'votes' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Listing
    |--------------------------------------------------------------------------
    */

    // Reviews per page in the ready-made views ({{ reviews:display }}).
    'per_page' => 10,

    /*
    |--------------------------------------------------------------------------
    | Review schema (JSON-LD)
    |--------------------------------------------------------------------------
    |
    | The schema.org type of each collection's entries, for
    | {{ reviews:schema }}. Cargo's product collections are "Product"
    | unless set here; anything else not listed is "Thing".
    |
    */

    'schema' => [
        'types' => [
            // 'books' => 'Book',
            // 'courses' => 'Course',
            // 'recipes' => 'Recipe',
        ],

        // How many reviews go in the schema with the average.
        'reviews' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Emails
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        // Sent each new review. Also editable in the Control Panel.
        'to' => [],

        // Emails to the reviewer.
        'approved' => false,
        'replied' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cargo
    |--------------------------------------------------------------------------
    |
    | Only used when Cargo (duncanmcclean/statamic-cargo ^1.15) is installed.
    |
    */

    'cargo' => [
        // Only customers who bought a product may review it.
        'buyers_only' => false,

        // Emails asking customers to review what they bought.
        'requests' => [
            'enabled' => false,
            // Days after the order is placed.
            'delay_days' => 14,
            // The order must have reached this status: "payment_received",
            // "shipped" or "delivered".
            'status' => 'payment_received',
            // Orders older than the delay plus this many days are left
            // alone, so turning requests on doesn't email every past
            // customer.
            'window_days' => 14,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | People and their data
    |--------------------------------------------------------------------------
    |
    | When a user is deleted, their reviews are "anonymised" (kept, with the
    | name and email taken off) or "deleted".
    |
    */

    'deleted_users' => 'anonymise',

    // Run the scheduled jobs (review request emails). Off if you schedule
    // `php please reviews:send-requests` yourself.
    'schedule' => true,

];
