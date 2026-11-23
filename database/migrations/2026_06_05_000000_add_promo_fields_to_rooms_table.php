<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->boolean('is_promo')->default(false)->after('status')->index();
            $table->decimal('promo_price', 10, 2)->nullable()->after('is_promo');
            $table->decimal('promo_price_per_head', 10, 2)->nullable()->after('promo_price');
            $table->unsignedTinyInteger('promo_discount_percent')->nullable()->after('promo_price_per_head');
            $table->string('promo_title')->nullable()->after('promo_discount_percent');
            $table->text('promo_description')->nullable()->after('promo_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn([
                'is_promo',
                'promo_price',
                'promo_price_per_head',
                'promo_discount_percent',
                'promo_title',
                'promo_description',
            ]);
        });
    }
};

