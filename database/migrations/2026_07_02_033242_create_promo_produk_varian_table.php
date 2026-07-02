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
        Schema::create('promo_produk_varian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_id')->constrained('promos')->cascadeOnDelete();
            $table->foreignId('produk_varian_id')->constrained('produk_varians')->cascadeOnDelete();
            $table->decimal('minimal_harga_jual', 15, 2);
            $table->timestamps();

            $table->unique(['promo_id', 'produk_varian_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_produk_varian');
    }
};
