<?php
/*
 * ============================================================
 * BUAT APA : Tabel tracking_events = RIWAYAT perjalanan shipment.
 * APPEND-ONLY: event hanya boleh DITAMBAH, tidak diubah atau dihapus
 *            (dijaga model TrackingEvent). Karena itu tabel ini tidak punya
 *            kolom updated_at, hanya created_at.
 * event_type : status (BOOKED, PICKED_UP, IN_TRANSIT, DELIVERED, CANCELLED)
 *            atau LOCATION_UPDATE / REOPENED.
 * LOKASI   : location_id (opsional, dari tabel locations) dan/atau location_name
 *            (teks bebas, mis. "Suez Canal") + latitude/longitude decimal(10,7),
 *            format koordinat yang sama dengan tabel locations.
 * INDEX    : (shipment_id, occurred_at) untuk membaca timeline dengan cepat.
 * ============================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 30);
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('location_name', 150)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->dateTime('occurred_at');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['shipment_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_events');
    }
};
