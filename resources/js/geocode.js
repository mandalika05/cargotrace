/*
 * ============================================================
 * BUAT APA : Tombol "Find coordinates" (geocoding). Mengubah nama tempat menjadi
 *            latitude/longitude lewat /geocode (Nominatim OpenStreetMap), supaya staf tidak
 *            mengetik koordinat manual.
 * CARA PAKAI di Blade: tombol dengan atribut data-geocode dan
 *   data-url     alamat /geocode
 *   data-fields  selector kolom yang dirangkai jadi kata pencarian, mis. "#city,#country"
 *   data-lat / data-lng  selector kolom latitude / longitude yang diisi
 *   data-results selector tempat daftar hasil ditampilkan
 * HASIL    : sampai 5 pilihan; staf klik satu, kolom koordinat terisi. Staf tetap bisa
 *            memeriksa dan mengubahnya sebelum menyimpan.
 * AMAN     : teks hasil dipasang dengan textContent. Gagal/offline -> pesan rapi.
 * ============================================================
 */
function note(box, text) {
    box.replaceChildren();
    const p = document.createElement('p');
    p.className = 'geo-note';
    p.textContent = text;
    box.appendChild(p);
}

document.querySelectorAll('[data-geocode]').forEach((btn) => {
    const box = document.querySelector(btn.dataset.results);
    const latInput = document.querySelector(btn.dataset.lat);
    const lngInput = document.querySelector(btn.dataset.lng);
    if (!box || !latInput || !lngInput) return;

    btn.addEventListener('click', () => {
        const query = btn.dataset.fields.split(',')
            .map((sel) => (document.querySelector(sel.trim())?.value || '').trim())
            .filter(Boolean).join(', ');

        if (query.length < 3) { note(box, 'Fill in the place name first (at least 3 characters).'); return; }

        btn.disabled = true;
        note(box, 'Searching…');

        fetch(btn.dataset.url + '?q=' + encodeURIComponent(query), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then((r) => { if (!r.ok) throw new Error('http'); return r.json(); })
            .then(({ data }) => {
                if (!data.available) { note(box, 'The place search is not reachable right now. Enter the coordinates manually.'); return; }
                if (!data.results.length) { note(box, 'No place found. Try a different name or enter the coordinates manually.'); return; }

                box.replaceChildren();
                data.results.forEach((r) => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'geo-result';
                    b.textContent = r.label;
                    b.addEventListener('click', () => {
                        latInput.value = Number(r.latitude).toFixed(7);
                        lngInput.value = Number(r.longitude).toFixed(7);
                        note(box, 'Coordinates filled. Please check them before saving.');
                    });
                    box.appendChild(b);
                });
            })
            .catch(() => note(box, 'The place search is not reachable right now. Enter the coordinates manually.'))
            .finally(() => { btn.disabled = false; });
    });
});
