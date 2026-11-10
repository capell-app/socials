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
        if (TableName::existsAndIsOwned('social_profiles', basename(__FILE__, '.php'))) {
            return;
        }

        Schema::create(TableName::resolve('social_profiles'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained(TableName::resolve('sites'))->cascadeOnDelete();
            $table->string('network_key')->nullable();
            $table->string('profile_value');
            $table->string('custom_label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['site_id', 'network_key']);
            $table->index(['site_id', 'is_enabled', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TableName::resolve('social_profiles'));
    }
};
