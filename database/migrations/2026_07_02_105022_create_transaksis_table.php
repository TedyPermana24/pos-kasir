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
        Schema::create('transaksis', function (Blueprint $table) {
            $table->id();
            $table->string('no_referensi')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nama_pelanggan')->default('GUEST');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('total_pajak', 15, 2)->default(0);
            $table->decimal('total_diskon', 15, 2)->default(0);
            $table->decimal('diskon_produk', 15, 2)->default(0);
            $table->decimal('diskon_keranjang', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('bayar', 15, 2)->default(0);
            $table->decimal('kembalian', 15, 2)->default(0);
            $table->string('status')->default('selesai');
            $table->text('alasan_pembatalan')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaksis');
    }
};
