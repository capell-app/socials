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
        if (! Schema::hasTable(TableName::resolve('social_site_preferences')) || ! Schema::hasColumn(TableName::resolve('social_site_preferences'), 'share_network_keys')) {
            return;
        }

        $column = collect(Schema::getColumns(TableName::resolve('social_site_preferences')))
            ->firstWhere('name', 'share_network_keys');

        if (! is_array($column) || ($column['nullable'] ?? false) === true) {
            return;
        }

        Schema::table(TableName::resolve('social_site_preferences'), function (Blueprint $table): void {
            $table->json('share_network_keys')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(TableName::resolve('social_site_preferences')) || ! Schema::hasColumn(TableName::resolve('social_site_preferences'), 'share_network_keys')) {
            return;
        }

        $column = collect(Schema::getColumns(TableName::resolve('social_site_preferences')))
            ->firstWhere('name', 'share_network_keys');

        if (! is_array($column) || ($column['nullable'] ?? false) !== true) {
            return;
        }

        Schema::table(TableName::resolve('social_site_preferences'), function (Blueprint $table): void {
            $table->json('share_network_keys')->nullable(false)->change();
        });
    }
};
