<!-- resources/views/security/personnel/partials/show-modal.blade.php -->
<!-- This is loaded via AJAX when viewing personnel details -->

<div class="p-6">
    <div class="flex items-center space-x-4 mb-6">
        <div class="w-20 h-20 rounded-full flex items-center justify-center text-3xl font-bold"
             style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
            {{ substr($user->name, 0, 2) }}
        </div>
        <div>
            <h3 class="text-xl font-semibold" style="color: var(--text-primary);">{{ $user->name }}</h3>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-envelope mr-1"></i> {{ $user->email }}
                <span class="mx-2">•</span>
                <i class="fas fa-phone mr-1"></i> {{ $user->phone ?? 'No phone' }}
            </div>
            <div class="mt-1">
                <span class="px-2 py-0.5 text-xs rounded-full badge-{{ $user->status === 'active' ? 'success' : ($user->status === 'pending' ? 'warning' : 'secondary') }}">
                    {{ ucfirst($user->status) }}
                </span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="p-4 rounded border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: var(--border-color);">
            <div class="text-sm" style="color: var(--text-secondary);">Supervisor Status</div>
            <div class="font-semibold mt-1" style="color: var(--text-primary);">
                {{ $user->can_be_supervisor && $user->supervisor_level > 0 ? 'Supervisor' : 'Not a Supervisor' }}
            </div>
        </div>
        <div class="p-4 rounded border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: var(--border-color);">
            <div class="text-sm" style="color: var(--text-secondary);">Supervisor Level</div>
            <div class="font-semibold mt-1" style="color: var(--text-primary);">
                @php
                    $levelNames = [0 => 'N/A', 1 => 'Team Lead', 2 => 'Section Lead', 3 => 'Post Commander'];
                @endphp
                {{ $levelNames[$user->supervisor_level] ?? 'N/A' }}
            </div>
        </div>
        <div class="p-4 rounded border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: var(--border-color);">
            <div class="text-sm" style="color: var(--text-secondary);">Supervisor Score</div>
            <div class="font-semibold mt-1" style="color: var(--text-primary);">
                {{ $user->supervisor_score ?? 'N/A' }}
            </div>
        </div>
    </div>

    @if($user->can_be_supervisor && $user->supervisor_level > 0)
    <div class="p-4 rounded border" style="background-color: rgba(var(--success-rgb), 0.05); border-color: rgba(var(--success-rgb), 0.2);">
        <h4 class="font-semibold mb-2" style="color: var(--text-primary);">
            <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
            Supervisor Assignment Details
        </h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <span style="color: var(--text-secondary);">Assigned By:</span>
                <span style="color: var(--text-primary);">{{ $user->supervisor_assigned_by_name ?? 'System' }}</span>
            </div>
            <div>
                <span style="color: var(--text-secondary);">Assigned At:</span>
                <span style="color: var(--text-primary);">{{ $user->supervisor_assigned_at?->format('Y-m-d H:i') ?? 'N/A' }}</span>
            </div>
            <div class="md:col-span-2">
                <span style="color: var(--text-secondary);">Certifications:</span>
                <span style="color: var(--text-primary);">
                    @php
                        $certs = is_string($user->supervisor_certifications) 
                            ? json_decode($user->supervisor_certifications, true) 
                            : $user->supervisor_certifications;
                    @endphp
                    @if(!empty($certs) && is_array($certs))
                        {{ implode(', ', $certs) }}
                    @else
                        No certifications recorded
                    @endif
                </span>
            </div>
        </div>
    </div>
    @endif
</div>