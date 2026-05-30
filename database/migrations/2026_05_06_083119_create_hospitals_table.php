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
        Schema::create('hospitals', function (Blueprint $table) {
       $table->id();
            $table->string('name');
            $table->string('address');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('type')->default('general'); // general, specialist, clinic
            $table->boolean('has_emergency_unit')->default(true);
            $table->string('operating_hours')->nullable();
            $table->text('facilities')->nullable(); // JSON string
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Spatial index for geolocation queries
            $table->index(['latitude', 'longitude']);
            $table->index(['is_active', 'has_emergency_unit']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hospitals');
    }
};
