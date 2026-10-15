<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection()
    {
        return config('reviews.drivers.database.connection') ?: parent::getConnection();
    }

    public function up(): void
    {
        $tables = config('reviews.drivers.database.tables', []);
        $reviews = $tables['reviews'] ?? 'reviews';

        Schema::create($reviews, function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('entry_id', 64);
            $table->string('site', 64)->nullable();
            $table->unsignedTinyInteger('rating');
            $table->json('ratings')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('user_id', 64)->nullable()->index();
            $table->string('status', 16);
            $table->boolean('verified')->default(false);
            $table->string('order_id', 64)->nullable()->index();
            $table->text('reply')->nullable();
            $table->string('reply_by', 64)->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->text('note')->nullable();
            $table->json('photos')->nullable();
            $table->boolean('has_photos')->default(false);
            $table->unsignedInteger('helpful')->default(0);
            $table->timestamps();
            $table->timestamp('approved_at')->nullable();

            // An entry's approved reviews, newest first or by rating.
            $table->index(['entry_id', 'status', 'created_at']);
            $table->index(['entry_id', 'status', 'rating']);
            // The moderation queue.
            $table->index(['status', 'created_at']);
        });

        Schema::create($tables['votes'] ?? 'review_votes', function (Blueprint $table) use ($reviews) {
            $table->id();
            $table->string('review_id', 64);
            $table->string('voter', 64);
            $table->timestamp('created_at')->nullable();
            $table->unique(['review_id', 'voter']);
            $table->foreign('review_id')->references('id')->on($reviews)->cascadeOnDelete();
        });

        Schema::create($tables['requests'] ?? 'review_requests', function (Blueprint $table) {
            $table->id();
            // "sent" for an order's request, "opt_out" for an address that
            // asked not to be sent any.
            $table->string('type', 16);
            $table->string('order_id', 64)->nullable()->unique();
            $table->string('email_hash', 64)->nullable()->index();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        $tables = config('reviews.drivers.database.tables', []);

        Schema::dropIfExists($tables['requests'] ?? 'review_requests');
        Schema::dropIfExists($tables['votes'] ?? 'review_votes');
        Schema::dropIfExists($tables['reviews'] ?? 'reviews');
    }
};
