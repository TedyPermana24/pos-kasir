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
        Schema::create('produk_varians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->constrained('produks')->cascadeOnDelete();
            $table->foreignId('satuan_id')->constrained('satuans')->cascadeOnDelete();
            $table->string('sku')->unique()->nullable();
            $table->string('nama_varian');
            $table->decimal('harga_modal', 15, 2)->nullable()->default(0);
            $table->decimal('harga_jual', 15, 2);
            $table->integer('stok')->nullable()->default(0);
            $table->integer('minimum_stok')->nullable()->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produk_varians');
    }
};
