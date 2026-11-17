// BUAT APA : Menutup dropdown lonceng notifikasi (P13.3) saat klik di luar atau tekan Esc.
// Elemen: <details id="bell-menu"> di resources/views/layouts/partials/topbar.blade.php
const bell = document.getElementById('bell-menu');

if (bell) {
    document.addEventListener('click', (event) => {
        if (bell.open && !bell.contains(event.target)) {
            bell.open = false;
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && bell.open) {
            bell.open = false;
            bell.querySelector('summary')?.focus();
        }
    });
}
