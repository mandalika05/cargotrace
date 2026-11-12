{{--
 ============================================================
 BUAT APA : Logo CargoTrace (SVG tertanam, tanpa gambar dari luar).
            Huruf "C" dari garis lengkung + titik emas di ujungnya
            = jejak (trace) yang bergerak. Pakai: <x-logo-mark />
 Ukuran diatur lewat CSS (kelas .logo-mark, atau tambah class sendiri).
 ============================================================
--}}
<svg {{ $attributes->merge(['class' => 'logo-mark']) }} viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <rect width="40" height="40" rx="11" fill="#0E6B5C"/>
    <rect x="1" y="1" width="38" height="38" rx="10" stroke="#F4B740" stroke-opacity=".6" stroke-width="1.5"/>
    <path d="M27.3 13.9A9.5 9.5 0 1 0 27.3 26.1" stroke="#FFFEFB" stroke-width="3" stroke-linecap="round"/>
    <circle cx="28.8" cy="12.6" r="3" fill="#F4B740"/>
</svg>
