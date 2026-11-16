{{--
 ============================================================
 BUAT APA : Panel "Exceptions" di halaman detail shipment (kolom kiri).
            Semua peran yang boleh melihat shipment ini melihat daftarnya
            (customer: hanya shipment miliknya, sudah dijaga ShipmentPolicy).
            Staf juga bisa: mencatat exception baru, mulai investigasi, dan menyelesaikan
            (wajib catatan). Aturan perpindahan status dijaga ExceptionService.
 id="exceptions" : tujuan #exceptions setelah aksi.
 ============================================================
--}}
@php
    $exceptions = $shipment->exceptions()
        ->with(['reporter:id,name', 'resolver:id,name'])
        ->orderByRaw("CASE status WHEN 'OPEN' THEN 0 WHEN 'INVESTIGATING' THEN 1 ELSE 2 END")
        ->orderByRaw("CASE severity WHEN 'HIGH' THEN 0 WHEN 'MEDIUM' THEN 1 ELSE 2 END")
        ->orderByDesc('detected_at')
        ->get();
    $canReport = auth()->user()->can('create', \App\Models\ShipmentException::class) && $shipment->status !== 'CANCELLED';
    $failedId = (int) old('exception_id');
@endphp
<div id="exceptions" class="card panel">
    <h2 class="panel-title">Exceptions @if ($exceptions->isNotEmpty())<span class="cell-sub" style="margin: 0 0 0 .25rem">({{ $exceptions->count() }})</span>@endif</h2>

    @if ($exceptions->isEmpty())
        <p class="exc-empty">No exceptions reported for this shipment.</p>
    @else
        <div class="exc-list">
            @foreach ($exceptions as $exception)
                <div class="exc-card {{ $exception->isResolved() ? 'exc-card-resolved' : ($exception->severity === 'HIGH' ? 'exc-card-high' : ($exception->severity === 'LOW' ? 'exc-card-low' : '')) }}">
                    <div class="exc-head">
                        <span class="exc-title">{{ $exception->label() }}</span>
                        <span class="chip {{ $exception->severityChip() }}">{{ $exception->severity }}</span>
                        <span class="chip {{ $exception->statusChip() }}">{{ strtoupper($exception->status) }}</span>
                    </div>
                    <div class="exc-meta">
                        Detected {{ $exception->detected_at->format('d M Y, H:i') }}@if ($exception->reporter) by {{ $exception->reporter->name }}@endif
                        @if ($exception->resolved_at) &middot; Resolved {{ $exception->resolved_at->format('d M Y, H:i') }}@if ($exception->resolver) by {{ $exception->resolver->name }}@endif @endif
                    </div>
                    @if ($exception->description) <div class="exc-desc">{{ $exception->description }}</div> @endif
                    @if ($exception->resolution_note) <div class="exc-resolution"><strong>Resolution:</strong> {{ $exception->resolution_note }}</div> @endif

                    @can('update', $exception)
                        <div class="exc-actions">
                            @if ($exception->status === 'OPEN')
                                <form method="POST" action="{{ route('exceptions.update', $exception) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="INVESTIGATING">
                                    <button type="submit" class="btn-ghost btn-sm">Start investigation</button>
                                </form>
                            @endif
                            <details class="fold fold-inline" style="flex: 1 1 14rem" @if ($failedId === $exception->id) open @endif>
                                <summary>Resolve</summary>
                                <div class="fold-body">
                                    <form method="POST" action="{{ route('exceptions.update', $exception) }}" class="form-stack">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="RESOLVED">
                                        <input type="hidden" name="exception_id" value="{{ $exception->id }}">
                                        <div>
                                            <label for="note-{{ $exception->id }}" class="field-label">How was it resolved?</label>
                                            <textarea id="note-{{ $exception->id }}" name="note" rows="2" maxlength="1000" class="field-input" required>{{ $failedId === $exception->id ? old('note') : '' }}</textarea>
                                            @if ($failedId === $exception->id)
                                                @error('resolution_note') <p class="field-error">{{ $message }}</p> @enderror
                                                @error('status') <p class="field-error">{{ $message }}</p> @enderror
                                            @endif
                                        </div>
                                        <button type="submit" class="btn-primary btn-sm">Mark as resolved</button>
                                    </form>
                                </div>
                            </details>
                        </div>
                    @endcan
                </div>
            @endforeach
        </div>
    @endif

    @if ($canReport)
        <details class="fold" @if ($errors->has('type') || $errors->has('severity') || $errors->has('detected_at')) open @endif>
            <summary><x-icon name="plus" /> Report an exception</summary>
            <div class="fold-body">
                <form method="POST" action="{{ route('shipments.exceptions.store', $shipment) }}" class="form-stack">
                    @csrf
                    <div class="form-grid">
                        <div>
                            <label for="exc_type" class="field-label">Type</label>
                            <select id="exc_type" name="type" class="field-input" required>
                                @foreach (\App\Models\ShipmentException::TYPES as $type)
                                    <option value="{{ $type }}" @selected(old('type') === $type)>{{ \App\Models\ShipmentException::typeLabel($type) }}</option>
                                @endforeach
                            </select>
                            @error('type') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="exc_severity" class="field-label">Severity</label>
                            <select id="exc_severity" name="severity" class="field-input" required>
                                @foreach (\App\Models\ShipmentException::SEVERITIES as $severity)
                                    <option value="{{ $severity }}" @selected(old('severity', 'MEDIUM') === $severity)>{{ ucfirst(strtolower($severity)) }}</option>
                                @endforeach
                            </select>
                            @error('severity') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label for="exc_detected" class="field-label">Detected at</label>
                        <input id="exc_detected" name="detected_at" type="datetime-local" class="field-input" value="{{ old('detected_at', now()->format('Y-m-d\TH:i')) }}">
                        @error('detected_at') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="exc_desc" class="field-label">Description</label>
                        <textarea id="exc_desc" name="description" rows="3" maxlength="1000" class="field-input" placeholder="What happened?">{{ old('description') }}</textarea>
                        @error('description') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="btn-primary"><x-icon name="plus" /> Report exception</button>
                </form>
            </div>
        </details>
    @endif
</div>
