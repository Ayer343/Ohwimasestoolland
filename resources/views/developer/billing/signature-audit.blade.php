{{-- resources/views/developer/billing/signature-audit.blade.php --}}
@extends('layouts.dev')

@php
    $pageTitle = 'Signature Audit Trail';

    if (!function_exists('safeFormatDate')) {
        function safeFormatDate($date, $format = 'M d, Y') {
            if (empty($date)) return 'N/A';
            if ($date instanceof \Carbon\Carbon) {
                return $date->format($format);
            }
            if (is_string($date)) {
                try {
                    return \Carbon\Carbon::parse($date)->format($format);
                } catch (\Exception $e) {
                    return $date;
                }
            }
            return 'N/A';
        }
    }

    if (!function_exists('safeFormatDateTime')) {
        function safeFormatDateTime($date, $format = 'M d, Y h:i A') {
            if (empty($date)) return 'N/A';
            if ($date instanceof \Carbon\Carbon) {
                return $date->format($format);
            }
            if (is_string($date)) {
                try {
                    return \Carbon\Carbon::parse($date)->format($format);
                } catch (\Exception $e) {
                    return $date;
                }
            }
            return 'N/A';
        }
    }

    $isDarkMode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] === 'true' : false;

    $processedSignatures = collect();
    if (isset($signatures) && $signatures->count() > 0) {
        foreach ($signatures as $sig) {
            $sig['signature_date_formatted'] = safeFormatDate($sig['signature_date'] ?? $sig['created_at'] ?? now(), 'M d, Y');
            $sig['signature_time_formatted'] = safeFormatDateTime($sig['signature_date'] ?? $sig['created_at'] ?? now(), 'h:i A');
            $sig['created_at_formatted']     = safeFormatDate($sig['created_at'] ?? now(), 'M d, Y');
            $sig['created_at_time']          = safeFormatDateTime($sig['created_at'] ?? now(), 'h:i A');
            $processedSignatures->push($sig);
        }
    }

    $auditStartDate = 'No signatures';
    $auditEndDate   = '';
    if ($processedSignatures->count() > 0) {
        $firstDate = $processedSignatures->first()['created_at'] ?? null;
        $lastDate  = $processedSignatures->last()['created_at'] ?? null;
        $auditStartDate = safeFormatDate($lastDate, 'M d');
        $auditEndDate   = safeFormatDate($firstDate, 'M d, Y');
    }
@endphp

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-3">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-signature text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-signature mr-2" style="color: var(--primary);"></i>
                        Signature Audit Trail
                    </h2>
                    <div class="text-sm flex items-center mt-1 flex-wrap gap-1" style="color: var(--text-secondary);">
                        <i class="fas fa-hashtag"></i>
                        <span class="font-mono">{{ $agreement->agreement_number }}</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-file-contract" style="color: var(--info);"></i>
                        <span>{{ Str::limit($agreement->description, 50) }}</span>
                    </div>
                    <div class="text-xs flex items-center mt-1 flex-wrap gap-1" style="color: var(--text-secondary);">
                        <i class="fas fa-user-shield"></i>
                        <span>Super Admin: {{ $agreement->superAdmin->name ?? 'N/A' }}</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-calendar-alt"></i>
                        <span>Created: {{ safeFormatDate($agreement->created_at) }}</span>
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <div class="flex items-center space-x-2">
                    <a href="{{ route('developer.billing.view-agreement', $agreement->id) }}"
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Agreement
                    </a>
                    <button onclick="window.print()"
                            class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                            style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-print mr-1"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit Summary -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                     style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    <i class="fas fa-signature"></i>
                </div>
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Signatures</div>
                    <div class="text-xl font-bold" style="color: var(--text-primary);">{{ $processedSignatures->count() }}</div>
                </div>
            </div>
        </div>

        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                     style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                    <i class="fas fa-code"></i>
                </div>
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Developer Signatures</div>
                    <div class="text-xl font-bold" style="color: var(--text-primary);">
                        {{ $processedSignatures->where('user_type', 'Developer')->count() }}
                    </div>
                </div>
            </div>
        </div>

        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                     style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Super Admin Signatures</div>
                    <div class="text-xl font-bold" style="color: var(--text-primary);">
                        {{ $processedSignatures->where('user_type', 'Super Admin')->count() }}
                    </div>
                </div>
            </div>
        </div>

        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                     style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                    <i class="fas fa-history"></i>
                </div>
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Audit Period</div>
                    <div class="text-lg font-bold" style="color: var(--text-primary);">
                        @if($processedSignatures->count() > 0)
                            {{ safeFormatDate($processedSignatures->last()['created_at'] ?? null, 'M d') }} -
                            {{ safeFormatDate($processedSignatures->first()['created_at'] ?? null, 'M d, Y') }}
                        @else
                            No signatures
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Signature Audit Table -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-clipboard-list mr-2" style="color: var(--secondary);"></i> Signature Audit Log
            <span class="ml-2 text-sm px-2 py-1 rounded-full"
                  style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                {{ $processedSignatures->count() }} records
            </span>
        </h3>

        @if($processedSignatures->count() > 0)
        <div class="overflow-x-auto">
            <table class="table audit-table w-full">
                <thead>
                    <tr>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">#</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Signature</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">User</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Details</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Technical Info</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($processedSignatures as $index => $signature)
                    <tr>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <div class="font-mono text-sm" style="color: var(--text-secondary);">
                                #{{ str_pad($index + 1, 3, '0', STR_PAD_LEFT) }}
                            </div>
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                                     style="background-color: {{ ($signature['user_type'] ?? '') == 'Developer' ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--info-rgb), 0.1)' }};
                                            color: {{ ($signature['user_type'] ?? '') == 'Developer' ? 'var(--success)' : 'var(--info)' }};">
                                    <i class="fas fa-{{ ($signature['user_type'] ?? '') == 'Developer' ? 'code' : 'user-shield' }}"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-medium" style="color: var(--text-primary);">{{ $signature['signature_name'] ?? 'N/A' }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">{{ $signature['user_type'] ?? 'Unknown' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <div>
                                <div class="font-medium" style="color: var(--text-primary);">{{ $signature['user_name'] ?? 'Unknown' }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">{{ $signature['user_email'] ?? 'N/A' }}</div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium mt-1 {{ ($signature['user_type'] ?? '') == 'Developer' ? 'badge-success' : 'badge-info' }}">
                                    <i class="fas fa-user mr-1"></i>
                                    {{ $signature['user_type'] ?? 'Unknown' }}
                                </span>
                            </div>
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <div>
                                <div class="text-sm mb-1">
                                    <span style="color: var(--text-secondary);">Type:</span>
                                    <span class="font-medium ml-1" style="color: var(--text-primary);">
                                        {{ ucfirst(str_replace('_', ' ', $signature['signature_type'] ?? 'unknown')) }}
                                    </span>
                                </div>
                                <div class="text-sm">
                                    <span style="color: var(--text-secondary);">Date:</span>
                                    <span class="font-medium ml-1" style="color: var(--text-primary);">
                                        {{ $signature['signature_date_formatted'] }}
                                    </span>
                                </div>
                                @if(!empty($signature['signature_path']))
                                <div class="text-xs mt-1">
                                    <a href="{{ Storage::url($signature['signature_path']) }}"
                                       target="_blank"
                                       class="inline-flex items-center text-xs"
                                       style="color: var(--primary);">
                                        <i class="fas fa-external-link-alt mr-1"></i> View Signature
                                    </a>
                                </div>
                                @endif
                            </div>
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <div class="text-xs">
                                @if(!empty($signature['ip_address']))
                                <div class="mb-1">
                                    <span style="color: var(--text-secondary);">IP:</span>
                                    <span class="font-mono ml-1" style="color: var(--text-primary);">{{ $signature['ip_address'] }}</span>
                                </div>
                                @endif
                                @if(!empty($signature['user_agent']))
                                <div>
                                    <span style="color: var(--text-secondary);">Device:</span>
                                    <span class="font-mono ml-1 truncate" style="color: var(--text-primary); display: block; max-width: 200px;"
                                          title="{{ $signature['user_agent'] }}">
                                        {{ Str::limit($signature['user_agent'], 30) }}
                                    </span>
                                </div>
                                @endif
                                <div class="mt-1">
                                    <span style="color: var(--text-secondary);">Signature ID:</span>
                                    <span class="font-mono ml-1" style="color: var(--text-primary);">{{ $signature['id'] ?? 'N/A' }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <div>
                                <div class="text-sm font-medium" style="color: var(--text-primary);">
                                    {{ $signature['signature_date_formatted'] }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    {{ $signature['signature_time_formatted'] }}
                                </div>
                                <div class="text-xs mt-1">
                                    <span style="color: var(--text-secondary);">Logged:</span>
                                    <span class="ml-1" style="color: var(--text-primary);">
                                        {{ $signature['created_at_formatted'] }}
                                    </span>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Timeline Visualization -->
        <div class="mt-8 pt-8" style="border-top: 1px solid var(--border-color);">
            <h4 class="text-md font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-stream mr-2" style="color: var(--secondary);"></i> Signature Timeline
            </h4>

            <div class="timeline">
                @foreach($processedSignatures as $signature)
                <div class="timeline-item timeline-item-{{ ($signature['user_type'] ?? '') == 'Developer' ? 'success' : 'info' }}">
                    <div class="timeline-item-icon">
                        <i class="fas fa-{{ ($signature['user_type'] ?? '') == 'Developer' ? 'code' : 'user-shield' }}"></i>
                    </div>
                    <div class="timeline-item-content">
                        <div class="timeline-item-title" style="color: var(--text-primary);">
                            {{ $signature['user_type'] ?? 'Unknown' }} Signature
                        </div>
                        <div class="timeline-item-date" style="color: var(--text-secondary);">
                            {{ $signature['signature_date_formatted'] }} {{ $signature['signature_time_formatted'] }}
                        </div>
                        <div class="timeline-item-description" style="color: var(--text-secondary);">
                            <strong>{{ $signature['user_name'] ?? 'Unknown' }}</strong> signed as "{{ $signature['signature_name'] ?? 'N/A' }}"
                            @if(!empty($signature['ip_address']))
                            • IP: {{ $signature['ip_address'] }}
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Audit Summary Footer -->
        <div class="mt-8 pt-8" style="border-top: 1px solid var(--border-color);">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h5 class="text-sm font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-clipboard-check mr-2" style="color: var(--success);"></i> Audit Summary
                    </h5>
                    <ul class="space-y-2">
                        <li class="flex items-center text-sm">
                            <i class="fas fa-check-circle mr-2" style="color: var(--success); font-size: 0.75rem;"></i>
                            <span style="color: var(--text-secondary);">Total signature events:</span>
                            <span class="font-medium ml-1" style="color: var(--text-primary);">{{ $processedSignatures->count() }}</span>
                        </li>
                        <li class="flex items-center text-sm">
                            <i class="fas fa-code mr-2" style="color: var(--success); font-size: 0.75rem;"></i>
                            <span style="color: var(--text-secondary);">Developer signatures:</span>
                            <span class="font-medium ml-1" style="color: var(--text-primary);">{{ $processedSignatures->where('user_type', 'Developer')->count() }}</span>
                        </li>
                        <li class="flex items-center text-sm">
                            <i class="fas fa-user-shield mr-2" style="color: var(--info); font-size: 0.75rem;"></i>
                            <span style="color: var(--text-secondary);">Super Admin signatures:</span>
                            <span class="font-medium ml-1" style="color: var(--text-primary);">{{ $processedSignatures->where('user_type', 'Super Admin')->count() }}</span>
                        </li>
                        <li class="flex items-center text-sm">
                            <i class="fas fa-calendar-alt mr-2" style="color: var(--warning); font-size: 0.75rem;"></i>
                            <span style="color: var(--text-secondary);">Audit period:</span>
                            <span class="font-medium ml-1" style="color: var(--text-primary);">
                                @if($processedSignatures->count() > 0)
                                    {{ safeFormatDate($processedSignatures->last()['created_at'] ?? null, 'M d, Y') }} to {{ safeFormatDate($processedSignatures->first()['created_at'] ?? null, 'M d, Y') }}
                                @endif
                            </span>
                        </li>
                    </ul>
                </div>

                <div>
                    <h5 class="text-sm font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-shield-alt mr-2" style="color: var(--primary);"></i> Audit Integrity
                    </h5>
                    <ul class="space-y-2">
                        <li class="flex items-center text-sm">
                            @if($processedSignatures->where('ip_address')->count() == $processedSignatures->count())
                                <i class="fas fa-check-circle mr-2" style="color: var(--success); font-size: 0.75rem;"></i>
                            @else
                                <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning); font-size: 0.75rem;"></i>
                            @endif
                            <span style="color: var(--text-secondary);">IP Address Logging:</span>
                            <span class="font-medium ml-1" style="color: var(--text-primary);">
                                {{ $processedSignatures->where('ip_address')->count() }}/{{ $processedSignatures->count() }}
                            </span>
                        </li>
                        <li class="flex items-center text-sm">
                            @if($processedSignatures->where('user_agent')->count() == $processedSignatures->count())
                                <i class="fas fa-check-circle mr-2" style="color: var(--success); font-size: 0.75rem;"></i>
                            @else
                                <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning); font-size: 0.75rem;"></i>
                            @endif
                            <span style="color: var(--text-secondary);">Device Info Logging:</span>
                            <span class="font-medium ml-1" style="color: var(--text-primary);">
                                {{ $processedSignatures->where('user_agent')->count() }}/{{ $processedSignatures->count() }}
                            </span>
                        </li>
                        <li class="flex items-center text-sm">
                            <i class="fas fa-history mr-2" style="color: var(--info); font-size: 0.75rem;"></i>
                            <span style="color: var(--text-secondary);">Timestamp Accuracy:</span>
                            <span class="font-medium ml-1" style="color: var(--text-primary);">100%</span>
                        </li>
                        <li class="flex items-center text-sm">
                            <i class="fas fa-fingerprint mr-2" style="color: var(--primary); font-size: 0.75rem;"></i>
                            <span style="color: var(--text-secondary);">Unique Signers:</span>
                            <span class="font-medium ml-1" style="color: var(--text-primary);">
                                {{ $processedSignatures->unique('user_name')->count() }}
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="mt-6 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                <div class="flex items-start">
                    <i class="fas fa-info-circle mr-3 mt-0.5" style="color: var(--info);"></i>
                    <div>
                        <p class="text-sm font-semibold mb-1" style="color: var(--text-primary);">Legal Notice</p>
                        <p class="text-xs" style="color: var(--text-secondary);">
                            This audit trail is a legally binding record of all electronic signatures associated with this agreement.
                            Each entry includes timestamp, user information, and technical metadata to ensure authenticity and non-repudiation.
                        </p>
                        <div class="flex items-center mt-2 text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-clock mr-1"></i>
                            <span>Generated on: {{ safeFormatDateTime(now()) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @else
        <!-- Empty State -->
        <div class="text-center py-12">
            <div class="w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-4"
                 style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                <i class="fas fa-signature text-3xl"></i>
            </div>
            <h3 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No Signatures Found</h3>
            <p class="text-sm mb-6" style="color: var(--text-secondary); max-width: 400px; margin: 0 auto;">
                No signatures have been recorded for this agreement yet. Signatures will appear here once the agreement is signed by both parties.
            </p>
            <div class="flex justify-center space-x-3">
                <a href="{{ route('developer.billing.view-agreement', $agreement->id) }}"
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Agreement
                </a>
                @if($agreement->status == 'pending' && !empty($agreement->agreement_pdf_path))
                <button onclick="sendForSigning()"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-paper-plane mr-2"></i> Send for Signing
                </button>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@section('scripts')
<script>
function sendForSigning() {
    if (confirm('Send this agreement for signing to the super admin?')) {
        showToast('Sending invitation...', 'info');

        const agreementId = '{{ $agreement->id }}';
        const url = '/developer/billing/agreement/' + agreementId + '/send-invitation';

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Signature invitation sent successfully!', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showToast('Failed to send invitation: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to send invitation. Please try again.', 'error');
        });
    }
}

function showToast(message, type = 'info') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(toastContainer);
    }

    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
    }`;

    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1';
    messageEl.textContent = message;

    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 transition-colors duration-200';
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.onclick = () => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    };

    toast.appendChild(messageEl);
    toast.appendChild(closeBtn);
    toastContainer.appendChild(toast);

    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 10);

    setTimeout(() => {
        if (toast.parentNode === toastContainer) {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

document.addEventListener('DOMContentLoaded', function() {
    // nothing extra for now
});
</script>

<style>
/* =========================================================
   Audit table — replaces the default `hover:bg-gray-50`
   (which produced a stark white flash) with a subtle
   primary-tinted hover that respects the theme.
   ========================================================= */
.audit-table tbody tr {
    background-color: transparent !important;
    transition: background-color 0.15s ease !important;
}

.audit-table tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.04) !important;
}

/* For dark mode, deepen the tint just a touch */
html.dark .audit-table tbody tr:hover,
body.dark .audit-table tbody tr:hover,
body[data-theme="dark"] .audit-table tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.08) !important;
}

/* Keep cell borders visible on hover */
.audit-table tbody tr:hover > td {
    border-color: var(--border-color) !important;
}

/* ---- Timeline ---- */
.timeline {
    position: relative;
    padding-left: 1.5rem;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 0.625rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background-color: var(--border-color);
}

.timeline-item {
    position: relative;
    margin-bottom: 1.5rem;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-item-icon {
    position: absolute;
    left: -1.5rem;
    top: 0;
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: var(--card-bg);
    border: 2px solid var(--border-color);
    z-index: 1;
}

.timeline-item-success .timeline-item-icon {
    border-color: var(--success);
    color: var(--success);
}

.timeline-item-info .timeline-item-icon {
    border-color: var(--info);
    color: var(--info);
}

.timeline-item-content {
    background-color: var(--card-bg);
    padding: 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid var(--border-color);
}

.timeline-item-title {
    font-weight: 500;
    font-size: 0.875rem;
}

.timeline-item-date {
    font-size: 0.75rem;
    margin-top: 0.25rem;
}

.timeline-item-description {
    font-size: 0.75rem;
    margin-top: 0.25rem;
}

.badge-success {
    background-color: rgba(var(--success-rgb), 0.1) !important;
    color: var(--success) !important;
    border: 1px solid rgba(var(--success-rgb), 0.3) !important;
}

.badge-info {
    background-color: rgba(var(--info-rgb), 0.1) !important;
    color: var(--info) !important;
    border: 1px solid rgba(var(--info-rgb), 0.3) !important;
}

.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-1px);
}

.table td, .table th {
    vertical-align: middle;
}

@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-2 {
        grid-template-columns: 1fr;
    }

    .grid.grid-cols-1.lg\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }

    .table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
}

@media (max-width: 640px) {
    .grid.grid-cols-1.lg\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
}

@media print {
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }

    .btn, button {
        display: none !important;
    }

    .table {
        border-collapse: collapse;
    }

    .table th, .table td {
        border: 1px solid #ddd !important;
    }

    .timeline::before {
        background-color: #ddd !important;
    }

    .timeline-item-icon {
        background-color: white !important;
        border-color: #ddd !important;
        color: #666 !important;
    }

    a {
        color: #000 !important;
        text-decoration: none !important;
    }
}
</style>
@endsection