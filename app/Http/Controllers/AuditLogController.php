<?php
/*
 * ============================================================
 * BUAT APA : Halaman Audit log (khusus admin): siapa mengubah apa dan kapan.
 * HAK AKSES: route dibungkus middleware role:admin.
 * ============================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\AuditLogFilterRequest;
use App\Models\AuditLog;
use App\Models\Shipment;
use App\Models\User;

class AuditLogController extends Controller
{
    public function index(AuditLogFilterRequest $request)
    {
        $filters = $request->validated();

        $logs = AuditLog::query()
            ->with('user:id,name,role')
            ->filter($filters)
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        // Link ke shipment hanya bila shipment-nya masih ada (belum dihapus)
        $shipmentIds = $logs->getCollection()->where('entity_type', 'shipment')->pluck('entity_id')->unique()->all();
        $existingShipments = Shipment::whereIn('id', $shipmentIds)->pluck('id')->all();

        return view('audit-logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'existingShipments' => $existingShipments,
            'staff' => User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_OPERATOR])->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
