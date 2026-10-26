{{-- resources/views/partials/ad-card.blade.php --}}
@php
    use App\Models\SystemSetting;
    use Illuminate\Support\Facades\Storage;

    // ----------------------------------------------------------------
    // ✅ IMPROVED: Defensive guards on the ad + unit relations.
    //
    //   The caller is expected to eager-load `unit.photos` and
    //   `unit.property`, but if it forgets, the card should not
    //   throw a 500 — it should degrade to the empty-state.
    // ----------------------------------------------------------------
    $ad   = $ad ?? null;
    $unit = $ad?->unit;

    if (!$ad || !$unit) {
        // Fail silently — a broken ad row should never break the page.
        return;
    }

    // ----------------------------------------------------------------
    // ✅ IMPROVED: Cover photo resolution.
    //   Prefers the explicit cover, falls back to the first photo,
    //   then null (which triggers the gradient placeholder below).
    // ----------------------------------------------------------------
    $photos = $unit->photos ?? collect();
    $cover  = $photos->firstWhere('is_cover', true) ?? $photos->first();

    // ----------------------------------------------------------------
    // ✅ IMPROVED: Safe URL resolution.
    //   Wrapped in try/catch so a bad storage path (deleted file,
    //   misconfigured disk) doesn't blow up the whole card grid.
    // ----------------------------------------------------------------
    $coverUrl = null;
    if ($cover) {
        try {
            $disk     = $cover->disk ?? 'public';
            $path     = $cover->thumbnail_path ?? $cover->path ?? null;
            $coverUrl = $path ? Storage::disk($disk)->url($path) : null;
        } catch (\Throwable $e) {
            $coverUrl = null;
        }
    }

    // ----------------------------------------------------------------
    // ✅ IMPROVED: Currency-aware price fallback.
    //   The original hardcoded ₵ in the null branch, which is wrong
    //   for any non-GHS deployment. Now uses the system formatter.
    // ----------------------------------------------------------------
    $systemSettings = SystemSetting::getSettings();
    $priceDisplay   = $unit->rent_formatted
        ?? ($unit->monthly_rent
            ? $systemSettings->formatAmount($unit->monthly_rent)
            : null);

    // ----------------------------------------------------------------
    // ✅ IMPROVED: Human-readable fallbacks for unit label.
    // ----------------------------------------------------------------
    $unitLabel = $unit->unit_name
        ?? $unit->unit_number
        ?? ('Unit #' . $unit->id);

    $locationLine = $unit->property?->address
        ?? $unit->property?->full_location   // ✅ NEW: use the accessor from the Property model
        ?? 'Location not specified';

    // ----------------------------------------------------------------
    // ✅ IMPROVED: Defensive status guard.
    //   The card should only render for a live campaign. Even if the
    //   parent query is wrong, we don't want to show a cancelled ad.
    // ----------------------------------------------------------------
    $isLive = $ad->status === \App\Models\AdCampaign::STATUS_ACTIVE
        && (!$ad->ends_at || $ad->ends_at->isFuture());
@endphp

@if($isLive)
    <article class="card overflow-hidden hover:shadow-xl transition-all duration-300 group relative">

        {{-- ============================================================
             ✅ IMPROVED: Stretched link covering the entire card.
             One <a> is the "real" link (keyboard-accessible, has the
             descriptive text). The overlay makes the whole card clickable
             without nesting links inside links (which is invalid HTML).
             ============================================================ --}}
        <a href="{{ route('ads.click', $ad->uuid) }}"
           class="absolute inset-0 z-10"
           aria-label="View {{ $unitLabel }} — sponsored listing"
           data-track="ad-card-click"
           data-ad-uuid="{{ $ad->uuid }}"
           data-ad-position="{{ $loop->index ?? 0 }}"></a>

        {{-- ============================================================
             Cover image (or gradient placeholder)
             ============================================================ --}}
        <div class="relative aspect-video overflow-hidden bg-gray-100">
            @if($coverUrl)
                {{--
                    ✅ IMPROVED: explicit width/height attributes to
                    reserve space and eliminate Cumulative Layout Shift.
                    The rendered size is governed by CSS; these are the
                    intrinsic ratio hints for the browser.
                --}}
                <img src="{{ $coverUrl }}"
                     alt="{{ $unitLabel }}"
                     width="640"
                     height="360"
                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                     loading="lazy"
                     decoding="async"
                     onerror="this.onerror=null; this.src=''; this.parentElement.classList.add('img-fallback-active');">
            @else
                <div class="w-full h-full flex items-center justify-center gradient-bg">
                    <i class="fas fa-building text-white text-4xl opacity-50" aria-hidden="true"></i>
                </div>
            @endif

            {{-- Fallback overlay shown when the image fails to load --}}
            <div class="img-fallback absolute inset-0 items-center justify-center gradient-bg hidden">
                <i class="fas fa-building text-white text-4xl opacity-50" aria-hidden="true"></i>
            </div>

            {{-- Sponsored badge --}}
            <span class="absolute top-3 left-3 z-20 px-2 py-1 rounded-md text-[11px] font-semibold shadow pointer-events-none"
                  style="background: rgba(var(--primary-rgb), 0.92); color: white; backdrop-filter: blur(4px);">
                <i class="fas fa-star mr-1" aria-hidden="true"></i> Sponsored
            </span>
        </div>

        {{-- ============================================================
             Card body
             ============================================================ --}}
        <div class="p-5">
            {{-- Unit name --}}
            <h3 class="font-semibold text-lg mb-1 truncate"
                style="color: var(--text-primary);"
                title="{{ $unitLabel }}">
                {{ $unitLabel }}
            </h3>

            {{-- Location --}}
            <p class="text-sm flex items-center mb-3 min-w-0"
               style="color: var(--text-secondary);"
               title="{{ $locationLine }}">
                <i class="fas fa-map-marker-alt mr-1.5 flex-shrink-0" aria-hidden="true"></i>
                <span class="truncate">{{ $locationLine }}</span>
            </p>

            {{-- Price + specs --}}
            <div class="flex justify-between items-center mb-4">
                <div>
                    @if($priceDisplay)
                        <span class="text-2xl font-bold" style="color: var(--primary);">
                            {{ $priceDisplay }}
                        </span>
                        <span class="text-sm" style="color: var(--text-secondary);">/month</span>
                    @else
                        <span class="text-sm" style="color: var(--text-secondary);">
                            Price on request
                        </span>
                    @endif
                </div>

                {{-- ✅ IMPROVED: only render each spec when it's non-null --}}
                <div class="flex gap-3 text-sm" style="color: var(--text-secondary);">
                    @if($unit->bedrooms)
                        <span title="{{ $unit->bedrooms }} bedroom{{ $unit->bedrooms > 1 ? 's' : '' }}">
                            <i class="fas fa-bed mr-1" aria-hidden="true"></i>{{ $unit->bedrooms }}
                        </span>
                    @endif

                    @if($unit->bathrooms)
                        <span title="{{ $unit->bathrooms }} bathroom{{ $unit->bathrooms > 1 ? 's' : '' }}">
                            <i class="fas fa-bath mr-1" aria-hidden="true"></i>{{ $unit->bathrooms }}
                        </span>
                    @endif
                </div>
            </div>

            {{--
                ✅ IMPROVED: CTA is a visual button only — the stretched
                link at the top of the card handles navigation. Keeping
                it as a <div> with role/aria avoids nested links while
                still looking like a button.
            --}}
            <div class="block w-full text-center px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 pointer-events-none"
                 style="background: var(--primary); color: white;"
                 aria-hidden="true">
                View Details
            </div>
        </div>
    </article>
@endif

{{-- ================================================================
     ✅ IMPROVED: Scoped styles for the image-fallback overlay.
     Only applied when the <img> fires onerror.
     ================================================================ --}}
@push('styles')
<style>
    .img-fallback {
        display: none;
    }
    .img-fallback-active .img-fallback {
        display: flex;
    }
</style>
@endpush