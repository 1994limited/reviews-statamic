# Storage drivers

Reviews are kept by a **driver**: `file` (the default) or `database`. Templates, tags and the Control Panel work the same with either. Set it in `.env`:

```env
REVIEWS_DRIVER=database
```

## Files

Each review is a YAML file in `storage/reviews`, named by its ID, indexed by Statamic's Stache as entries are, so an entry's reviews are found without reading every file. `php please stache:warm` and `stache:clear` include them.

Reviews are written by visitors, so they're kept in `storage`, not `content`: they don't belong in Git. Back up `storage/reviews` with the rest of your site's data. Change the folder with `drivers.file.directory` in the config.

The file driver suits sites with up to a few thousand reviews. Past that, use the database.

## Database

Four tables: `reviews`, `review_votes` ("helpful" votes), `review_requests` (Cargo's review request emails) and `review_sources` ([sync sources](import-and-sync.md#syncing)). Publish the migrations and run them:

```bash
php artisan vendor:publish --tag=reviews-migrations
php artisan migrate
```

Once `REVIEWS_DRIVER=database`, the migration also runs with the site's own (`php artisan migrate`), so publishing it is only needed to edit it, or to run it before switching. Use another connection with `REVIEWS_DB_CONNECTION`, and rename the tables with `drivers.database.tables` in the config.

The tables are indexed for an entry's reviews by date and rating, and for the moderation queue, so listings and averages stay quick with many thousands of reviews.

## Moving between them

```bash
php please reviews:transfer --from=file --to=database
```

Every review is copied with its ID, dates, photos and "helpful" votes. Run the migration first; the command says so if you haven't. Then set `REVIEWS_DRIVER` to the new driver. `--force` skips the question. The reviews in the old driver are left as they were.

Review request records (which orders have been asked, and who opted out) aren't copied. Customers whose orders were already asked won't be asked again if they're outside the request window ([Cargo](cargo.md)); turn requests off for a few days after switching if you'd rather be sure.
