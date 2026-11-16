<?php
/*
 * ============================================================
 * BUAT APA : Aturan IZIN exception (Laravel Policy, ditemukan otomatis:
 *            ShipmentException -> ShipmentExceptionPolicy).
 *   - Admin dan Operator: boleh melihat, mencatat, dan mengubah.
 *   - Customer: hanya boleh MELIHAT exception pada shipment miliknya.
 * ============================================================
 */

namespace App\Policies;

use App\Models\ShipmentException;
use App\Models\User;

class ShipmentExceptionPolicy
{
    // Boleh membuka halaman daftar exception? Semua peran boleh; isi daftarnya yang dibatasi (customer hanya miliknya).
    public function viewAny(User $user): bool
    {
        return true; // isi daftar dibatasi per peran di ExceptionService
    }

    // Boleh melihat SATU exception? Admin/Operator: ya. Customer: hanya kalau shipment-nya miliknya sendiri.
    public function view(User $user, ShipmentException $exception): bool
    {
        if ($user->hasRole(User::ROLE_ADMIN, User::ROLE_OPERATOR)) {
            return true;
        }

        return (int) $exception->shipment?->customer_id === (int) $user->id;
    }

    // Mencatat exception baru
    public function create(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN, User::ROLE_OPERATOR);
    }

    // Mengubah status (investigasi / selesai); RESOLVED tidak bisa diubah lagi
    public function update(User $user, ShipmentException $exception): bool
    {
        return $user->hasRole(User::ROLE_ADMIN, User::ROLE_OPERATOR) && ! $exception->isResolved();
    }
}
