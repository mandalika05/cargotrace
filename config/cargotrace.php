<?php
/*
 * ============================================================
 * BUAT APA : Nilai BAWAAN aturan bisnis CargoTrace (P11).
 *            Admin bisa mengubahnya lewat halaman Settings (tabel `settings`);
 *            kalau barisnya belum ada di database, nilai di bawah dipakai.
 * ATURAN DELAY (slide 8 brief):
 *            ETA > expected arrival  -> shipment terlambat (delayed)
 *            0 - low_max_hours       -> LOW
 *            > low_max sampai medium_max_hours -> MEDIUM
 *            > medium_max_hours      -> HIGH
 * Mau ganti ambang sesuai skenario kelompok? Ubah angka di sini
 * (atau lewat Settings tanpa menyentuh kode).
 * ============================================================
 */

return [
    'delay' => [
        'low_max_hours' => 6,
        'medium_max_hours' => 24,
    ],

    // Prakiraan cuaca (Open-Meteo: gratis, tanpa API key). Hasil disimpan di cache
    // supaya halaman cepat dan tidak membebani layanan luar.
    'weather' => [
        'url' => env('WEATHER_API_URL', 'https://api.open-meteo.com/v1/forecast'),
        'days' => 5,
        'timeout' => 5,       // detik; jaringan lambat/mati -> pesan rapi, bukan halaman macet
        'cache_minutes' => 30,
    ],

    // Geocoding (nama tempat -> koordinat) memakai Nominatim milik OpenStreetMap: gratis,
    // tanpa API key. Aturan mereka: wajib User-Agent yang jelas dan maksimal 1 permintaan
    // per detik, jadi hasilnya di-cache lama dan tombol di web dibatasi per menit.
    'geocoding' => [
        'url' => env('GEOCODING_API_URL', 'https://nominatim.openstreetmap.org/search'),
        'user_agent' => env('GEOCODING_USER_AGENT', 'CargoTrace/1.0 (university final project)'),
        'timeout' => 5,
        'cache_minutes' => 1440, // 24 jam
        'limit' => 5,
    ],

    // Peta (Leaflet + OpenStreetMap). Kebijakan tile OSM mewajibkan atribusi (sudah ada di peta).
    'map' => [
        'tile_url' => env('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
    ],
];
