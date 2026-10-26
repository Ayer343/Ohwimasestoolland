@extends('layouts.tenant')

@section('title', 'No Unit Assigned')

@section('content')
<div class="max-w-3xl mx-auto py-10 px-4">
    <div class="card">
        <div class="p-8 text-center">
            <div class="mx-auto w-20 h-20 rounded-full flex items-center justify-center mb-6"
                 style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                <i class="fas fa-home text-3xl"></i>
            </div>

            <h2 class="text-2xl font-semibold mb-2" style="color: var(--text-primary);">
                No unit assigned yet
            </h2>

            <p class="text-sm mb-6" style="color: var(--text-secondary);">
                You don't have an active unit assignment right now.
                Once your landlord or property manager assigns you a unit,
                it will appear here and on your dashboard.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 max-w-lg mx-auto mb-8 text-left">
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                    <i class="fas fa-user-check mb-1" style="color: var(--primary);"></i>
                    <p class="text-xs font-medium" style="color: var(--text-primary);">Awaiting assignment</p>
                </div>
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                    <i class="fas fa-envelope mb-1" style="color: var(--info);"></i>
                    <p class="text-xs font-medium" style="color: var(--text-primary);">Check your inbox</p>
                </div>
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                    <i class="fas fa-phone mb-1" style="color: var(--warning);"></i>
                    <p class="text-xs font-medium" style="color: var(--text-primary);">Contact your landlord</p>
                </div>
            </div>

            <div class="flex flex-wrap justify-center gap-3">
                <a href="{{ route('dashboard') }}"
                   class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>

                <a href="{{ route('profile.edit') ?? '#' }}"
                   class="px-4 py-2 rounded-lg inline-flex items-center text-sm font-medium"
                   style="background-color: var(--primary); color: white; border: 2px solid var(--primary);">
                    <i class="fas fa-user-cog mr-2"></i> Update My Profile
                </a>
            </div>

            <p class="text-xs mt-8" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-1"></i>
                If you believe this is a mistake, please contact support or your property manager.
            </p>
        </div>
    </div>
</div>
@endsection