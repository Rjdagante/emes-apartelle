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
            $table->decimal('extra_bed_price', 10, 2)->default(300.00)->after('price_per_head')->nullable();
            $table->unsignedInteger('max_extra_guests')->default(2)->after('max_occupancy');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedInteger('extra_guests')->default(0)->after('guests');
            $table->decimal('extra_bed_charge', 10, 2)->default(0.00)->after('total_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['extra_bed_price', 'max_extra_guests']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['extra_guests', 'extra_bed_charge']);
        });
    }
};

