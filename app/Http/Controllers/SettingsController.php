<?php
/*
 * ============================================================
 * BUAT APA : Halaman Settings (khusus Admin): mengubah ambang delay LOW/MEDIUM/HIGH
 *            tanpa menyentuh kode (syarat slide 8: "threshold configurable").
 * ============================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\SettingsRequest;
use App\Models\Setting;
use App\Services\EtaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(EtaService $eta): View
    {
        return view('settings.edit', ['thresholds' => $eta->thresholds()]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        foreach ($request->validated() as $key => $value) {
            Setting::putValue($key, $value);
        }

        return redirect()->route('settings.edit')->with('success', 'Delay thresholds saved.');
    }
}
