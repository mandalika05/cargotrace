{{--
 ============================================================
 BUAT APA : Panel tindakan di kolom kanan (bawah):
            1) Form "Add tracking event" (staf, shipment belum terminal)
            2) Panel "Reopen shipment" (hanya Admin, shipment terminal)
 DATA     : $eventTypes, $locations, $canAddEvent dari show.blade.php.
 ============================================================
--}}
@php
    // Pengaman: hitung sendiri kalau belum disiapkan oleh show.blade.php.
    $canAddEvent ??= auth()->user()->can('update', $shipment);
    if (! isset($eventTypes)) {
        $eventTypes = collect($shipment->allowedNextStatuses())
            ->mapWithKeys(fn ($s) => [$s => ucfirst(strtolower(str_replace('_', ' ', $s)))]);
        if (in_array($shipment->status, ['PICKED_UP', 'IN_TRANSIT'], true)) {
            $eventTypes->put('LOCATION_UPDATE', 'Location update (no status change)');
        }
    }
    $locations ??= $canAddEvent ? \App\Models\Location::orderBy('name')->get(['id', 'name', 'city', 'country']) : collect();
@endphp
{{-- ===== Form tambah event (staf) ===== --}}
@if ($canAddEvent && $eventTypes->isNotEmpty())
    <div class="card panel">
        <h2 class="panel-title">Add tracking event</h2>
        <div class="hint-box">
            Current status: <strong>{{ $shipment->statusLabel() }}</strong>.
            Allowed next: {{ $eventTypes->implode(', ') }}.
        </div>

        <form method="POST" action="{{ route('shipments.tracking.store', $shipment) }}" class="form-stack">
            @csrf
            <div>
                <label for="event_type" class="field-label">Event</label>
                <select id="event_type" name="event_type" class="field-input" required>
                    @foreach ($eventTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('event_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('event_type') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="occurred_at" class="field-label">Date and time</label>
                <input id="occurred_at" name="occurred_at" type="datetime-local" value="{{ old('occurred_at', now()->format('Y-m-d\TH:i')) }}" class="field-input" required>
                @error('occurred_at') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="location_id" class="field-label">Location (from list)</label>
                <select id="location_id" name="location_id" class="field-input">
                    <option value="">None</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected((string) old('location_id') === (string) $location->id)>{{ $location->name }} ({{ $location->city }})</option>
                    @endforeach
                </select>
                @error('location_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="location_name" class="field-label">Or place name</label>
                <input id="location_name" name="location_name" type="text" value="{{ old('location_name') }}" class="field-input" maxlength="150" placeholder="e.g. Suez Canal, Egypt">
                @error('location_name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-grid">
                <div>
                    <label for="latitude" class="field-label">Latitude</label>
                    <input id="latitude" name="latitude" type="number" step="0.0000001" min="-90" max="90" value="{{ old('latitude') }}" class="field-input" placeholder="optional">
                    @error('latitude') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="longitude" class="field-label">Longitude</label>
                    <input id="longitude" name="longitude" type="number" step="0.0000001" min="-180" max="180" value="{{ old('longitude') }}" class="field-input" placeholder="optional">
                    @error('longitude') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="description" class="field-label">Description</label>
                <textarea id="description" name="description" rows="3" maxlength="1000" class="field-input" placeholder="What happened?">{{ old('description') }}</textarea>
                @error('description') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="btn-primary"><x-icon name="plus" /> Add event</button>
        </form>
    </div>
@endif

{{-- ===== Prosedur khusus Admin: reopen ===== --}}
@if ($shipment->isTerminal() && auth()->user()->isAdmin())
    <div class="card panel">
        <h2 class="panel-title">Reopen shipment</h2>
        <div class="hint-box">
            This shipment is <strong>{{ $shipment->statusLabel() }}</strong>. Reopening is a special administrator
            procedure. A reason is required and it is recorded on the timeline.
        </div>
        <form method="POST" action="{{ route('shipments.reopen', $shipment) }}" class="form-stack">
            @csrf
            <div>
                <label for="reason" class="field-label">Reason</label>
                <textarea id="reason" name="reason" rows="3" maxlength="500" class="field-input" required>{{ old('reason') }}</textarea>
                @error('reason') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-danger" onclick="return confirm('Reopen this shipment?')">Reopen shipment</button>
        </form>
    </div>
@endif
