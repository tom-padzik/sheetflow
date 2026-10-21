<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sheetflow_runs', function (Blueprint $table): void {
            $table->json('invalid_actions')->nullable()->after('column_map');
            $table->unsignedInteger('stopped_row')->nullable()->after('failed_rows');
        });

        Schema::table('sheetflow_mapping_templates', function (Blueprint $table): void {
            $table->json('invalid_actions')->nullable()->after('column_map');
        });
    }

    public function down(): void
    {
        Schema::table('sheetflow_runs', function (Blueprint $table): void {
            $table->dropColumn(['invalid_actions', 'stopped_row']);
        });

        Schema::table('sheetflow_mapping_templates', function (Blueprint $table): void {
            $table->dropColumn('invalid_actions');
        });
    }
};
