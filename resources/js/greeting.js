// BUAT APA : Dua sapaan kecil (P14.5), dihitung di browser pengunjung:
//   1) Dashboard: "Good morning/afternoon/evening/night" sesuai JAM di perangkat.
//      Elemen: <span data-greeting> di resources/views/dashboard.blade.php
//      (server memakai zona waktu UTC, jadi jam server bisa salah untuk pengunjung di Indonesia)
//   2) Halaman login: "Welcome to CargoTrace" untuk yang BARU PERTAMA KALI membuka di browser ini,
//      "Welcome back" untuk yang sudah pernah berhasil masuk. Elemen: <h2 data-welcome> di auth/login.blade.php
//      Penanda "pernah masuk" disimpan di localStorage browser (bukan di server, dan tidak berisi data pribadi).
// Aturan sapaan dashboard: 04-11 Good morning | 12-16 Good afternoon | 17-20 Good evening | selain itu Good night

const SEEN_KEY = 'cargotrace_seen';

function greetingFor(hour) {
    if (hour >= 4 && hour < 12) return 'Good morning';
    if (hour >= 12 && hour < 17) return 'Good afternoon';
    if (hour >= 17 && hour < 21) return 'Good evening';
    return 'Good night';
}

// localStorage bisa dimatikan (mode privat, dll): semua akses dibungkus try/catch
function readSeen() {
    try { return localStorage.getItem(SEEN_KEY) === '1'; } catch (e) { return false; }
}

function writeSeen() {
    try { localStorage.setItem(SEEN_KEY, '1'); } catch (e) { /* abaikan */ }
}

// 1) Sapaan dashboard
document.querySelectorAll('[data-greeting]').forEach((el) => {
    el.textContent = greetingFor(new Date().getHours());
});

// 2) Halaman login
document.querySelectorAll('[data-welcome]').forEach((el) => {
    if (readSeen()) el.textContent = 'Welcome back';
});

// Halaman setelah login (punya .app-shell) = pengguna berhasil masuk: catat bahwa browser ini sudah pernah masuk
if (document.querySelector('.app-shell')) writeSeen();
