<?php
/*
 * ============================================================
 * BUAT APA : "Peta jalan" status shipment. SATU-SATUNYA tempat yang menentukan
 *            perpindahan status mana yang sah (aturan di slide 6 brief).
 *
 *   CREATED -> BOOKED -> PICKED_UP -> IN_TRANSIT -> DELIVERED
 *   CANCELLED hanya dari CREATED atau BOOKED.
 *   DELIVERED dan CANCELLED = terminal (tidak ada jalan keluar),
 *   kecuali lewat prosedur khusus Admin: reopen().
 *
 * Mau ubah alur? Ubah tabel TRANSITIONS di bawah. Cukup di sini.
 * ============================================================
 */

namespace App\Services;

use App\Models\Shipment;

class ShipmentStateMachine
{
    public const TRANSITIONS = [
        Shipment::STATUS_CREATED => [Shipment::STATUS_BOOKED, Shipment::STATUS_CANCELLED],
        Shipment::STATUS_BOOKED => [Shipment::STATUS_PICKED_UP, Shipment::STATUS_CANCELLED],
        Shipment::STATUS_PICKED_UP => [Shipment::STATUS_IN_TRANSIT],
        Shipment::STATUS_IN_TRANSIT => [Shipment::STATUS_DELIVERED],
        Shipment::STATUS_DELIVERED => [],
        Shipment::STATUS_CANCELLED => [],
    ];

    // Tujuan prosedur reopen (hanya Admin): DELIVERED kembali IN_TRANSIT, CANCELLED kembali CREATED
    public const REOPEN_TARGETS = [
        Shipment::STATUS_DELIVERED => Shipment::STATUS_IN_TRANSIT,
        Shipment::STATUS_CANCELLED => Shipment::STATUS_CREATED,
    ];

    public function allowedNext(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, $this->allowedNext($from), true);
    }

    public function reopenTarget(string $from): ?string
    {
        return self::REOPEN_TARGETS[$from] ?? null;
    }
}
