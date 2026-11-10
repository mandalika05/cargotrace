<?php
/*
 * ============================================================
 * BUAT APA : Model carrier (perusahaan pengangkut).
 * RELASI   : hasMany shipments (satu carrier menangani banyak shipment).
 * ============================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carrier extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'contact_email', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
