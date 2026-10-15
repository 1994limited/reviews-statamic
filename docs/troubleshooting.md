# Troubleshooting

**The form says "This can't be reviewed."** The entry isn't in a collection chosen in the settings, or it isn't published (drafts, scheduled and expired entries can't be reviewed), or its collection has no pages.

**A review I sent doesn't appear.** It's waiting in **Content → Reviews → Pending**. Reviews are checked before they appear unless the settings say otherwise.

**I approved a review and the page doesn't show it.** With static caching, Reviews clears the entry's page, but a page *listing* entries with their ratings needs an [invalidation rule](https://statamic.dev/static-caching#invalidation). See [Static caching](static-caching.md).

**Everyone sees someone else's thank-you message, or their typing.** The form is on a statically cached page without `{{ nocache }}`. See [Static caching](static-caching.md).

**Reviews sent from my phone vanish without an error.** Something filled in the honeypot field: usually a browser or password manager autofilling a field called `website`. Rename it with `spam.honeypot` in the config.

**"Please confirm you're not a robot" every time.** Check the captcha's keys are for this domain, and the page's Content Security Policy allows the provider's script. With reCAPTCHA v3, the visitor's score may be under the threshold (`spam.captcha.score`).

**"Too many requests".** The rate limit: five reviews per visitor per ten minutes. Behind a proxy or load balancer, make sure Laravel [trusts it](https://laravel.com/docs/requests#configuring-trusted-proxies), or everyone shares one IP address.

**Photos aren't accepted.** Photos are off by default; turn them on in the settings. PHP's GD extension is needed, and your server's `upload_max_filesize` and `post_max_size` must allow them.

**Review request emails aren't sent.** They're off by default (Settings → Cargo), need Laravel's scheduler running, and only go to orders placed between the delay and two weeks after it. `php please reviews:send-requests --dry-run` says who would be emailed.

**`array_flip(): Can only flip string and integer values` from the Stache.** Clear the cache (`php artisan cache:clear`), then `php please stache:warm`.

**The transfer command says the tables don't exist.** Publish and run the migration first: `php please vendor:publish --tag=reviews-migrations`, then `php artisan migrate`.
