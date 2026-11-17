<?php
/*
 * ============================================================
 * BUAT APA : Export CSV daftar shipment (mengikuti filter yang sedang aktif).
 * IZIN     : sama dengan daftar: customer hanya mendapat shipment miliknya (dibatasi di
 *            ShipmentService, bukan di tampilan). Maksimal 5000 baris.
 * KEAMANAN : sel yang diawali = + - @ diberi tanda kutip di depan (CSV injection di Excel).
 * EXCEL    : berkas diberi BOM UTF-8 supaya huruf non-ASCII tampil benar.
 * ============================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\ShipmentFilterRequest;
use App\Models\Shipment;
use App\Services\ShipmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    private const MAX_ROWS = 5000;

    public function __construct(private readonly ShipmentService $shipments)
    {
    }

    public function shipments(ShipmentFilterRequest $request): StreamedResponse
    {
        Gate::authorize('viewAny', Shipment::class);

        $rows = $this->shipments
            ->paginate($request->user(), $request->validated(), self::MAX_ROWS)
            ->items();

        $filename = 'cargotrace-shipments-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Shipment number', 'Customer', 'Carrier', 'Origin', 'Destination', 'Status',
                'Expected departure', 'Expected arrival', 'ETA', 'Delay', 'Delay (minutes)', 'Delivered at',
            ]);

            foreach ($rows as $s) {
                $delay = $s->delay();
                fputcsv($out, array_map([$this, 'safe'], [
                    $s->shipment_number,
                    $s->customer?->name,
                    $s->carrier?->name,
                    $s->origin?->city,
                    $s->destination?->city,
                    $s->status,
                    $s->expected_departure_at?->format('Y-m-d H:i'),
                    $s->expected_arrival_at?->format('Y-m-d H:i'),
                    $s->estimated_arrival_at?->format('Y-m-d H:i'),
                    $delay->label(),
                    $delay->delayMinutes,
                    $s->delivered_at?->format('Y-m-d H:i'),
                ]));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
