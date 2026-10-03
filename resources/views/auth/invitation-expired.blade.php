@extends('layouts.auth')

@section('title', 'Invitation Expired - ' . config('app.name'))

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <!-- Header -->
        <div class="text-center">
            <div class="mx-auto h-20 w-20 flex items-center justify-center rounded-full bg-red-100">
                <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
            </div>
            <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                Invitation Expired
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                {{ $message ?? 'This invitation link is no longer valid.' }}
            </p>
        </div>

        <!-- Main Content -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-6">
            <!-- Expired Details -->
            <div class="text-center">
                <i class="fas fa-clock text-yellow-500 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">What Happened?</h3>
                <p class="text-sm text-gray-600">
                    Invitation links are valid for a limited time for security reasons. 
                    This link has expired and can no longer be used to activate your account.
                </p>
            </div>

            <!-- Next Steps -->
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                <h4 class="text-sm font-medium text-blue-800 mb-2">What to Do Next</h4>
                <ul class="text-sm text-blue-700 space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-envelope text-blue-500 mt-0.5 mr-2"></i>
                        <span>Contact the person who sent you this invitation</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-sync-alt text-blue-500 mt-0.5 mr-2"></i>
                        <span>Ask them to send you a new invitation link</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-user-plus text-blue-500 mt-0.5 mr-2"></i>
                        <span>Use the new link to complete your registration</span>
                    </li>
                </ul>
            </div>

            <!-- User Information (if available) -->
            @if(isset($user) && $user)
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <h4 class="text-sm font-medium text-gray-800 mb-2">Your Account Information</h4>
                <div class="space-y-1 text-sm text-gray-600">
                    <div class="flex justify-between">
                        <span>Name:</span>
                        <span class="font-medium">{{ $user->name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Email:</span>
                        <span class="font-medium">{{ $user->email }}</span>
                    </div>
                    @if($user->phone)
                    <div class="flex justify-between">
                        <span>Phone:</span>
                        <span class="font-medium">{{ $user->phone }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between">
                        <span>Role:</span>
                        <span class="font-medium">{{ $user->type_name }}</span>
                    </div>
                </div>
            </div>
            @endif

            <!-- Contact Information -->
            @if($showContact ?? false)
            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <h4 class="text-sm font-medium text-green-800 mb-2">Need Immediate Help?</h4>
                <div class="text-sm text-green-700 space-y-2">
                    <p>If you're having trouble receiving a new invitation, contact our support team:</p>
                    <div class="flex flex-col space-y-1">
                        <a href="mailto:support@example.com" class="flex items-center text-green-600 hover:text-green-500">
                            <i class="fas fa-envelope mr-2"></i>
                            support@example.com
                        </a>
                        <a href="tel:+233123456789" class="flex items-center text-green-600 hover:text-green-500">
                            <i class="fas fa-phone mr-2"></i>
                            +233 123 456 789
                        </a>
                    </div>
                </div>
            </div>
            @endif

            <!-- Action Buttons -->
            <div class="flex flex-col space-y-3">
                <a href="{{ route('login') }}" 
                   class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Go to Login
                </a>
                
                @if(isset($user) && $user)
                <button onclick="requestNewInvitation()" 
                        class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                    <i class="fas fa-paper-plane mr-2"></i>
                    Request New Invitation
                </button>
                @endif
            </div>
        </div>

        <!-- Additional Information -->
        <div class="text-center text-sm text-gray-500">
            <p>Having persistent issues? Our support team is here to help you get started.</p>
        </div>
    </div>
</div>

<script>
function requestNewInvitation() {
    // In a real implementation, this would make an API call to request a new invitation
    alert('A request for a new invitation has been sent to the administrator. You will receive a new invitation link shortly.');
    
    // Example of what the actual implementation might look like:
    /*
    fetch('/api/invitations/request-new', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            user_id: {{ $user->id ?? 'null' }},
            email: '{{ $user->email ?? '' }}'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('New invitation sent successfully! Check your email.');
        } else {
            alert('Failed to send new invitation: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error requesting new invitation. Please contact support.');
    });
    */
}
</script>

<style>
/* Custom styles for expired page */
.bg-red-100 {
    background-color: rgba(254, 226, 226, 0.5);
}

.bg-blue-50 {
    background-color: rgba(239, 246, 255, 0.7);
}

.bg-green-50 {
    background-color: rgba(240, 253, 244, 0.7);
}

/* Smooth transitions */
* {
    transition: all 0.3s ease-in-out;
}

/* Focus styles */
button:focus, a:focus {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

/* Responsive adjustments */
@media (max-width: 640px) {
    .max-w-md {
        margin: 1rem;
    }
}
</style>
@endsection