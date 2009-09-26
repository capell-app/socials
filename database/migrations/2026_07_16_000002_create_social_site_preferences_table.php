<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const string GENERATION_TABLE = 'social_site_preferences_table_generation';

    public function up(): void
    {
        if (! Schema::hasTable('social_site_preferences')) {
            Schema::create('social_site_preferences', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_id')->unique()->constrained('sites')->cascadeOnDelete();
                $table->string('follow_label_style')->default('icons');
                $table->boolean('follow_open_in_new_tab')->default(false);
                $table->json('share_network_keys')->nullable();
                $table->string('share_label_style')->default('icons');
                $table->boolean('share_open_in_new_tab')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable(self::GENERATION_TABLE)) {
            Schema::create(self::GENERATION_TABLE, function (Blueprint $table): void {
                $table->unsignedTinyInteger('id')->primary();
                $table->uuid('generation');
            });

            DB::table(self::GENERATION_TABLE)->insert([
                'id' => 1,
                'generation' => (string) Str::uuid(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_site_preferences');
        Schema::dropIfExists(self::GENERATION_TABLE);
    }
};
