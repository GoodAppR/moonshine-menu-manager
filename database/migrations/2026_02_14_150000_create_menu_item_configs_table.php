<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_item_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('layout_class')->index();
            $table->unsignedBigInteger('moonshine_user_id')->nullable()->index();
            $table->string('item_key', 500)->index();
            $table->string('parent_key', 500)->nullable()->index();
            $table->string('zone', 50)->default('sidebar');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('visible')->default(true);
            $table->timestamps();

            $table->foreign('moonshine_user_id')
                ->references('id')
                ->on('moonshine_users')
                ->onDelete('cascade');

            $table->unique(
                ['layout_class', 'moonshine_user_id', 'item_key'],
                'menu_item_configs_layout_user_item_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_configs');
    }
};
