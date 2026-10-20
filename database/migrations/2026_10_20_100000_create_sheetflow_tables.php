<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sheetflow_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('definition_key');
            $table->string('status');
            $table->string('disk');
            $table->string('path');
            $table->string('original_filename');
            $table->string('format');
            $table->string('delimiter', 4)->nullable();
            $table->string('sheet')->nullable();
            $table->string('header_signature', 64)->nullable();
            $table->json('headers')->nullable();
            $table->json('sheet_names')->nullable();
            $table->json('preview_rows')->nullable();
            $table->json('column_map')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('batch_id')->nullable();
            $table->timestamp('staged_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['definition_key', 'user_id']);
        });

        Schema::create('sheetflow_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('run_id')->constrained('sheetflow_runs')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('values');
            $table->string('status');
            $table->timestamps();

            $table->unique(['run_id', 'row_number']);
            $table->index(['run_id', 'status']);
        });

        Schema::create('sheetflow_failures', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('run_id')->constrained('sheetflow_runs')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('values');
            $table->json('errors');
            $table->timestamps();

            $table->index(['run_id', 'row_number']);
        });

        Schema::create('sheetflow_mapping_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('definition_key');
            $table->string('name');
            $table->string('header_signature', 64);
            $table->json('column_map');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['definition_key', 'header_signature'], 'sheetflow_templates_signature_idx');
            $table->unique(['definition_key', 'name', 'user_id'], 'sheetflow_templates_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sheetflow_mapping_templates');
        Schema::dropIfExists('sheetflow_failures');
        Schema::dropIfExists('sheetflow_rows');
        Schema::dropIfExists('sheetflow_runs');
    }
};
