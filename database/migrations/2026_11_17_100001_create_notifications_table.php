<?php
/*
 * ============================================================
 * BUAT APA : Tabel `notifications` (notifikasi in-app). Skema standar Laravel untuk
 *            notifikasi tipe "database": satu baris = satu notifikasi untuk satu user.
 *            id        UUID
 *            type      nama kelas notifikasi
 *            notifiable_type/id  penerima (polimorfik, di sini selalu User)
 *            data      isi notifikasi (JSON: judul, pesan, link, shipment)
 *            read_at   NULL = belum dibaca
 * ============================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Hitung "belum dibaca" per user dengan cepat (lonceng di tiap halaman)
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_unread_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
