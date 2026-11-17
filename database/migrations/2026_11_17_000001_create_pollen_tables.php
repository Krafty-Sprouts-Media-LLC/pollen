<?php

/**
 * Pollen: the tables Pollen keeps its channels, shares and plans in.
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

        Schema::create('pollen_automations', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('name', 80);
            $table->string('kind', 16);
            $table->boolean('active')->default(true);
            $table->jsonb('settings')->default('{}');
            $table->timestampTz('next_run_at')->nullable();
            $table->unsignedInteger('turn')->default(0);
            $table->string('created_by', 32)->default('');
            $table->string('last_note', 500)->default('');
            $table->timestampsTz();
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

        Schema::create('pollen_images', function (Blueprint $table): void {
            $table->string('key', 40)->primary();
            $table->string('source', 2048);
            $table->string('network', 32);
            $table->string('mime', 32);
            $table->longText('bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->timestampTz('created_at');
        });

        Schema::create('pollen_connects', function (Blueprint $table): void {
            $table->string('state', 64)->primary();
            $table->string('channel_id', 26);
            $table->string('verifier', 128)->default('');
            $table->string('started_by', 32)->default('');
            $table->timestampTz('expires_at');
        });

        Schema::create('pollen_people', function (Blueprint $table): void {
            $table->string('person_id', 32)->primary();
            $table->jsonb('permissions')->default('[]');
            $table->string('zone', 64)->default('');
            $table->timestampTz('seen_at');
        });

        Schema::create('pollen_requests', function (Blueprint $table): void {
            $table->string('key', 120)->primary();
            $table->string('plugin', 120)->default('');
            $table->timestampTz('created_at');
        });
    }
};
