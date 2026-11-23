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
        if (!Schema::hasTable('room_packages')) {
            Schema::create('room_packages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
                $table->string('name');
                $table->string('tagline')->nullable();
                $table->string('duration')->default('3 Days, 2 Nights');
                $table->string('pax')->default('Good for 2 pax');
                $table->decimal('price', 10, 2)->default(0);
                $table->string('room_type_label')->default('Private Room');
                $table->string('tour_type')->default('Joiners Tour');
                $table->json('room_inclusions')->nullable();
                $table->json('tour_destinations')->nullable();
                $table->json('tour_inclusions')->nullable();
                $table->text('description')->nullable();
                $table->string('location')->nullable();
                $table->string('contact_number')->nullable();
                $table->string('image')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_packages');
    }
};

