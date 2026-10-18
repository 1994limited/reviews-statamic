<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reviews 1.2: where a review came from (imported or synced from another
 * platform), whether it counts towards the average, and the platforms
 * reviews are synced from. Existing reviews are this site's own, and count.
 */
return new class extends Migration
{
    public function getConnection()
    {
        return config('reviews.drivers.database.connection') ?: parent::getConnection();
    }

    public function up(): void
    {
        $tables = config('reviews.drivers.database.tables', []);

        Schema::table($tables['reviews'] ?? 'reviews', function (Blueprint $table) {
            $table->string('source', 40)->nullable()->index();
            $table->string('source_id', 64)->nullable()->index();
            $table->string('source_url', 500)->nullable();
            $table->boolean('counted')->default(true);
            $table->string('fingerprint', 64)->nullable()->unique();
        });

        Schema::create($tables['sources'] ?? 'review_sources', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->json('data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $tables = config('reviews.drivers.database.tables', []);

        Schema::dropIfExists($tables['sources'] ?? 'review_sources');

        Schema::table($tables['reviews'] ?? 'reviews', function (Blueprint $table) {
            $table->dropUnique(['fingerprint']);
            $table->dropIndex(['source']);
            $table->dropIndex(['source_id']);
            $table->dropColumn(['source', 'source_id', 'source_url', 'counted', 'fingerprint']);
        });
    }
};
