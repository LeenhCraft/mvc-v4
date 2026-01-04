<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

/**
 * Migration: Create posts table (example)
 */
return function (string $direction) {
    $schema = Capsule::schema();

    if ($direction === 'up') {
        $schema->create('posts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('title', 255);
            $table->text('content');
            $table->string('status', 50)->default('draft'); // draft, published, archived
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            // Indexes
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    if ($direction === 'down') {
        $schema->dropIfExists('posts');
    }
};
