<?php

namespace Padmission\Tickets\Tests\Fixtures\Migrations;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Older host-owned copies, deliberately independent of package migration code. */
class HostTicketSchema
{
    public static function create(string $stateTable = 'ticket_user_states', bool $withAdditions = false, bool $withLinked = true): void
    {
        Schema::create('ticket_dispositions', function (Blueprint $table) {
            $table->id();

            if (config('padmission-tickets.tenancy.enabled', false)) {
                $tenantModelClass = config('padmission-tickets.tenancy.tenancy_model');
                $tenantKey = Str::snake(class_basename($tenantModelClass)).'_id';
                $traits = class_uses_recursive($tenantModelClass);

                match (true) {
                    in_array(HasUlids::class, $traits) => $table->foreignUlid($tenantKey)->constrained(),
                    in_array(HasUuids::class, $traits) => $table->foreignUuid($tenantKey)->constrained(),
                    default => $table->foreignId($tenantKey)->constrained(),
                };
            }

            $table->string('panel');
            $table->string('display_name');
            $table->string('color');
            $table->unsignedInteger('order')->default(99);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ticket_statuses', function (Blueprint $table) {
            $table->id();

            if (config('padmission-tickets.tenancy.enabled', false)) {
                $tenantModelClass = config('padmission-tickets.tenancy.tenancy_model');
                $tenantKey = Str::snake(class_basename($tenantModelClass)).'_id';
                $traits = class_uses_recursive($tenantModelClass);

                match (true) {
                    in_array(HasUlids::class, $traits) => $table->foreignUlid($tenantKey)->constrained(),
                    in_array(HasUuids::class, $traits) => $table->foreignUuid($tenantKey)->constrained(),
                    default => $table->foreignId($tenantKey)->constrained(),
                };
            }

            $table->string('panel');
            $table->string('display_name');
            $table->string('color');
            $table->unsignedInteger('order')->default(99);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ticket_priorities', function (Blueprint $table) {
            $table->id();

            if (config('padmission-tickets.tenancy.enabled', false)) {
                $tenantModelClass = config('padmission-tickets.tenancy.tenancy_model');
                $tenantKey = Str::snake(class_basename($tenantModelClass)).'_id';
                $traits = class_uses_recursive($tenantModelClass);

                match (true) {
                    in_array(HasUlids::class, $traits) => $table->foreignUlid($tenantKey)->constrained(),
                    in_array(HasUuids::class, $traits) => $table->foreignUuid($tenantKey)->constrained(),
                    default => $table->foreignId($tenantKey)->constrained(),
                };
            }

            $table->string('panel');
            $table->string('display_name');
            $table->string('color');
            $table->unsignedInteger('order')->default(99);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tickets', function (Blueprint $table) use ($withLinked) {
            $table->id();

            if (config('padmission-tickets.tenancy.enabled', false)) {
                $tenantModelClass = config('padmission-tickets.tenancy.tenancy_model');
                $tenantKey = Str::snake(class_basename($tenantModelClass)).'_id';
                $traits = class_uses_recursive($tenantModelClass);

                match (true) {
                    in_array(HasUlids::class, $traits) => $table->foreignUlid($tenantKey)->constrained(),
                    in_array(HasUuids::class, $traits) => $table->foreignUuid($tenantKey)->constrained(),
                    default => $table->foreignId($tenantKey)->constrained(),
                };
            }

            $table->string('panel')->index();
            $table->string('source_panel')->nullable()->index();
            $table->string('escalation_level')->default('default');
            if ($withLinked) {
                $table->foreignId('linked_ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            }

            $table->string('subject');
            $table->foreignId('status_id')->constrained('ticket_statuses');
            $table->foreignId('priority_id')->constrained('ticket_priorities');
            $table->foreignId('disposition_id')->nullable()->constrained('ticket_dispositions')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitter_id')->nullable()->constrained('users')->nullOnDelete();

            $table->json('submitter_data')->nullable();
            $table->string('turn');
            $table->json('data')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ticket_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('sender');
            $table->string('type');
            $table->text('content')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create($stateTable, function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index('user_id');
            $table->unique(['ticket_id', 'user_id']);
        });

        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('ticket_activities')->cascadeOnDelete();
            $table->string('filename');
            $table->string('filepath');
            $table->string('preview_filepath')->nullable();
            $table->integer('file_size')->unsigned();
            $table->string('mime_type');
            $table->timestamps();

            $table->index('activity_id');
        });

        if ($stateTable !== 'ticket_notifications') {
            Schema::table($stateTable, function (Blueprint $table): void {
                $table->foreignId('last_seen_activity_id')->nullable()->constrained('ticket_activities')->nullOnDelete();
                $table->foreignId('last_notified_activity_id')->nullable()->constrained('ticket_activities')->nullOnDelete();
                $table->index('last_seen_activity_id');
                $table->index('last_notified_activity_id');
            });
        }

        if ($withAdditions) {
            Schema::table('ticket_attachments', function (Blueprint $table): void {
                $table->unsignedBigInteger('created_by')->nullable()->index('host_created_by_index');
            });
            Schema::table('tickets', function (Blueprint $table): void {
                $table->foreignId('duplicate_of_ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            });
            Schema::table('ticket_activities', function (Blueprint $table): void {
                $table->index(['ticket_id', 'type', 'sender', 'created_at'], 'host_requester_age_index');
            });
        }
    }
}
