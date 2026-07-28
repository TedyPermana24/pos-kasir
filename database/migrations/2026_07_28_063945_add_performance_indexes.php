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
        Schema::table('produks', function (Blueprint $table) {
            $table->index('nama_produk');
        });

        Schema::table('promos', function (Blueprint $table) {
            $table->index(['is_active', 'tanggal_mulai', 'tanggal_selesai']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produks', function (Blueprint $table) {
            $table->dropIndex(['nama_produk']);
        });

        Schema::table('promos', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'tanggal_mulai', 'tanggal_selesai']);
        });
    }
};
