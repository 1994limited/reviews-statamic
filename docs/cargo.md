# Cargo

In a shop built with [Cargo](https://builtwithcargo.dev) (1.15 or later, before 2.0), Reviews knows who bought what. Nothing needs setting up: it switches on when Cargo is installed, and off again (rather than failing) with a version it wasn't built for.

## Verified purchases

A review is a **verified purchase** when the reviewer bought the product: a signed-in customer with a paid order for it (paid, shipped or delivered; not cancelled or returned), or a customer who came from a [review request](#review-requests) email. Variants count as their product: a review of a book bought in paperback is verified.

Show it with `verified`:

```antlers
{{ if verified }}<span>✓ Verified purchase</span>{{ /if }}
```

<details>
<summary>Blade</summary>

```blade
@if ($review['verified'])<span>✓ Verified purchase</span>@endif
```

</details>

`{{ reviews verified="true" }}` lists only verified reviews. In the Control Panel, verified reviews say so, with a link to the order. Verification is checked when the review is written, and kept even if the order is refunded later.

Publish verified reviews without checking with **Publish without checking → Reviews by verified buyers** in the settings.

## Reviews from buyers only

Turn on **Only buyers can review products** in the settings, and only customers who bought a product can review it. The form's `reason` is `buyers_only` for everyone else. Entries that aren't products (pages, blog posts) are still open to anyone.

## Review requests

An email asking customers to review what they bought, a while after their order. Turn it on in **Settings → Cargo**, and choose:

- **Days after the order** (14), and **once the order is** paid, shipped or delivered (paid). A book ordered on the 1st and shipped on the 3rd is asked about on the 15th with "shipped".
- The **subject** and **message**, if you'd like your own words.

Each order is asked once, about the products on it that the customer hasn't already reviewed, with a button for each. The buttons are signed links that open the product's page with the customer's name and email filled in, and mark their review as a verified purchase even if they check out as a guest. They work for 90 days.

Every email has a link to stop review requests. It opens a page with a button, so a mail scanner following the link doesn't unsubscribe anyone. Addresses that opt out are kept only as a hash.

Requests are sent by `php please reviews:send-requests`, which Reviews schedules hourly: your site needs [Laravel's scheduler](https://laravel.com/docs/scheduling#running-the-scheduler) running, as most do. Check who would be emailed first with:

```bash
php please reviews:send-requests --dry-run
```

Orders older than the delay plus 14 days (`cargo.requests.window_days`) are left alone, so turning requests on doesn't email every customer you've ever had.

Review requests are marketing to some people's minds. In the UK and EU you can usually email your own customers about what they bought, with a way to opt out (which every email has); check what applies to you.
