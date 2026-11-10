<?php
/*
 * ============================================================
 * BUAT APA : Tabel shipments = data utama pengiriman.
 * RELASI   : customer_id & created_by -> users, carrier_id -> carriers,
 *            origin_id & destination_id -> locations (dua kunci ke tabel yang sama).
 * restrictOnDelete : carrier/lokasi/customer yang masih dipakai shipment
 *            TIDAK bisa dihapus (dijaga langsung oleh database).
 * softDeletes : shipment yang "dihapus" Admin hanya ditandai (deleted_at),
 *            riwayatnya tetap aman.
 * INDEX    : status, expected_arrival_at, (carrier_id,status) untuk filter & dashboard.
 * status   : CREATED | BOOKED | PICKED_UP | IN_TRANSIT | DELIVERED | CANCELLED.
 *            Perubahannya dijaga ShipmentService (transisi di P10), bukan input bebas.
 * ============================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_number', 30)->unique();

            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('carrier_id')->constrained('carriers')->restrictOnDelete();
            $table->foreignId('origin_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('destination_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('cargo_type', 100);
            $table->decimal('weight_kg', 10, 2);
            $table->decimal('volume_cbm', 10, 2);

            $table->dateTime('expected_departure_at');
            $table->dateTime('expected_arrival_at');
            $table->dateTime('estimated_arrival_at')->nullable();

            $table->string('status', 20)->default('CREATED');
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
            $table->index('expected_arrival_at');
            $table->index(['carrier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
