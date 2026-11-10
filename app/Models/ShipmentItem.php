<?php
/*
 * ============================================================
 * BUAT APA : Model rincian barang. Milik satu shipment (belongsTo).
 * ============================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentItem extends Model
{
    protected $fillable = ['description', 'quantity', 'weight_kg'];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
