{{-- resources/views/sanitation/dashboard/partials/driver-route-planner.blade.php --}}

{{--
    Driver Route Planner
    --------------------
    Expected variables:
      - $driver         : App\Models\SanitationPersonnel (the current driver)
      - $driverRoute    : array  { stops, stop_count, total_weight, zone, map_center, generated_at }
      - $driverVehicle  : array  { number, type, zone }

    Requires the following CSS classes from the main dashboard blade:
      .route-chip, .route-chip--muted
      .route-stop, .route-stop__index, .route-stop__body, .route-stop__actions
      .icon-btn, .icon-btn--success
      .perf-tile, .empty-state
      .priority-badge, .priority-badge--{priority}
      .status-dot, .status-dot--info
      .btn-primary, .btn-outline, .btn-sm
--}}

@php
    $stops = $driverRoute['stops'] ?? collect();

    // ✅ NEW: pre-compute a few things once instead of inside the loop
    $highPriorityCount = $stops->filter(fn ($s) => in_array($s->priority, ['emergency', 'high'], true))->count();

    // ✅ FIX: build the multi-stop Google Maps "directions" URL from the
    //    ordered stops. Google accepts up to ~9 waypoints in the URL form,
    //    so we cap the list to avoid silently truncated routes.
    $routeUrl = null;
    if ($stops->count() > 0) {
        $withCoords = $stops->filter(fn ($s) => $s->property && $s->property->latitude && $s->property->longitude);

        if ($withCoords->count() > 0) {
            $points = $withCoords->map(function ($s) {
                return $s->property->latitude . ',' . $s->property->longitude;
            })->values();

            $origin      = $points->first();
            $destination = $points->last();
            $waypoints   = $points->slice(1, -1)->take(9); // max 9 waypoints

            $routeUrl = 'https://www.google.com/maps/dir/?api=1'
                . '&origin='      . urlencode($origin)
                . '&destination=' . urlencode($destination)
                . '&travelmode=driving';

            if ($waypoints->isNotEmpty()) {
                $routeUrl .= '&waypoints=' . urlencode($waypoints->implode('|'));
            }
        }
    }
@endphp

<div class="card p-6 mb-6" id="driver-route-planner">

    {{-- ============================================ --}}
    {{-- Header: title + vehicle chips               --}}
    {{-- ============================================ --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
        <h3 class="font-semibold" style="color: var(--text-primary);">
            <i class="fas fa-route mr-2" style="color: var(--primary);"></i>
            Today's Route
            @if(($driverRoute['zone'] ?? null))
                <span class="ml-2 text-xs font-normal" style="color: var(--text-secondary);">
                    · {{ $driverRoute['zone'] }}
                </span>
            @endif
        </h3>

        <div class="flex items-center gap-2 flex-wrap">
            @if(!empty($driverVehicle['zone']))
                <span class="route-chip">
                    <i class="fas fa-map-marker-alt mr-1"></i>{{ $driverVehicle['zone'] }}
                </span>
            @endif

            @if(!empty($driverVehicle['number']))
                <span class="route-chip">
                    <i class="fas fa-truck mr-1"></i>{{ $driverVehicle['number'] }}
                </span>
            @endif

            @if(!empty($driverVehicle['type']))
                <span class="route-chip route-chip--muted">
                    {{ ucfirst($driverVehicle['type']) }}
                </span>
            @endif

            @if(empty($driverVehicle['number']) && empty($driverVehicle['type']) && empty($driverVehicle['zone']))
                <span class="route-chip route-chip--muted">
                    <i class="fas fa-info-circle mr-1"></i>No vehicle assigned
                </span>
            @endif
        </div>
    </div>

    {{-- ============================================ --}}
    {{-- Summary strip                                --}}
    {{-- ============================================ --}}
    <div class="grid grid-cols-3 gap-3 mb-4">
        <div class="perf-tile">
            <div class="text-2xl font-bold" style="color: var(--primary);">
                {{ $driverRoute['stop_count'] ?? 0 }}
            </div>
            <div class="text-xs" style="color: var(--text-secondary);">Stops</div>
        </div>

        <div class="perf-tile">
            <div class="text-2xl font-bold" style="color: var(--info);">
                {{ number_format($driverRoute['total_weight'] ?? 0, 1) }}
            </div>
            <div class="text-xs" style="color: var(--text-secondary);">Est. Load (kg)</div>
        </div>

        {{-- ✅ FIX: replaced the duplicate "Remaining" tile with something
             that actually provides new information --}}
        <div class="perf-tile">
            <div class="text-2xl font-bold"
                 style="color: {{ $highPriorityCount > 0 ? 'var(--warning)' : 'var(--success)' }};">
                {{ $highPriorityCount }}
            </div>
            <div class="text-xs" style="color: var(--text-secondary);">High Priority</div>
        </div>
    </div>

    {{-- ============================================ --}}
    {{-- Stop list                                    --}}
    {{-- ============================================ --}}
    @if($stops->count() > 0)
        <div class="space-y-3">
            @foreach($stops as $index => $stop)
                @php
                    $property  = $stop->property;
                    $hasCoords = $property && $property->latitude && $property->longitude;
                    $digital   = $property?->digital_address;
                    $city      = $property?->city;
                @endphp

                <div class="route-stop">
                    <div class="route-stop__index">{{ $index + 1 }}</div>

                    <div class="route-stop__body">
                        {{-- ✅ FIX: null-safe access on $property --}}
                        <div class="font-medium truncate" style="color: var(--text-primary);">
                            {{ $property?->property_name ?? 'Unknown Property' }}
                        </div>

                        @if($digital || $city)
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                @if($digital)
                                    <i class="fas fa-map-pin mr-1"></i>
                                    {{ $digital }}
                                @endif
                                @if($digital && $city)
                                    <span class="mx-1">•</span>
                                @endif
                                @if($city)
                                    {{ $city }}
                                @endif
                            </div>
                        @endif

                        <div class="text-xs mt-1 flex items-center gap-2 flex-wrap" style="color: var(--text-secondary);">
                            @if($stop->priority)
                                <span class="priority-badge priority-badge--{{ $stop->priority }}">
                                    {{ ucfirst($stop->priority) }}
                                </span>
                            @endif

                            @if($stop->status)
                                <span class="status-dot status-dot--info"></span>
                                {{ ucfirst($stop->status) }}
                            @endif

                            @if($stop->waste_type)
                                <span class="mx-1">•</span>
                                <span>{{ ucfirst($stop->waste_type) }}</span>
                            @endif

                            @if($stop->waste_weight_kg)
                                <span class="mx-1">•</span>
                                <span>{{ number_format((float) $stop->waste_weight_kg, 1) }} kg</span>
                            @endif
                        </div>
                    </div>

                    <div class="route-stop__actions">
                        @if($hasCoords)
                            <a href="https://www.google.com/maps/dir/?api=1&destination={{ $property->latitude }},{{ $property->longitude }}"
                               target="_blank"
                               rel="noopener"
                               class="icon-btn"
                               title="Navigate to this stop">
                                <i class="fas fa-directions"></i>
                            </a>
                        @endif

                        <button type="button"
                                onclick="markStopComplete({{ $stop->id }})"
                                class="icon-btn icon-btn--success"
                                title="Mark stop as completed">
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="empty-state">
            <i class="fas fa-route"></i>
            <p>No stops assigned for today</p>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                Approved collection requests assigned to you or your zone will appear here.
            </p>
        </div>
    @endif

    {{-- ============================================ --}}
    {{-- Action bar                                   --}}
    {{-- ============================================ --}}
    <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t" style="border-color: var(--border-color);">
        @if($routeUrl)
            {{-- ✅ FIX: Start Route now opens the full ordered route, not just the first stop --}}
            <a href="{{ $routeUrl }}"
               target="_blank"
               rel="noopener"
               class="btn-primary btn-sm">
                <i class="fas fa-play mr-1"></i> Start Route
            </a>
        @endif

        <a href="{{ route('sanitation.requests.index') }}" class="btn-outline btn-sm">
            <i class="fas fa-list mr-1"></i> All Requests
        </a>

        <a href="{{ route('sanitation.profile.edit') }}" class="btn-outline btn-sm">
            <i class="fas fa-truck mr-1"></i> Vehicle Settings
        </a>
    </div>
</div>

{{-- ============================================================ --}}
{{-- Scripts — idempotent: safe to include multiple times          --}}
{{-- ============================================================ --}}
@push('scripts')
@once
<script>
    // ✅ FIX: resolve the status route from Blade so subdirectory installs work.
    //    '__ID__' is swapped for the request ID at runtime.
    window.__driverStatusRouteTemplate = @json(
        route('sanitation.requests.status', ['collectionRequest' => '__ID__'])
    );

    // ---- Mark a stop as completed ----
    window.markStopComplete = function (id) {
        if (!confirm('Mark this stop as completed?')) return;

        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        if (!tokenMeta) {
            alert('CSRF token missing — please refresh the page.');
            return;
        }

        const url = window.__driverStatusRouteTemplate.replace('__ID__', id);

        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': tokenMeta.content,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ status: 'completed' }),
        })
        .then(r => r.json().then(data => ({ ok: r.ok, data })))
        .then(({ ok, data }) => {
            if (ok && data.success) {
                location.reload();
            } else {
                alert(data.message || 'Failed to mark stop complete');
            }
        })
        .catch(() => alert('Network error — please try again'));
    };

    // ✅ NOTE: `startRoute()` is no longer needed — the "Start Route" button
    //    is now a plain <a href> pointing at the multi-stop Google Maps URL
    //    that was built server-side. Removing the JS avoids the "opens first
    //    stop only" behaviour.
</script>
@endonce
@endpush