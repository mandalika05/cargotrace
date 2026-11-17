<?php
/*
 * ============================================================
 * BUAT APA : Tabel `audit_logs` = jejak perubahan penting (syarat brief: catat aktor, aksi,
 *            waktu, dan entity terkait).
 * KOLOM    : user_id       siapa yang melakukan (NULL = sistem / belum login)
 *            action        mis. shipment.created, shipment.updated, user.updated
 *            entity_type   jenis data: shipment, tracking_event, exception, user, carrier, ...
 *            entity_id     id data tersebut
 *            entity_label  nama yang mudah dibaca (nomor shipment, email, nama carrier)
 *            old_values / new_values  nilai sebelum dan sesudah (JSON; tanpa password)
 *            ip_address    alamat IP peminta
 *            created_at    waktu kejadian (tidak ada updated_at: log tidak pernah diubah)
 * HAPUS USER: user_id jadi NULL (nullOnDelete) supaya riwayat tetap ada.
 * ============================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('entity_type', 40);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('entity_label', 150)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id']);
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
