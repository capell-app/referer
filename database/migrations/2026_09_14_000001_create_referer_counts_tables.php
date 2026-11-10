<?php

declare(strict_types=1);

use Capell\Core\Support\TableName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! TableName::existsAndIsOwned('referer_daily_counts', basename(__FILE__, '.php'))) {
            Schema::create(TableName::resolve('referer_daily_counts'), function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_id')->constrained(TableName::resolve('sites'))->cascadeOnDelete();
                $table->date('date');
                $table->string('source_key', 64);
                $table->unsignedBigInteger('count')->default(0);
                $table->unique(['site_id', 'date', 'source_key'], TableName::namedIndex('referer_daily_counts', 'referer_daily_counts_site_date_source_unique'));
                $table->index(['site_id', 'date'], TableName::namedIndex('referer_daily_counts', 'referer_daily_counts_site_date_index'));
            });
        }

        if (! TableName::existsAndIsOwned('referer_source_totals', basename(__FILE__, '.php'))) {
            Schema::create(TableName::resolve('referer_source_totals'), function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_id')->constrained(TableName::resolve('sites'))->cascadeOnDelete();
                $table->string('source_key', 64);
                $table->unsignedBigInteger('count')->default(0);
                $table->date('collection_started_on');
                $table->unique(['site_id', 'source_key'], TableName::namedIndex('referer_source_totals', 'referer_source_totals_site_source_unique'));
            });
        }

        if (! TableName::existsAndIsOwned('referer_retention_state', basename(__FILE__, '.php'))) {
            Schema::create(TableName::resolve('referer_retention_state'), function (Blueprint $table): void {
                $table->unsignedTinyInteger('id')->primary();
                $table->date('daily_available_from');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(TableName::resolve('referer_daily_counts'));
        Schema::dropIfExists(TableName::resolve('referer_source_totals'));
        Schema::dropIfExists(TableName::resolve('referer_retention_state'));
    }
};
