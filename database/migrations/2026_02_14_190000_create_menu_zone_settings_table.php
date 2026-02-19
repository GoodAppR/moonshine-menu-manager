<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_zone_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('layout_class')->index();
            $table->unsignedBigInteger('moonshine_user_id')->nullable()->index();
            $table->string('zone', 50)->index();
            $table->string('key', 100)->index();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->foreign('moonshine_user_id')
                ->references('id')
                ->on('moonshine_users')
                ->onDelete('cascade');

            $table->unique(
                ['layout_class', 'moonshine_user_id', 'zone', 'key'],
                'menu_zone_settings_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_zone_settings');
    }
};
