<?php
/*
 * ============================================================
 * BUAT APA : Tabel carriers = perusahaan pengangkut (operator logistik).
 * UNIQUE   : code (kode pendek unik, mis. NFL).
 * is_active: carrier nonaktif tidak muncul di form shipment baru,
 *            tapi shipment lama tetap utuh.
 * ============================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carriers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 10)->unique();
            $table->string('contact_email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carriers');
    }
};
