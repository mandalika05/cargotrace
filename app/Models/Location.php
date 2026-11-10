<?php
/*
 * ============================================================
 * BUAT APA : Model lokasi (pelabuhan/kota) untuk origin dan destination.
 * RELASI   : satu lokasi bisa jadi asal DAN tujuan banyak shipment,
 *            jadi ada dua relasi hasMany dengan kunci berbeda.
 * ============================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'city', 'country', 'latitude', 'longitude'];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function originShipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'origin_id');
    }

    public function destinationShipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'destination_id');
    }

    // Teks untuk dropdown / tampilan: "Singapore Port - Singapore, Singapore"
    public function label(): string
    {
        return "{$this->name} ({$this->city}, {$this->country})";
    }
}
