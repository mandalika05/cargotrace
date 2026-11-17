<?php
/*
 * ============================================================
 * BUAT APA : Menghubungkan model ke AuditService. Didaftarkan untuk setiap model yang
 *            dipantau di AppServiceProvider. Pola ini membuat audit log TIDAK perlu
 *            mengubah service/controller yang sudah jadi.
 * ============================================================
 */

namespace App\Observers;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function created(Model $model): void
    {
        if (AuditService::enabled()) {
            $this->audit->created($model);
        }
    }

    public function updated(Model $model): void
    {
        if (AuditService::enabled()) {
            $this->audit->updated($model);
        }
    }

    public function deleted(Model $model): void
    {
        if (AuditService::enabled()) {
            $this->audit->deleted($model);
        }
    }

    public function restored(Model $model): void
    {
        if (AuditService::enabled()) {
            $this->audit->restored($model);
        }
    }
}
