<?php
/*
 * ============================================================
 * BUAT APA : Model akun pengguna + peran (role).
 * POLA DARI: UTS MedikaCare (model User dengan kolom role).
 * CATATAN KEAMANAN:
 *   - 'role' SENGAJA TIDAK masuk $fillable (anti mass-assignment).
 *     Kalau masuk, orang bisa mendaftar sambil mengirim role=admin.
 *   - Role diisi lewat Factory/Seeder, atau lewat halaman Admin (P9).
 *   - HasApiTokens = token Sanctum untuk REST API.
 * ============================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // Nama peran dibuat konstanta supaya tidak salah ketik di banyak tempat
    public const ROLE_ADMIN = 'admin';
    public const ROLE_OPERATOR = 'operator';
    public const ROLE_CUSTOMER = 'customer';

    // Hanya kolom ini yang boleh diisi massal (create/update dari input)
    protected $fillable = ['name', 'email', 'password'];

    // Kolom ini tidak ikut tampil saat model diubah jadi JSON
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed', // otomatis di-hash, tidak perlu Hash::make manual
        ];
    }

    // --- Pembantu cek peran (dipakai di Blade, Policy, dan Service) ---
    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool    { return $this->role === self::ROLE_ADMIN; }
    public function isOperator(): bool { return $this->role === self::ROLE_OPERATOR; }
    public function isCustomer(): bool { return $this->role === self::ROLE_CUSTOMER; }

    // Shipment milik customer ini (satu customer punya banyak shipment)
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'customer_id');
    }
}
