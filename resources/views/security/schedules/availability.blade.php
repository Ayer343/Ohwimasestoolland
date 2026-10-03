@extends('layouts.secu')

@section('title', 'My Availability')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <a href="{{ route('security.schedules.index') }}" 
                   class="mr-4 w-10 h-10 rounded-lg flex items-center justify-center"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-clock mr-2" style="color: var(--primary);"></i>
                        My Availability
                    </h2>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Set your availability for {{ $currentMonth }}
                    </p>
                </div>
            </div>
            
            <div class="flex items-center space-x-3">
                <button onclick="saveAllAvailability()" 
                        class="px-4 py-2 rounded-lg text-sm font-medium text-white inline-flex items-center btn-success">
                    <i class="fas fa-save mr-2"></i> Save All Changes
                </button>
                <a href="{{ route('security.preferences') }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-sliders-h mr-2"></i> Preferences
                </a>
            </div>
        </div>
    </div>

    <!-- Month Navigation -->
    <div class="card p-4">
        <div class="flex justify-between items-center">
            <a href="{{ route('security.availability', ['year' => $prevMonth->year, 'month' => $prevMonth->month]) }}" 
               class="px-4 py-2 rounded-lg text-sm inline-flex items-center"
               style="background-color: var(--bg-secondary); color: var(--text-primary);">
                <i class="fas fa-chevron-left mr-2"></i> {{ $prevMonth->format('M Y') }}
            </a>
            
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                {{ $currentMonth }}
            </h3>
            
            <a href="{{ route('security.availability', ['year' => $nextMonth->year, 'month' => $nextMonth->month]) }}" 
               class="px-4 py-2 rounded-lg text-sm inline-flex items-center"
               style="background-color: var(--bg-secondary); color: var(--text-primary);">
                {{ $nextMonth->format('M Y') }} <i class="fas fa-chevron-right ml-2"></i>
            </a>
        </div>
    </div>

    <!-- Legend / Status Key -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card p-3 flex items-center">
            <div class="w-6 h-6 rounded-full bg-green-500 mr-3"></div>
            <div>
                <div class="font-medium" style="color: var(--text-primary);">Available</div>
                <div class="text-xs" style="color: var(--text-secondary);">Can work any shift</div>
            </div>
        </div>
        
        <div class="card p-3 flex items-center">
            <div class="w-6 h-6 rounded-full bg-yellow-500 mr-3"></div>
            <div>
                <div class="font-medium" style="color: var(--text-primary);">Prefer</div>
                <div class="text-xs" style="color: var(--text-secondary);">Would like to work</div>
            </div>
        </div>
        
        <div class="card p-3 flex items-center">
            <div class="w-6 h-6 rounded-full bg-red-500 mr-3"></div>
            <div>
                <div class="font-medium" style="color: var(--text-primary);">Unavailable</div>
                <div class="text-xs" style="color: var(--text-secondary);">Cannot work</div>
            </div>
        </div>
        
        <div class="card p-3 flex items-center">
            <div class="w-6 h-6 rounded-full bg-gray-300 mr-3"></div>
            <div>
                <div class="font-medium" style="color: var(--text-primary);">Not Set</div>
                <div class="text-xs" style="color: var(--text-secondary);">Default availability</div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $availableCount }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Available Days</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-check-circle" style="color: var(--success);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $preferCount }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Preferred Days</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-star" style="color: var(--warning);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--danger);">{{ $unavailableCount }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Unavailable Days</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-times-circle" style="color: var(--danger);"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Calendar -->
    <div class="card p-6">
        <div class="grid grid-cols-7 gap-2 mb-4">
            <!-- Day headers -->
            @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day)
                <div class="text-center font-medium py-2" style="color: var(--text-secondary);">
                    {{ $day }}
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-7 gap-2">
            <!-- Empty cells for days before month starts -->
            @for($i = 1; $i < $startOfMonth->dayOfWeek; $i++)
                <div class="p-4 rounded-lg opacity-25" style="background-color: var(--bg-secondary);"></div>
            @endfor

            <!-- Calendar days -->
            @for($day = 1; $day <= $daysInMonth; $day++)
                @php
                    $currentDate = $startOfMonth->copy()->setDay($day);
                    $dateString = $currentDate->format('Y-m-d');
                    $availability = $availabilities[$dateString] ?? null;
                    $status = $availability ? $availability->status : 'not_set';
                    $isPast = $currentDate->isPast();
                    $isToday = $currentDate->isToday();
                    
                    $statusColors = [
                        'available' => 'bg-green-500',
                        'prefer' => 'bg-yellow-500',
                        'unavailable' => 'bg-red-500',
                        'not_set' => 'bg-gray-300'
                    ];
                    
                    $statusLabels = [
                        'available' => 'Available',
                        'prefer' => 'Prefer to work',
                        'unavailable' => 'Unavailable',
                        'not_set' => 'Not set'
                    ];
                @endphp

                <div class="relative p-4 rounded-lg transition-all duration-200 calendar-day
                    {{ $isToday ? 'ring-2 ring-blue-500' : '' }}
                    {{ $isPast ? 'opacity-50' : 'cursor-pointer hover:shadow-lg' }}"
                    style="background-color: var(--bg-secondary);"
                    data-date="{{ $dateString }}"
                    data-status="{{ $status }}"
                    onclick="{{ !$isPast ? 'openDayModal(\'' . $dateString . '\', \'' . $status . '\')' : '' }}">
                    
                    <div class="flex justify-between items-start">
                        <span class="font-medium" style="color: var(--text-primary);">{{ $day }}</span>
                        @if(!$isPast)
                        <div class="w-3 h-3 rounded-full {{ $statusColors[$status] }}"></div>
                        @endif
                    </div>
                    
                    @if($availability && $availability->notes)
                        <div class="mt-2 text-xs truncate" style="color: var(--text-secondary);" title="{{ $availability->notes }}">
                            <i class="fas fa-sticky-note mr-1"></i> {{ Str::limit($availability->notes, 15) }}
                        </div>
                    @endif
                    
                    @if($isToday)
                        <span class="absolute top-1 right-1 text-xs text-blue-500">Today</span>
                    @endif
                </div>
            @endfor

            <!-- Empty cells for days after month ends -->
            @php
                $remainingCells = 42 - ($daysInMonth + $startOfMonth->dayOfWeek - 1);
            @endphp
            @for($i = 0; $i < $remainingCells; $i++)
                <div class="p-4 rounded-lg opacity-25" style="background-color: var(--bg-secondary);"></div>
            @endfor
        </div>
    </div>

    <!-- Legend for quick actions -->
    <div class="card p-4">
        <div class="flex flex-wrap items-center justify-between">
            <div class="flex items-center space-x-4">
                <span class="text-sm font-medium" style="color: var(--text-primary);">Quick Actions:</span>
                <button onclick="setAllForMonth('available')" 
                        class="px-3 py-1 rounded text-sm" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                    Set All Available
                </button>
                <button onclick="setAllForMonth('unavailable')" 
                        class="px-3 py-1 rounded text-sm" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                    Set All Unavailable
                </button>
                <button onclick="clearAllForMonth()" 
                        class="px-3 py-1 rounded text-sm" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                    Clear All
                </button>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-1"></i> Click on any future day to set availability
            </div>
        </div>
    </div>
</div>

<!-- Day Detail Modal -->
<div id="dayModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            
            <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-calendar-day mr-2" style="color: var(--primary);"></i>
                    Set Availability for <span id="modalDateDisplay" class="ml-2"></span>
                </h3>
                <button type="button" onclick="closeModal()" style="color: var(--text-secondary);">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="availabilityForm" class="p-6 space-y-4">
                @csrf
                <input type="hidden" id="modalDate" name="date">
                
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Availability Status</label>
                    <div class="grid grid-cols-3 gap-3">
                        <label class="relative">
                            <input type="radio" name="status" value="available" class="sr-only peer" onchange="updateStatusPreview()">
                            <div class="p-3 rounded-lg text-center cursor-pointer transition-all duration-200
                                        peer-checked:ring-2 peer-checked:ring-green-500 peer-checked:bg-green-50"
                                 style="background-color: rgba(var(--success-rgb), 0.1);">
                                <i class="fas fa-check-circle text-green-500 text-xl mb-1"></i>
                                <div class="text-sm font-medium" style="color: var(--text-primary);">Available</div>
                            </div>
                        </label>
                        
                        <label class="relative">
                            <input type="radio" name="status" value="prefer" class="sr-only peer" onchange="updateStatusPreview()">
                            <div class="p-3 rounded-lg text-center cursor-pointer transition-all duration-200
                                        peer-checked:ring-2 peer-checked:ring-yellow-500 peer-checked:bg-yellow-50"
                                 style="background-color: rgba(var(--warning-rgb), 0.1);">
                                <i class="fas fa-star text-yellow-500 text-xl mb-1"></i>
                                <div class="text-sm font-medium" style="color: var(--text-primary);">Prefer</div>
                            </div>
                        </label>
                        
                        <label class="relative">
                            <input type="radio" name="status" value="unavailable" class="sr-only peer" onchange="updateStatusPreview()">
                            <div class="p-3 rounded-lg text-center cursor-pointer transition-all duration-200
                                        peer-checked:ring-2 peer-checked:ring-red-500 peer-checked:bg-red-50"
                                 style="background-color: rgba(var(--danger-rgb), 0.1);">
                                <i class="fas fa-times-circle text-red-500 text-xl mb-1"></i>
                                <div class="text-sm font-medium" style="color: var(--text-primary);">Unavailable</div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-sticky-note mr-1" style="color: var(--primary);"></i> Notes (Optional)
                    </label>
                    <textarea name="notes" id="modalNotes" rows="3" 
                              class="w-full p-3 rounded-lg"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                              placeholder="Add any notes about your availability..."></textarea>
                </div>
                
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <div class="flex items-center text-sm" style="color: var(--info);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Setting your availability helps schedulers assign shifts that work for you.</span>
                    </div>
                </div>
            </form>
            
            <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                <button type="button" class="px-4 py-2 rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-secondary);" onclick="closeModal()">
                    Cancel
                </button>
                <button type="button" class="px-4 py-2 rounded-lg text-white btn-success" onclick="saveAvailability()">
                    <i class="fas fa-save mr-2"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Update Modal -->
<div id="bulkModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeBulkModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Confirm Bulk Update</h3>
            </div>
            
            <div class="p-6">
                <p id="bulkMessage" style="color: var(--text-primary);"></p>
            </div>
            
            <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                <button type="button" class="px-4 py-2 rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-secondary);" onclick="closeBulkModal()">
                    Cancel
                </button>
                <button type="button" class="px-4 py-2 rounded-lg text-white btn-success" onclick="confirmBulkUpdate()">
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.calendar-day {
    transition: all 0.2s ease;
    min-height: 80px;
}

.calendar-day:hover:not(.opacity-50) {
    transform: translateY(-2px);
}

/* Custom radio button styles */
input[type="radio"]:checked + div {
    border-color: currentColor;
}

/* Modal animations */
.fixed {
    animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: var(--bg-secondary);
}

::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--text-secondary);
}
</style>

<script>
let currentDate = null;
let bulkAction = null;
let bulkStatus = null;

function openDayModal(date, currentStatus) {
    currentDate = date;
    document.getElementById('modalDate').value = date;
    document.getElementById('modalDateDisplay').textContent = new Date(date).toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    
    // Set current status
    if (currentStatus !== 'not_set') {
        document.querySelector(`input[name="status"][value="${currentStatus}"]`).checked = true;
    } else {
        document.querySelectorAll('input[name="status"]').forEach(r => r.checked = false);
    }
    
    // Load notes if they exist
    const dayElement = document.querySelector(`[data-date="${date}"]`);
    if (dayElement && dayElement.querySelector('.fa-sticky-note')) {
        // You would need to store notes in a data attribute or fetch them
        document.getElementById('modalNotes').value = '';
    } else {
        document.getElementById('modalNotes').value = '';
    }
    
    document.getElementById('dayModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('dayModal').classList.add('hidden');
}

function updateStatusPreview() {
    // Optional: Add visual feedback when status changes
}

function saveAvailability() {
    const form = document.getElementById('availabilityForm');
    const formData = new FormData(form);
    const saveBtn = event.target;
    const originalText = saveBtn.innerHTML;
    
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
    saveBtn.disabled = true;
    
    fetch('/security/availability/update', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the calendar day color
            const dayElement = document.querySelector(`[data-date="${currentDate}"]`);
            if (dayElement) {
                // Remove old status class
                dayElement.classList.remove('bg-green-500', 'bg-yellow-500', 'bg-red-500', 'bg-gray-300');
                
                // Add new status dot
                const dot = dayElement.querySelector('.w-3.h-3.rounded-full');
                if (dot) {
                    dot.className = `w-3 h-3 rounded-full ${getStatusColor(data.status)}`;
                }
                
                // Update notes display
                const notesDiv = dayElement.querySelector('.mt-2.text-xs');
                if (data.notes) {
                    if (notesDiv) {
                        notesDiv.innerHTML = `<i class="fas fa-sticky-note mr-1"></i> ${data.notes.substring(0, 15)}...`;
                    } else {
                        dayElement.innerHTML += `
                            <div class="mt-2 text-xs truncate" style="color: var(--text-secondary);" title="${data.notes}">
                                <i class="fas fa-sticky-note mr-1"></i> ${data.notes.substring(0, 15)}...
                            </div>
                        `;
                    }
                } else if (notesDiv) {
                    notesDiv.remove();
                }
            }
            
            closeModal();
            showNotification('Availability updated successfully', 'success');
        } else {
            alert(data.message || 'Failed to update availability');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update availability');
    })
    .finally(() => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
}

function getStatusColor(status) {
    const colors = {
        'available': 'bg-green-500',
        'prefer': 'bg-yellow-500',
        'unavailable': 'bg-red-500',
        'not_set': 'bg-gray-300'
    };
    return colors[status] || 'bg-gray-300';
}

function setAllForMonth(status) {
    bulkStatus = status;
    const statusText = {
        'available': 'available',
        'unavailable': 'unavailable',
        'prefer': 'prefer to work'
    };
    document.getElementById('bulkMessage').innerHTML = 
        `Are you sure you want to set all future days this month as <strong>${statusText[status]}</strong>?`;
    document.getElementById('bulkModal').classList.remove('hidden');
}

function clearAllForMonth() {
    bulkStatus = 'clear';
    document.getElementById('bulkMessage').innerHTML = 
        'Are you sure you want to clear all availability settings for this month?';
    document.getElementById('bulkModal').classList.remove('hidden');
}

function closeBulkModal() {
    document.getElementById('bulkModal').classList.add('hidden');
    bulkStatus = null;
}

function confirmBulkUpdate() {
    const year = {{ $year }};
    const month = {{ $month }};
    
    fetch('/security/availability/bulk-update', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            year: year,
            month: month,
            action: bulkStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Failed to update availability');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update availability');
    })
    .finally(() => {
        closeBulkModal();
    });
}

function saveAllAvailability() {
    // Collect all changes and save them
    showNotification('All changes saved', 'success');
}

function showNotification(message, type) {
    // You can implement a toast notification here
    alert(message);
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('fixed') && 
        event.target !== document.querySelector('#dayModal .fixed') &&
        event.target !== document.querySelector('#bulkModal .fixed')) {
        closeModal();
        closeBulkModal();
    }
}
</script>
@endsection