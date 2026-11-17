<?php

/**
 * Pollen: the tables for channels, shares and the items Pollen heard of.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pollen_channels', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('name', 80);
            $table->string('network', 32);
            $table->string('account', 200)->default('');
            $table->string('account_name', 200)->default('');
            $table->jsonb('labels')->default('[]');
            $table->jsonb('auto_types')->default('[]');
            $table->string('server', 255)->default('');
            $table->string('handle', 200)->default('');
            $table->text('sealed')->default('');
            $table->timestampTz('token_expires_at')->nullable();
            $table->jsonb('options')->default('{}');
            $table->string('status', 24)->default('new');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('failures')->default(0);
            $table->string('last_error', 500)->default('');
            $table->string('connect_key', 40)->nullable()->unique();
            $table->string('access', 16)->default('sharers');
            $table->jsonb('people')->default('[]');
            $table->string('created_by', 32)->default('');
            $table->timestampsTz();
            $table->index('network');
        });

        Schema::create('pollen_shares', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('channel_id', 26)->index();
            $table->string('item_id', 26)->nullable()->index();
            $table->string('item_title', 512)->default('');
            $table->string('item_url', 2048)->default('');
            $table->string('origin', 16);
            $table->string('automation_id', 26)->nullable()->index();
            $table->string('status', 16);
            $table->text('message')->nullable();
            $table->text('text')->nullable();
            $table->string('image_url', 2048)->default('');
            $table->text('first_comment')->default('');
            $table->timestampTz('send_at');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('sent_at')->nullable();
            $table->string('remote_id', 255)->default('');
            $table->string('remote_url', 2048)->default('');
            $table->string('reply', 500)->default('');
            $table->string('requested_by', 32)->default('');
            $table->string('request_key', 120)->nullable()->unique();
            $table->timestampsTz();
            $table->index(['status', 'send_at']);
            $table->index(['channel_id', 'sent_at']);
        });

        Schema::create('pollen_items', function (Blueprint $table): void {
            $table->string('item_id', 26)->primary();
            $table->boolean('opted_out')->default(false);
            $table->timestampTz('published_at')->nullable();
            $table->string('type', 64)->default('');
            $table->string('title', 512)->default('');
            $table->string('url', 2048)->default('');
            $table->boolean('gone')->default(false);
            $table->unsignedInteger('times_shared')->default(0);
            $table->timestampTz('last_shared_at')->nullable();
            $table->string('panel_note', 200)->default('');
            $table->timestampsTz();
        });
    }
};
