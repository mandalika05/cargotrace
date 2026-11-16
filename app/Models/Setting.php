<?php
/*
 * ============================================================
 * BUAT APA : Model pengaturan aplikasi (key-value). Dipakai EtaService untuk
 *            membaca ambang delay yang bisa diubah Admin.
 * CACHE    : semua pengaturan dibaca SEKALI lalu disimpan di cache, supaya daftar
 *            shipment yang menghitung delay puluhan kali tidak menembak database
 *            terus. Cache dihapus otomatis setiap kali ada pengaturan yang disimpan.
 * ============================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    private const CACHE_KEY = 'cargotrace.settings';

    protected $fillable = ['key', 'value'];

    // Ambil satu nilai; kalau belum ada, kembalikan $default
    public static function getValue(string $key, mixed $default = null): mixed
    {
        return self::allCached()[$key] ?? $default;
    }

    // Simpan (atau perbarui) satu nilai, lalu buang cache
    public static function putValue(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, string|null> */
    private static function allCached(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());
    }
}
