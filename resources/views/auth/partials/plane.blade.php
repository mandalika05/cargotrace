{{--
 ============================================================
 BUAT APA : Pesawat kargo kecil yang terbang pelan melintasi latar login (P14.5b, EKSPERIMEN).
 CARA HAPUS : hapus baris @include('auth.partials.plane') di layouts/guest.blade.php
              (dan baris @import './plane.css' di resources/css/app.css). Selesai.
 CATATAN  : SVG tertanam (tetap muncul offline). Hanya tampil di laptop dan dimatikan bila
            pengguna memilih "kurangi gerakan" (lihat resources/css/plane.css).
 ============================================================
--}}
<div class="login-plane" aria-hidden="true">
    <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
        <g fill="#FFFEFB">
            <path d="M4 30h44c8 0 12 2 12 2s-4 2-12 2H4z"/>
            <path d="M26 31 14 8h6l20 23z"/>
            <path d="M26 33 14 56h6l20-23z"/>
            <path d="M6 31 2 22h5l5 9z"/>
            <path d="M6 33 2 42h5l5-9z"/>
        </g>
    </svg>
</div>
