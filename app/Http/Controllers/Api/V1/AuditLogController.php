<?php
/*
 * ============================================================
 * BUAT APA : REST API audit log (khusus admin lewat middleware role:admin).
 * ENDPOINT : GET /audit-logs?entity=&action_type=&user_id=&date_from=&date_to=&q=&per_page=
 * ============================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLogFilterRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index(AuditLogFilterRequest $request)
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);

        return AuditLogResource::collection(
            AuditLog::query()->with('user:id,name,role')->filter($request->validated())->latest('id')->paginate($perPage)->withQueryString()
        );
    }
}
