# Import and sync

Bring reviews from another platform: once, from a file it exported (moving to Reviews, say), or on a schedule, from its API (keeping a platform you still use in step).

## Importing a file

Under **Reviews → Import**, upload a CSV or JSON file. Reviews reads:

| Platform | Its export | Matched to entries by |
| --- | --- | --- |
| Reviews (Statamic or Craft) | `php please reviews:export-all`, or Craft's `php craft reviews/export` | ID, or exact title |
| Yotpo | Moderation → Export | SKU, handle or page address |
| Judge.me | Its export, or its import template | Shopify handle |
| REVIEWS.io | Its export, or its import template | SKU |
| Shopify Product Reviews | Its export | Shopify handle |
| Trustpilot | Business → Reviews → Export | SKU, if given |
| WooCommerce | A review export plugin, or WP All Export | SKU, page address or post ID |
| Anything else | A CSV or JSON file with a rating column | Whatever product column it has |

The format is worked out from the columns. Reviews are matched to entries in your reviewed collections by ID, by SKU (an entry's `sku` field, if your products have one), by slug (a platform's "handle"), by page address, or by exact title.

**Preview** shows how many would be added, how many are here already, and which matched nothing, before anything is added. Then **Import**. A review is never added twice: importing the same file again adds nothing.

- **Count them as this site's own reviews.** For reviews your customers wrote for you on a platform you're leaving. They then count towards ratings and go in your schema. Leave it off for reviews gathered elsewhere.
- **Status.** Approved, if the platform showed them, or waiting for an editor.
- **Include reviews the platform wasn't showing.** Added as rejected, so they're on record.
- **Reviews of the business go on.** Company reviews aren't of a product: give the ID of the entry they belong to (a reviews page, say), or they're left out.

Give users **Import and sync reviews** (under Reviews in their permissions) to use it.

For files over 20 MB, or from a script:

```bash
php please reviews:import exports/yotpo.csv --dry-run
php please reviews:import exports/yotpo.csv --counted --business=reviews-page
```

`--format`, `--counted`, `--pending`, `--include-unpublished`, `--business` and `--site` are the same options. `--dry-run` says what would be added without adding anything.

### Moving between sites

`php please reviews:export-all reviews.csv` writes every review as a CSV that Reviews (for Statamic or Craft) imports.

## Syncing

Under **Reviews → Sync**, add a source:

| Platform | Settings | |
| --- | --- | --- |
| Feefo | Merchant identifier | No key needed. A review rating the service and products becomes one review each. |
| REVIEWS.io | Store ID, API key (optional) | Company and product reviews. |
| Judge.me | Shop domain, private API token | |
| Yotpo | App key, secret key | Only reviews changed since the last sync are fetched. |
| Trustpilot | Business unit ID, API key, profile address, SKUs | Needs Trustpilot's paid API module. |

Sources are kept beside your reviews (`storage/reviews-sources.yaml`, or the `review_sources` table with the database driver), not in the addon's settings, which are often committed to git. Secret keys are encrypted with your app key. Or give an environment variable instead (`$JUDGEME_TOKEN`), which is read when the source syncs.

Each source syncs every 24 hours (**Hours between syncs**). `reviews:sync` runs hourly from Laravel's scheduler, so make sure it runs:

```
* * * * * cd /path/to/site && php artisan schedule:run >> /dev/null 2>&1
```

Or sync from the command line:

```bash
php please reviews:sync              # every source that's due
php please reviews:sync --force      # every enabled source, now
php please reviews:sync {id} --all   # one source, everything again
```

A sync adds new reviews and follows what the platform changes (a reply, a name, a verified badge, photos). When the platform takes a review down, so does the sync; when an editor here rejects one, the sync leaves it rejected. A sync that fails says why on the Sync page, and is tried again next time.

### Not synced: Google

Google's terms don't allow keeping its reviews: the Business Profile API only for 30 days, and the Places API not at all. Show Google reviews with Google's own widget.

## Third-party reviews and schema

Google's guidelines say not to mark up reviews gathered from other websites. So imported and synced reviews:

- show where they came from ("From Trustpilot", with a link to the review there), in the ready-made views and as `source` and `source_url` in [Tags](tags.md);
- get no "helpful" votes;
- don't count towards the average or go in your schema, unless you count an import as your own, or a source is from a platform that allows it (only Feefo does: **Count towards ratings and schema** on its source).

Trustpilot asks that its reviews are shown with its logo and a link to your profile there: add those to your template beside them.
