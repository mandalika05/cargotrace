<?php
/*
 * ============================================================
 * BUAT APA : Halaman LACAK PUBLIK /track (tanpa login): siapa pun yang punya nomor
 *            shipment bisa melihat status dan perjalanan.
 * PRIVASI  : hanya data aman: status, rute (kota), jadwal, lencana delay, dan riwayat
 *            perjalanan (jenis event + tempat + waktu). TIDAK ditampilkan: nama customer,
 *            carrier, catatan internal, exception, atau berat/isi kargo.
 * KEAMANAN : dibatasi 20 pencarian per menit per alamat IP; input divalidasi.
 * ============================================================
 */

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class PublicTrackingController extends Controller
{
    public function __invoke(Request $request): View
    {
        $number = trim((string) $request->query('number', ''));
        $shipment = null;
        $searched = $number !== '';

        $error = null;

        if ($searched) {
            // Validasi manual (bukan redirect) supaya pesan tampil di halaman yang sama
            $validator = Validator::make(['number' => $number], ['number' => ['string', 'max:40', 'regex:/^[A-Za-z0-9\-]+$/']], [
                'number.regex' => 'Use only letters, numbers and dashes, e.g. CT-24771248-abcd.',
            ]);

            if ($validator->fails()) {
                $error = $validator->errors()->first('number');
            } else {
                // Huruf besar/kecil tidak dibedakan (nomor memuat huruf kecil di belakang)
                $shipment = Shipment::query()
                    ->whereRaw('LOWER(shipment_number) = ?', [mb_strtolower($number)])
                    ->with(['origin:id,city,country', 'destination:id,city,country'])
                    ->first();
            }
        }

        $events = collect();
        if ($shipment) {
            $events = $shipment->trackingEvents()
                ->whereNotIn('event_type', \App\Models\TrackingEvent::MARKER_TYPES)
                ->chronological()
                ->get();
        }

        return view('track.index', [
            'number' => $number,
            'searched' => $searched,
            'error' => $error,
            'shipment' => $shipment,
            'delay' => $shipment?->delay(),
            'events' => $events,
        ]);
    }
}
