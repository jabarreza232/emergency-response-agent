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
        Schema::table('users', function (Blueprint $table) {
            // Menambahkan foreign key role_id yang terhubung ke tabel roles
            $table->foreignId('role_id')
                ->nullable() // nullable agar user lama yang sudah terdaftar tidak error
                ->after('id') // Meletakkan kolom setelah kolom 'id' (opsional, khusus MySQL)
                ->constrained('roles') // Otomatis mendeteksi foreign key ke tabel 'roles'
                ->onDelete('set null'); // Jika role dihapus, user tidak ikut terhapus, hanya role_id jadi null
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Menghapus foreign key dan kolom jika migration di-rollback
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });
    }
};
