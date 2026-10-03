@props(['notification' => null])

@php
    $notification = $notification ?? session('notification');
    $type = $notification['type'] ?? 'info';
    $title = $notification['title'] ?? '';
    $message = $notification['message'] ?? '';
    $icon = [
        'success' => 'fa-check-circle',
        'error' => 'fa-exclamation-circle',
        'warning' => 'fa-exclamation-triangle',
        'info' => 'fa-info-circle'
    ][$type] ?? 'fa-bell';
@endphp

@if($notification)
    <div id="notification-toast" 
         class="notification-toast notification-{{ $type }}" 
         role="alert"
         aria-live="assertive"
         aria-atomic="true"
         data-auto-hide="5000"
         x-data="{ show: true }"
         x-show="show"
         x-init="setTimeout(() => show = false, 5000)"
         x-cloak>
        <div class="toast-content">
            <i class="toast-icon fas {{ $icon }}"></i>
            <div class="toast-message">
                @if($title)
                    <strong class="toast-title">{{ $title }}</strong>
                @endif
                <p class="toast-body">{{ $message }}</p>
            </div>
            <button class="toast-close" @click="show = false" aria-label="Close notification">&times;</button>
        </div>
    </div>
@endif