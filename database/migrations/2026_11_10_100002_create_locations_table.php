<?php
/*
 * ============================================================
 * BUAT APA : Tabel locations = pelabuhan / kota asal dan tujuan.
 * KOORDINAT: latitude & longitude disimpan decimal(10,7) di SEMUA tabel
 *            supaya konsisten (syarat brief untuk peta). Boleh kosong dulu;
 *            P12 mengisinya lewat geocoding.
 * UNIQUE   : kombinasi (name, city) agar tidak ada lokasi ganda.
 * ============================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('city', 100);
            $table->string('country', 100);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->unique(['name', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
