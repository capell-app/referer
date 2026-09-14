<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('referer_daily_counts')) {
            Schema::create('referer_daily_counts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
                $table->date('date');
                $table->string('source_key', 64);
                $table->unsignedBigInteger('count')->default(0);
                $table->unique(['site_id', 'date', 'source_key'], 'referer_daily_counts_site_date_source_unique');
                $table->index(['site_id', 'date'], 'referer_daily_counts_site_date_index');
            });
        }

        if (! Schema::hasTable('referer_source_totals')) {
            Schema::create('referer_source_totals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
                $table->string('source_key', 64);
                $table->unsignedBigInteger('count')->default(0);
                $table->date('collection_started_on');
                $table->unique(['site_id', 'source_key'], 'referer_source_totals_site_source_unique');
            });
        }

        if (! Schema::hasTable('referer_retention_state')) {
            Schema::create('referer_retention_state', function (Blueprint $table): void {
                $table->unsignedTinyInteger('id')->primary();
                $table->date('daily_available_from');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('referer_daily_counts');
        Schema::dropIfExists('referer_source_totals');
        Schema::dropIfExists('referer_retention_state');
    }
};
