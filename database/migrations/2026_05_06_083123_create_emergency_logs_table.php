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
        Schema::create('emergency_logs', function (Blueprint $table) {
    $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('emergency_type')->nullable(); // medical, accident, fire, etc.
            $table->text('notes')->nullable();
            $table->string('status')->default('triggered'); // triggered, contacted, resolved, cancelled
            $table->json('contacted_entities')->nullable(); // contacts & hospitals notified
            $table->json('response_data')->nullable(); // agent decision log
            $table->timestamp('triggered_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index('triggered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emergency_logs');
    }
};
