/*
 * ============================================================
 * BUAT APA : Menggambar PETA shipment di halaman detail memakai Leaflet + OpenStreetMap
 *            (syarat brief). Marker: asal, tujuan, dan titik tracking; garis rute; popup
 *            berisi status, waktu, dan lokasi.
 * DATA     : diambil dari /shipments/{id}/map (MapController). Peta dimuat belakangan
 *            sehingga halaman tidak tertahan kalau internet lambat.
 * AMAN     : isi popup dipasang dengan textContent (bukan innerHTML), jadi nama tempat
 *            yang berisi HTML tidak akan dijalankan (mencegah XSS).
 * ATRIBUSI : "© OpenStreetMap contributors" tampil di pojok peta (wajib oleh OSM).
 * ============================================================
 */
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const box = document.getElementById('shipment-map');

function el(tag, cls, text) {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text !== undefined && text !== null && text !== '') e.textContent = text;
    return e;
}

// Pesan pengganti peta (loading / tidak ada koordinat / gagal)
function showMessage(text) {
    box.replaceChildren(el('p', 'map-message', text));
}

// Isi popup dibuat dari elemen DOM, bukan string HTML
function popupContent(p) {
    const wrap = el('div', 'map-popup');
    wrap.appendChild(el('div', 'map-popup-title', p.title));
    if (p.place) wrap.appendChild(el('div', 'map-popup-place', p.place));
    if (p.time) wrap.appendChild(el('div', 'map-popup-time', p.time));
    if (p.description) wrap.appendChild(el('div', 'map-popup-desc', p.description));
    return wrap;
}

// Penanda bulat berwarna (tanpa gambar luar, jadi tidak ada file ikon yang hilang)
function pin(kind) {
    return L.divIcon({
        className: 'map-pin-wrap',
        html: '<span class="map-pin map-pin-' + kind + '"></span>',
        iconSize: [22, 22],
        iconAnchor: [11, 11],
        popupAnchor: [0, -10],
    });
}

function draw(d, tileUrl) {
    box.replaceChildren();
    const canvas = el('div', 'map-canvas');
    box.appendChild(canvas);

    const map = L.map(canvas, { scrollWheelZoom: false });
    const tiles = L.tileLayer(tileUrl, {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
    }).addTo(map);

    // Offline / tile gagal dimuat: beri tahu sekali, marker tetap tampil
    let warned = false;
    tiles.on('tileerror', () => {
        if (warned) return;
        warned = true;
        box.appendChild(el('p', 'map-note', 'Map tiles could not be loaded (check your internet connection). Markers are still shown.'));
    });

    const bounds = [];
    const add = (p, kind) => {
        const m = L.marker([p.latitude, p.longitude], { icon: pin(kind), title: p.title }).addTo(map);
        m.bindPopup(popupContent(p));
        bounds.push([p.latitude, p.longitude]);
    };

    if (d.origin) add(d.origin, 'origin');
    if (d.destination) add(d.destination, 'destination');
    d.points.forEach((p, i) => add(p, d.current && i === d.points.length - 1 ? 'current' : 'event'));

    if (d.route.length > 1) L.polyline(d.route, { color: '#1f8a70', weight: 4, opacity: 0.9 }).addTo(map);
    if (d.remaining.length > 1) L.polyline(d.remaining, { color: '#e08a1e', weight: 3, opacity: 0.8, dashArray: '8 8' }).addTo(map);

    if (bounds.length === 1) map.setView(bounds[0], 6);
    else map.fitBounds(bounds, { padding: [30, 30], maxZoom: 8 });

    // Legenda kecil di bawah peta
    const legend = el('div', 'map-legend');
    [['origin', 'Origin'], ['destination', 'Destination'], ['event', 'Tracking point'], ['current', 'Latest position']].forEach(([k, t]) => {
        const item = el('span', 'map-legend-item');
        item.appendChild(el('span', 'map-pin map-pin-' + k));
        item.appendChild(document.createTextNode(' ' + t));
        legend.appendChild(item);
    });
    box.appendChild(legend);

    // Ukuran dihitung ulang setelah kartu tampil penuh
    setTimeout(() => map.invalidateSize(), 150);
}

if (box) {
    fetch(box.dataset.url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then((r) => { if (!r.ok) throw new Error('http'); return r.json(); })
        .then(({ data }) => {
            if (!data.available) { showMessage(data.message); return; }
            draw(data, box.dataset.tiles);
        })
        .catch(() => showMessage('The map could not be loaded right now.'));
}
