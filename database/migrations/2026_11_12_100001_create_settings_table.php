<?php
/*
 * ============================================================
 * BUAT APA : Tabel settings = pengaturan aplikasi berbentuk pasangan key-value
 *            (mis. delay_low_max_hours = 6). Dengan begini ambang delay
 *            "configurable" (syarat slide 8): diubah Admin lewat web, bukan lewat kode.
 * UNIQUE   : key (satu pengaturan = satu baris).
 * ============================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Dijalankan saat php artisan migrate: membuat tabel settings (key unik + value).
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('value', 255)->nullable();
            $table->timestamps();
        });
    }

    // Dijalankan saat rollback: menghapus tabel settings.
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
