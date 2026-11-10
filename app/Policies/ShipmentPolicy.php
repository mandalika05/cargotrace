<?php
/*
 * ============================================================
 * BUAT APA : Aturan IZIN per shipment (Laravel Policy).
 *            Dipakai lewat Gate::authorize('view', $shipment) di controller
 *            dan @can('update', $shipment) di Blade.
 * ATURAN (sesuai brief):
 *   - Customer hanya boleh MELIHAT shipment miliknya.
 *   - Operator/Admin boleh membuat dan mengubah shipment yang BELUM terminal.
 *   - Hanya Admin yang boleh menghapus.
 * Laravel menemukan policy ini otomatis (Shipment -> ShipmentPolicy).
 * ============================================================
 */

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;

class ShipmentPolicy
{
    // Boleh membuka daftar (isi daftar sudah dibatasi per peran di Service)
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Shipment $shipment): bool
    {
        if ($user->hasRole(User::ROLE_ADMIN, User::ROLE_OPERATOR)) {
            return true;
        }

        return (int) $shipment->customer_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN, User::ROLE_OPERATOR);
    }

    public function update(User $user, Shipment $shipment): bool
    {
        return $user->hasRole(User::ROLE_ADMIN, User::ROLE_OPERATOR) && ! $shipment->isTerminal();
    }

    public function delete(User $user, Shipment $shipment): bool
    {
        return $user->isAdmin();
    }
}
