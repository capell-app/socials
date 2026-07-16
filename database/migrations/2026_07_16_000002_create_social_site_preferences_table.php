<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('social_site_preferences')) {
            return;
        }

        Schema::create('social_site_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->unique()->constrained('sites')->cascadeOnDelete();
            $table->string('follow_label_style')->default('icons');
            $table->boolean('follow_open_in_new_tab')->default(false);
            $table->json('share_network_keys')->default('[]');
            $table->string('share_label_style')->default('icons');
            $table->boolean('share_open_in_new_tab')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_site_preferences');
    }
};
