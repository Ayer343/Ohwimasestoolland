@extends('layouts.guest')

@section('title', 'Invalid Invitation')

@section('content')
@php
    $systemSettings = \App\Models\SystemSetting::getSettings();
    $supportEmail = $systemSettings->system_email ?? 'support@example.com';
    $systemName = $systemSettings->system_name ?? config('app.name', 'Property Registration System');
@endphp

<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-red-50 via-white to-pink-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <!-- Header -->
        <div class="text-center animate-fadeInUp">
            <div class="mx-auto h-20 w-20 bg-gradient-to-r from-red-500 to-pink-500 rounded-2xl flex items-center justify-center mb-6 shadow-xl">
                <i class="fas fa-ban text-white text-2xl"></i>
            </div>
            <h2 class="text-4xl font-bold text-gray-900 mb-3">
                Invalid Invitation
            </h2>
            <p class="text-lg text-gray-600 mb-6">
                This invitation link is not valid
            </p>
        </div>

        <!-- Invalid Message Card -->
        <div class="card border-l-4 border-red-500 animate-fadeInUp" style="animation-delay: 0.2s">
            <div class="p-8">
                <div class="flex items-start space-x-6">
                    <div class="flex-shrink-0 w-16 h-16 bg-red-100 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-times-circle text-red-600 text-2xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">
                            Invalid Link
                        </h3>
                        
                        <div class="space-y-4 text-gray-700">
                            <p class="text-lg">
                                This invitation link is no longer valid or has been revoked.
                            </p>
                            
                            @if(session('error'))
                            <div class="p-4 bg-red-50 rounded-xl border border-red-200">
                                <p class="text-red-700 font-semibold">{{ session('error') }}</p>
                            </div>
                            @endif

                            @if(isset($invitation))
                            <div class="p-4 bg-gray-50 rounded-xl border border-gray-200">
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="font-semibold">Status:</span>
                                        <span class="font-bold text-red-600">{{ ucfirst($invitation->status) }}</span>
                                    </div>
                                    @if($invitation->revoked_at)
                                    <div class="flex justify-between">
                                        <span class="font-semibold">Revoked at:</span>
                                        <span class="font-bold">{{ $invitation->revoked_at->format('M j, Y g:i A') }}</span>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Possible Reasons -->
        <div class="card border-l-4 border-amber-500 animate-fadeInUp" style="animation-delay: 0.3s">
            <div class="p-8">
                <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                    <i class="fas fa-info-circle text-amber-600 mr-4 text-2xl"></i>
                    Possible Reasons
                </h3>
                
                <div class="space-y-3 text-gray-700">
                    <div class="flex items-start">
                        <i class="fas fa-times-circle text-red-500 mr-3 mt-1"></i>
                        <span>The invitation has already been accepted</span>
                    </div>
                    <div class="flex items-start">
                        <i class="fas fa-times-circle text-red-500 mr-3 mt-1"></i>
                        <span>The invitation was revoked by an administrator</span>
                    </div>
                    <div class="flex items-start">
                        <i class="fas fa-times-circle text-red-500 mr-3 mt-1"></i>
                        <span>The invitation link is incorrect or corrupted</span>
                    </div>
                    <div class="flex items-start">
                        <i class="fas fa-times-circle text-red-500 mr-3 mt-1"></i>
                        <span>The associated registration plan was cancelled</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Support Information -->
        <div class="text-center animate-fadeInUp" style="animation-delay: 0.4s">
            <div class="p-6 bg-gray-50 rounded-2xl border border-gray-200">
                <h4 class="text-lg font-bold text-gray-900 mb-4">
                    Need a New Invitation?
                </h4>
                <p class="text-gray-700 mb-4">
                    Contact your administrator or {{ $systemName }} support:
                </p>
                <div class="flex flex-col space-y-3">
                    @if($supportEmail)
                    <div class="flex items-center justify-center text-blue-600">
                        <i class="fas fa-envelope mr-3"></i>
                        <a href="mailto:{{ $supportEmail }}" class="hover:underline font-semibold">
                            {{ $supportEmail }}
                        </a>
                    </div>
                    @endif
                    @if($systemSettings && $systemSettings->system_phone)
                    <div class="flex items-center justify-center text-green-600">
                        <i class="fas fa-phone mr-3"></i>
                        <a href="tel:{{ $systemSettings->system_phone }}" class="hover:underline font-semibold">
                            {{ $systemSettings->system_phone }}
                        </a>
                    </div>
                    @endif
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="mt-6 space-y-4">
                <a href="{{ url('/') }}" class="btn-outline-glow inline-flex items-center px-6 py-3 text-base font-semibold">
                    <i class="fas fa-home mr-3"></i>
                    Return to Homepage
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* Reuse styles from previous blades */
.animate-fadeInUp {
    animation: fadeInUp 0.8s ease-out forwards;
    opacity: 0;
}

.card {
    background: white;
    border-radius: 24px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
    border: 1px solid #f1f5f9;
}

.btn-outline-glow {
    background: white;
    border: 2px solid #e5e7eb;
    color: #374151;
    border-radius: 16px;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-block;
}

.btn-outline-glow:hover {
    background: #f9fafb;
    transform: translateY(-2px);
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
@endsection