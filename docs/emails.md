# Emails

| Email | To | When | Setting |
| --- | --- | --- | --- |
| New review | The addresses in **Email new reviews to** | A review is written or changed | Off until you add an address. |
| Your review is live | The reviewer | An editor approves it | Off by default. Not sent for reviews published as they're written. |
| We replied | The reviewer | An editor replies to an approved review | On by default. |
| Review request | A customer | [Cargo](cargo.md#review-requests) | Off by default. |

Turn them on and off in **Tools → Addons → Reviews → Settings → Moderation**. Emails are sent with your site's mail settings, from your `MAIL_FROM_ADDRESS`.

They're Laravel notifications, queued: on a site with a queue worker they're sent in the background; without one (`QUEUE_CONNECTION=sync`), straight away. What a reviewer wrote goes in as plain text, so Markdown or links in a review can't change the email.

To change their look, publish Laravel's notification views (`php artisan vendor:publish --tag=laravel-notifications` and `--tag=laravel-mail`). To change their words, publish the addon's translations and edit `lang/vendor/reviews/en/messages.php` (the `mail` keys).

To send your own emails instead, listen for the [events](php-api.md#events) and turn these off.
