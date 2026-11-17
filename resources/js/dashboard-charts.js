/*
 * ============================================================
 * BUAT APA : Menggambar grafik dashboard dengan Chart.js (di-bundle lewat npm, jadi
 *            JALAN OFFLINE, tidak memakai CDN). Gaya meniru mockup dosen: warna-warni.
 * DATA     : tiap <canvas data-chart='{"type":"bar","labels":[...],"values":[...],"colors":[...]}'>
 *            diisi oleh DashboardService lewat resources/views/dashboard/*.blade.php.
 * TIPE     : bar (tegak atau mendatar bila "horizontal":true), line, doughnut.
 * RAPI     : - label panjang dipecah beberapa baris (wrap) supaya TIDAK saling menimpa
 *            - angka ditulis di ujung batang (plugin valueLabels)
 *            - ada ruang ekstra (grace/padding) supaya angka tidak terpotong
 * ============================================================
 */
import Chart from 'chart.js/auto';

const ink = '#0f2f2a';
const grid = 'rgba(15, 47, 42, 0.08)';
// Harus sama dengan DashboardService::PALETTE
const palette = ['#2b7fb5', '#1f9d6b', '#e8892a', '#d64545', '#8b6fd1', '#14b8a6', '#f4b740', '#8fa39b'];
const family = "'Plus Jakarta Sans', system-ui, sans-serif";

Chart.defaults.font.family = family;
Chart.defaults.color = ink;
Chart.defaults.maintainAspectRatio = false;

// "Open exceptions" -> ["Open", "exceptions"]: tiap baris paling banyak `max` huruf
function wrap(text, max) {
    const lines = [];
    let line = '';
    String(text).split(' ').forEach((word) => {
        if (line && (line + ' ' + word).length > max) {
            lines.push(line);
            line = word;
        } else {
            line = line ? line + ' ' + word : word;
        }
    });
    if (line) lines.push(line);
    return lines;
}

// Menulis angka di ujung tiap batang (atas untuk tegak, kanan untuk mendatar)
const valueLabels = {
    id: 'valueLabels',
    afterDatasetsDraw(chart) {
        if (chart.config.type !== 'bar') return;
        const horizontal = chart.options.indexAxis === 'y';
        const ctx = chart.ctx;
        chart.getDatasetMeta(0).data.forEach((bar, i) => {
            ctx.save();
            ctx.fillStyle = ink;
            ctx.font = '700 12px ' + family;
            ctx.textBaseline = 'middle';
            if (horizontal) {
                ctx.textAlign = 'left';
                ctx.fillText(chart.data.datasets[0].data[i], bar.x + 6, bar.y);
            } else {
                ctx.textAlign = 'center';
                ctx.fillText(chart.data.datasets[0].data[i], bar.x, bar.y - 10);
            }
            ctx.restore();
        });
    },
};

function build(cfg) {
    const colors = cfg.colors && cfg.colors.length ? cfg.colors : cfg.values.map((_, i) => palette[i % palette.length]);

    if (cfg.type === 'doughnut') {
        return {
            type: 'doughnut',
            data: { labels: cfg.labels, datasets: [{ data: cfg.values, backgroundColor: colors, borderColor: '#ffffff', borderWidth: 3 }] },
            options: {
                cutout: '66%',
                plugins: { legend: { display: cfg.legend !== false, position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } } },
            },
        };
    }

    if (cfg.type === 'line') {
        return {
            type: 'line',
            data: {
                labels: cfg.labels.map((l) => wrap(l, 10)),
                datasets: [{
                    label: 'Shipments',
                    data: cfg.values,
                    borderWidth: 4,
                    tension: 0.35,
                    fill: true,
                    backgroundColor: 'rgba(43, 127, 181, 0.10)',
                    borderColor: palette[0],
                    // garis berganti warna di tiap ruas, titiknya juga berwarna-warni
                    segment: { borderColor: (c) => palette[c.p0DataIndex % palette.length] },
                    pointBackgroundColor: colors,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                }],
            },
            options: {
                layout: { padding: { top: 12, right: 12 } },
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: false } },
                    y: { beginAtZero: true, grace: '10%', grid: { color: grid }, ticks: { precision: 0 } },
                },
            },
        };
    }

    // bar (tegak atau mendatar)
    const horizontal = cfg.horizontal === true;
    return {
        type: 'bar',
        data: {
            labels: cfg.labels.map((l) => wrap(l, horizontal ? 18 : 10)),
            datasets: [{ label: 'Total', data: cfg.values, backgroundColor: colors, borderWidth: 0, borderRadius: 8, maxBarThickness: horizontal ? 28 : 56 }],
        },
        options: {
            indexAxis: horizontal ? 'y' : 'x',
            layout: { padding: horizontal ? { right: 28 } : { top: 22 } },
            plugins: { legend: { display: false } },
            scales: horizontal
                ? {
                    x: { beginAtZero: true, grace: '8%', grid: { color: grid }, ticks: { precision: 0 } },
                    y: { grid: { display: false } },
                }
                : {
                    x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: false } },
                    y: { beginAtZero: true, grace: '8%', grid: { color: grid }, ticks: { precision: 0 } },
                },
        },
        plugins: [valueLabels],
    };
}

document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
    try {
        new Chart(canvas, build(JSON.parse(canvas.dataset.chart)));
    } catch (e) {
        // Data rusak: ganti canvas dengan pesan, halaman tetap jalan
        canvas.replaceWith(Object.assign(document.createElement('p'), { className: 'chart-empty', textContent: 'The chart could not be drawn.' }));
    }
});
