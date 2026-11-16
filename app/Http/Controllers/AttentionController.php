<?php
/*
 * ============================================================
 * BUAT APA : Halaman "Needs attention": antrean shipment telat / bermasalah,
 *            paling gawat di atas. Khusus staf (dijaga middleware role di route).
 * ============================================================
 */

namespace App\Http\Controllers;

use App\Services\AttentionService;
use App\Services\EtaService;
use Illuminate\View\View;

class AttentionController extends Controller
{
    public function __invoke(AttentionService $attention, EtaService $eta): View
    {
        return view('attention.index', [
            'queue' => $attention->queue(),
            'thresholds' => $eta->thresholds(),
        ]);
    }
}
