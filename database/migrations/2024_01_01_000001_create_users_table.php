<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

/**
 * Migration: Create users table
 */
return function (string $direction) {
    $schema = Capsule::schema();

    if ($direction === 'up') {
        $schema->create('users', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->string('email', 255)->unique();
            $table->string('password', 255)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('email');
            $table->index('created_at');
        });
    }

    if ($direction === 'down') {
        $schema->dropIfExists('users');
    }
};
