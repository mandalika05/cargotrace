import './bootstrap';

// Font ditanam di aplikasi (tanpa Google Fonts) supaya huruf tetap sama saat offline.
// Hanya subset "latin" dan bobot yang dipakai, agar berkas tetap kecil.
import '@fontsource/plus-jakarta-sans/latin-400.css';
import '@fontsource/plus-jakarta-sans/latin-500.css';
import '@fontsource/plus-jakarta-sans/latin-600.css';
import '@fontsource/plus-jakarta-sans/latin-700.css';
import '@fontsource/plus-jakarta-sans/latin-800.css';
import '@fontsource/fraunces/latin-600.css';

// Peta (Leaflet) dan geocoding dimuat HANYA di halaman yang memerlukannya (P12),
// jadi halaman lain tidak ikut membawa kode peta.
if (document.getElementById('shipment-map')) import('./shipment-map.js');
if (document.querySelector('[data-geocode]')) import('./geocode.js');

// Grafik dashboard (Chart.js) dimuat HANYA di halaman yang punya <canvas data-chart> (P13).
if (document.querySelector('canvas[data-chart]')) import('./dashboard-charts.js');

// Peta di dashboard (P13): dimuat hanya di halaman yang punya #dashboard-map.
if (document.getElementById('dashboard-map')) import('./dashboard-map.js');

// Lonceng notifikasi (P13.3): tutup dropdown saat klik di luar atau tekan Esc.
import './topbar.js';
