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
       Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('emergency_log_id')->nullable()->constrained('emergency_logs')->onDelete('cascade');
            $table->string('title'); // Contoh: 'Kasus Selesai'
            $table->text('message'); // Penjelasan dari admin
            $table->boolean('is_read')->default(false); // Penanda apakah sudah dibaca
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
