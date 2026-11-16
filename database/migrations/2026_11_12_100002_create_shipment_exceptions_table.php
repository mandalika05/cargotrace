<?php
/*
 * ============================================================
 * BUAT APA : Tabel shipment_exceptions = gangguan pada shipment
 *            (customs hold, cuaca, pelabuhan padat, barang rusak, dst).
 * RELASI   : 1 shipment punya BANYAK exception (hasMany). cascadeOnDelete
 *            hanya berlaku bila shipment dihapus PERMANEN (soft delete tidak memicunya).
 * KOLOM    : type, severity (LOW/MEDIUM/HIGH), status (OPEN/INVESTIGATING/RESOLVED),
 *            detected_at (waktu terdeteksi), resolved_at (waktu selesai),
 *            reported_by / resolved_by (siapa yang mencatat), resolution_note.
 * INDEX    : (shipment_id, status) untuk panel di detail shipment,
 *            (status, severity) untuk daftar exception dan antrean "Needs attention".
 * ============================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Dijalankan saat php artisan migrate: membuat tabel shipment_exceptions beserta kunci asing dan index.
    public function up(): void
    {
        Schema::create('shipment_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('severity', 10);
            $table->string('status', 15)->default('OPEN');
            $table->text('description')->nullable();
            $table->dateTime('detected_at');
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shipment_id', 'status']);
            $table->index(['status', 'severity']);
        });
    }

    // Dijalankan saat rollback: menghapus tabel shipment_exceptions.
    public function down(): void
    {
        Schema::dropIfExists('shipment_exceptions');
    }
};
