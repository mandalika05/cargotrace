{{--
 ============================================================
 BUAT APA : Bagian TRACKING di halaman detail shipment:
            1) kartu Lokasi terkini  2) timeline kronologis
            3) form tambah event (staf, shipment belum terminal)
            4) prosedur reopen (Admin, shipment terminal)
 DISISIPKAN di shipments/show.blade.php dengan satu baris:
     @include('shipments.partials.tracking')
 CATATAN  : query timeline ada di sini (loadMissing) supaya ShipmentController
            yang sudah final tidak perlu diubah.
 URUTAN   : timeline kronologis (paling lama di atas, terbaru di bawah, diberi
            lencana "Latest"). Event "Shipment created" diambil dari created_at.
 ============================================================
--}}
@php
    $events = $shipment->trackingEvents()->with(['creator:id,name'])->chronological()->get();
    $latestId = $events->last()?->id;
    $current = $shipment->currentLocationEvent();
    $nextStatuses = $shipment->allowedNextStatuses();
    $canAddEvent = auth()->user()->can('update', $shipment);
    $isMoving = in_array($shipment->status, ['PICKED_UP', 'IN_TRANSIT'], true);
    $eventTypes = collect($nextStatuses)->mapWithKeys(fn ($s) => [$s => ucfirst(strtolower(str_replace('_', ' ', $s)))]);
    if ($isMoving) { $eventTypes->put('LOCATION_UPDATE', 'Location update (no status change)'); }
    $locations = $canAddEvent ? \App\Models\Location::orderBy('name')->get(['id', 'name', 'city', 'country']) : collect();
@endphp

<div id="timeline" class="page-body">
    <div class="detail-grid">

        {{-- ===== Timeline ===== --}}
        <div class="detail-main">
            <div class="card panel">
                <h2 class="panel-title">Tracking timeline</h2>

                <ol class="tl-wrap">
                    <li class="tl-item">
                        <span class="tl-dot tl-dot-neutral"></span>
                        <div class="tl-head">
                            <span class="tl-title">Shipment created</span>
                            <span class="tl-time">{{ $shipment->created_at->format('d M Y, H:i') }}</span>
                        </div>
                        <div class="tl-place">{{ $shipment->origin->name }}, {{ $shipment->origin->country }}</div>
                        @if ($shipment->creator) <div class="tl-by">by {{ $shipment->creator->name }}</div> @endif
                    </li>

                    @foreach ($events as $event)
                        <li class="tl-item">
                            <span class="tl-dot {{ $event->dotClass() }}"></span>
                            <div class="tl-head">
                                <span class="tl-title">{{ $event->label() }}@if ($event->id === $latestId)<span class="tl-latest-badge">Latest</span>@endif</span>
                                <span class="tl-time">{{ $event->occurred_at->format('d M Y, H:i') }}</span>
                            </div>
                            @if ($event->placeLabel()) <div class="tl-place">{{ $event->placeLabel() }}</div> @endif
                            @if ($event->description) <div class="tl-desc">{{ $event->description }}</div> @endif
                            @if ($event->creator) <div class="tl-by">by {{ $event->creator->name }}</div> @endif
                        </li>
                    @endforeach
                </ol>

                @if ($events->isEmpty())
                    <p class="cell-sub" style="margin-top: 1rem">No tracking events yet. Updates will appear here as the shipment moves.</p>
                @endif
            </div>
        </div>

        {{-- ===== Sisi kanan: lokasi terkini + tindakan ===== --}}
        <div class="detail-side">
            <div class="card panel">
                <div class="current-loc">
                    <div class="current-loc-icon"><x-icon name="map-pin" class="icon-lg" /></div>
                    <div>
                        <div class="current-loc-label">Current location</div>
                        @if ($current)
                            <div class="current-loc-name">{{ $current->placeLabel() }}</div>
                            <div class="current-loc-meta">Updated {{ $current->occurred_at->format('d M Y, H:i') }}</div>
                        @else
                            <div class="current-loc-name">{{ $shipment->origin->city }}</div>
                            <div class="current-loc-meta">Not yet moving (at origin)</div>
                        @endif
                    </div>
                </div>
            </div>

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
        </div>
    </div>
</div>
