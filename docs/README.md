# Reviews documentation

Reviews collects reviews and star ratings on your own site. Visitors write them with a form on any entry; editors approve, reject and reply in the Control Panel; your templates show the reviews, the average rating and review schema for search engines. With Cargo, reviews by customers who bought the product say so. Every example has an Antlers and a Blade version.

## Setting up

1. [Installation](installation.md): requirements, installing, licensing, updating.
2. [Settings and configuration](configuration.md): the Control Panel settings, and every option in `config/reviews.php`.
3. [Storage drivers](drivers.md): files or the database, and moving between them.

## Building the front end

4. [Ready-made reviews](ready-made.md): a finished reviews section and default styles in two tags, and changing how it looks with CSS variables. The quickest start.
5. [Templates](templates.md): the average, the reviews, the form and review schema in your own markup.
6. [Review schema and SEO addons](seo.md): star ratings in search results, and joining Reviews' schema to an SEO addon's, with steps for SEO Pro.
7. [Tags](tags.md): every `{{ reviews:* }}` tag, its parameters and variables.
8. [Spam](spam.md): the honeypot, the time trap, captchas (Turnstile, hCaptcha, reCAPTCHA) and rate limits.
9. [Photos](photos.md): letting reviewers add photos, and how they're kept private until approved.
10. [Static caching](static-caching.md): what to wrap in `{{ nocache }}`, and what clears itself.
11. [The demo shop](demo.md): Dog-ear Books, a whole site built with Reviews, to run or copy from.
12. [Cargo](cargo.md): verified purchases, reviews from buyers only, and emails asking customers for a review.

## Running a site

13. [Moderation](moderation.md): the Control Panel, approving, replying, permissions, the dashboard widget and the entry field.
14. [Emails](emails.md): to editors about new reviews, and to reviewers.
15. [Privacy](privacy.md): what's kept, exporting and forgetting someone's reviews, and fair-review law.
16. [Import and sync](import-and-sync.md): reviews from Yotpo, Judge.me, REVIEWS.io, Shopify, Trustpilot, WooCommerce and Feefo, once or on a schedule.

## Reference

17. [Events and PHP API](php-api.md): events, the `Reviews` service, and querying reviews.
18. [Troubleshooting](troubleshooting.md): common problems and fixes.

## Requirements

- PHP 8.3 or later, with the GD extension for photos.
- Statamic 6.35 or later. Front-end user accounts and Control Panel permissions need Statamic Pro; guests can review without it.
- For the database driver: any database Laravel supports.
- For the Cargo features: Cargo 1.15 or later (before 2.0).
