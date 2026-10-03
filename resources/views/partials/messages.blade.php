{{-- Success Messages --}}
@if(session('success'))
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert" style="background-color: rgba(var(--success-rgb), 0.1); border-color: rgba(var(--success-rgb), 0.3); color: var(--success);">
    <div class="flex items-center">
        <div class="flex-shrink-0">
            <i class="fas fa-check-circle mr-3"></i>
        </div>
        <div class="flex-1">
            <strong class="font-bold">Success!</strong>
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
        <button type="button" class="flex-shrink-0 ml-4 text-green-700 hover:text-green-900 transition-colors" onclick="this.parentElement.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>
@endif

{{-- Error Messages --}}
@if(session('error'))
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert" style="background-color: rgba(var(--danger-rgb), 0.1); border-color: rgba(var(--danger-rgb), 0.3); color: var(--danger);">
    <div class="flex items-center">
        <div class="flex-shrink-0">
            <i class="fas fa-exclamation-triangle mr-3"></i>
        </div>
        <div class="flex-1">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
        <button type="button" class="flex-shrink-0 ml-4 text-red-700 hover:text-red-900 transition-colors" onclick="this.parentElement.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>
@endif

{{-- Warning Messages --}}
@if(session('warning'))
<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert" style="background-color: rgba(var(--warning-rgb), 0.1); border-color: rgba(var(--warning-rgb), 0.3); color: var(--warning);">
    <div class="flex items-center">
        <div class="flex-shrink-0">
            <i class="fas fa-exclamation-circle mr-3"></i>
        </div>
        <div class="flex-1">
            <strong class="font-bold">Warning!</strong>
            <span class="block sm:inline">{{ session('warning') }}</span>
        </div>
        <button type="button" class="flex-shrink-0 ml-4 text-yellow-700 hover:text-yellow-900 transition-colors" onclick="this.parentElement.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>
@endif

{{-- Info Messages --}}
@if(session('info'))
<div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert" style="background-color: rgba(var(--info-rgb), 0.1); border-color: rgba(var(--info-rgb), 0.3); color: var(--info);">
    <div class="flex items-center">
        <div class="flex-shrink-0">
            <i class="fas fa-info-circle mr-3"></i>
        </div>
        <div class="flex-1">
            <strong class="font-bold">Info!</strong>
            <span class="block sm:inline">{{ session('info') }}</span>
        </div>
        <button type="button" class="flex-shrink-0 ml-4 text-blue-700 hover:text-blue-900 transition-colors" onclick="this.parentElement.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>
@endif

{{-- Validation Errors --}}
@if($errors->any())
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert" style="background-color: rgba(var(--danger-rgb), 0.1); border-color: rgba(var(--danger-rgb), 0.3); color: var(--danger);">
    <div class="flex items-center">
        <div class="flex-shrink-0">
            <i class="fas fa-exclamation-triangle mr-3"></i>
        </div>
        <div class="flex-1">
            <strong class="font-bold">Please fix the following errors:</strong>
            <ul class="mt-1 list-disc list-inside text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        <button type="button" class="flex-shrink-0 ml-4 text-red-700 hover:text-red-900 transition-colors" onclick="this.parentElement.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>
@endif

{{-- Auto-dismiss Script --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide messages after 5 seconds
    const messages = document.querySelectorAll('.bg-green-100, .bg-red-100, .bg-yellow-100, .bg-blue-100');
    
    messages.forEach(function(message) {
        setTimeout(() => {
            if (message.parentElement) {
                message.style.opacity = '0';
                message.style.transition = 'opacity 0.5s ease';
                setTimeout(() => {
                    if (message.parentElement) {
                        message.style.display = 'none';
                    }
                }, 500);
            }
        }, 5000);
    });

    // Add click handler for all close buttons
    document.querySelectorAll('[onclick*="parentElement.parentElement.style.display=\'none\'"]').forEach(function(button) {
        button.addEventListener('click', function() {
            this.parentElement.parentElement.style.opacity = '0';
            this.parentElement.parentElement.style.transition = 'opacity 0.3s ease';
            setTimeout(() => {
                this.parentElement.parentElement.style.display = 'none';
            }, 300);
        });
    });
});
</script>

<style>
/* Message animations */
.bg-green-100, .bg-red-100, .bg-yellow-100, .bg-blue-100 {
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        transform: translateY(-10px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Responsive adjustments */
@media (max-width: 640px) {
    .bg-green-100, .bg-red-100, .bg-yellow-100, .bg-blue-100 {
        margin-left: 1rem;
        margin-right: 1rem;
    }
    
    .flex.items-center {
        align-items: flex-start;
    }
    
    .flex-shrink-0 {
        margin-top: 0.125rem;
    }
}

/* Print styles */
@media print {
    .bg-green-100, .bg-red-100, .bg-yellow-100, .bg-blue-100 {
        border: 1px solid #ccc !important;
        background-color: #f9f9f9 !important;
        color: #333 !important;
    }
    
    button {
        display: none !important;
    }
}
</style>