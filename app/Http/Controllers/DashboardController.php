<?php
/*
 * ============================================================
 * BUAT APA : Halaman utama setelah login. Menampilkan angka RINGKAS dari
 *            database (bukan angka statis) + 5 shipment terbaru.
 *            Customer hanya menghitung shipment miliknya sendiri.
 * NANTI    : P13 menambah grafik, peta, antrean "Needs attention",
 *            delay rate, dan open exceptions.
 * ============================================================
 */

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $base = Shipment::query();
        if ($user->isCustomer()) {
            $base->where('customer_id', $user->id);
        }

        // Satu query: jumlah per status. Contoh hasil: ['IN_TRANSIT' => 4, 'DELIVERED' => 3]
        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $recent = (clone $base)
            ->with(['origin:id,city', 'destination:id,city'])
            ->latest('id')
            ->limit(5)
            ->get();

        return view('dashboard', [
            'stats' => [
                'total' => (int) $counts->sum(),
                'in_transit' => (int) (($counts['IN_TRANSIT'] ?? 0) + ($counts['PICKED_UP'] ?? 0)),
                'delivered' => (int) ($counts['DELIVERED'] ?? 0),
                'pending' => (int) (($counts['CREATED'] ?? 0) + ($counts['BOOKED'] ?? 0)),
            ],
            'recent' => $recent,
        ]);
    }
}
