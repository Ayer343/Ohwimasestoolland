{{-- resources/views/sanitation/approvals/landlord.blade.php --}}

@extends('layouts.landlord')

@section('title', 'Waste Collection Approval')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-3xl">
    <div class="card p-6">
        <!-- Header -->
        <div class="text-center mb-6">
            <div class="w-16 h-16 mx-auto bg-yellow-100 rounded-full flex items-center justify-center mb-3">
                <i class="fas fa-trash-alt text-yellow-600 text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                Waste Collection Approval Request
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Please review and respond to the waste collection request for your property
            </p>
        </div>

        <!-- Property Details -->
        <div class="bg-gray-50 rounded-lg p-4 mb-6" style="background-color: var(--bg-secondary);">
            <h3 class="font-semibold mb-3" style="color: var(--text-primary);">Property Details</h3>
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <span class="text-xs" style="color: var(--text-secondary);">Property Name</span>
                    <div class="font-medium" style="color: var(--text-primary);">{{ $property->property_name }}</div>
                </div>
                <div>
                    <span class="text-xs" style="color: var(--text-secondary);">Digital Address</span>
                    <div class="font-medium" style="color: var(--text-primary);">{{ $property->digital_address ?? 'Not set' }}</div>
                </div>
                <div>
                    <span class="text-xs" style="color: var(--text-secondary);">Street</span>
                    <div class="font-medium" style="color: var(--text-primary);">{{ $property->street_name ?? 'N/A' }}</div>
                </div>
                <div>
                    <span class="text-xs" style="color: var(--text-secondary);">Zone</span>
                    <div class="font-medium" style="color: var(--text-primary);">{{ $property->zone ?? 'Unassigned' }}</div>
                </div>
            </div>
        </div>

        <!-- Collection Details -->
        <div class="bg-blue-50 rounded-lg p-4 mb-6" style="background-color: #eff6ff;">
            <h3 class="font-semibold mb-3" style="color: var(--text-primary);">Collection Details</h3>
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <span class="text-xs" style="color: var(--text-secondary);">Frequency</span>
                    <div class="font-medium" style="color: var(--text-primary);">
                        {{ ucfirst($collectionRequest->metadata['collection_frequency'] ?? 'Weekly') }}
                    </div>
                </div>
                <div>
                    <span class="text-xs" style="color: var(--text-secondary);">Collection Days</span>
                    <div class="font-medium" style="color: var(--text-primary);">
                        {{ implode(', ', $collectionRequest->metadata['collection_days'] ?? ['Monday']) }}
                    </div>
                </div>
                <div>
                    <span class="text-xs" style="color: var(--text-secondary);">Preferred Time</span>
                    <div class="font-medium" style="color: var(--text-primary);">
                        {{ $collectionRequest->metadata['preferred_time'] ?? 'Morning' }}
                    </div>
                </div>
                <div>
                    <span class="text-xs" style="color: var(--text-secondary);">Waste Types</span>
                    <div class="font-medium" style="color: var(--text-primary);">
                        {{ implode(', ', array_map('ucfirst', $collectionRequest->metadata['waste_types'] ?? ['General'])) }}
                    </div>
                </div>
            </div>
            
            @if(!empty($collectionRequest->metadata['special_instructions']))
                <div class="mt-3 pt-3 border-t" style="border-color: #bfdbfe;">
                    <span class="text-xs" style="color: var(--text-secondary);">Special Instructions</span>
                    <div class="text-sm" style="color: var(--text-primary);">
                        {{ $collectionRequest->metadata['special_instructions'] }}
                    </div>
                </div>
            @endif
        </div>

        <!-- Important Notice -->
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-yellow-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm" style="color: var(--text-primary);">
                        <span class="font-medium">Important:</span>
                        If you do not respond within <strong>{{ $collectionRequest->approval_expires_at->diffInDays(now()) }} days</strong>, 
                        the request will expire and you will need to contact the sanitation department.
                    </p>
                </div>
            </div>
        </div>

        <!-- Approval Form -->
        <form method="POST" action="{{ route('sanitation.approvals.handle', $collectionRequest) }}" class="space-y-4">
            @csrf
            
            <!-- Action Buttons -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <button type="submit" name="action" value="approve" 
                        class="btn-success text-lg py-3">
                    <i class="fas fa-check mr-2"></i> Approve
                </button>
                <button type="submit" name="action" value="reject" 
                        class="btn-danger text-lg py-3">
                    <i class="fas fa-times mr-2"></i> Reject
                </button>
            </div>

            <!-- Notes/Reason -->
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">
                    Notes / Reason <span class="text-xs" style="color: var(--text-secondary);">(optional for approve, required for reject)</span>
                </label>
                <textarea name="rejection_reason" id="rejection_reason" rows="3"
                          class="w-full p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                          style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                          placeholder="Please provide a reason if rejecting..."></textarea>
            </div>

            <!-- Declaration -->
            <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg" style="background-color: var(--bg-secondary);">
                <input type="checkbox" name="declaration" id="declaration" value="1" required
                       class="mt-1 w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <label for="declaration" class="text-sm" style="color: var(--text-primary);">
                    I confirm that I am the authorized landlord/owner of this property and I understand the waste collection terms and conditions.
                </label>
            </div>

            <!-- Expiry Warning -->
            <div class="text-center text-xs" style="color: var(--text-secondary);">
                <i class="fas fa-clock mr-1"></i>
                This request will expire on {{ $collectionRequest->approval_expires_at->format('M d, Y g:i A') }}
                ({{ $collectionRequest->approval_expires_at->diffForHumans() }})
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Toggle rejection reason visibility
    document.querySelectorAll('button[name="action"]').forEach(button => {
        button.addEventListener('click', function(e) {
            const action = this.value;
            const reasonField = document.getElementById('rejection_reason');
            const reasonLabel = reasonField.closest('div');
            
            if (action === 'reject') {
                reasonLabel.style.display = 'block';
                reasonField.setAttribute('required', 'required');
            } else {
                reasonLabel.style.display = 'none';
                reasonField.removeAttribute('required');
            }
        });
    });
    
    // Initially hide rejection reason for approve button
    document.querySelector('button[value="approve"]')?.addEventListener('click', function() {
        document.getElementById('rejection_reason').removeAttribute('required');
    });
</script>
@endpush