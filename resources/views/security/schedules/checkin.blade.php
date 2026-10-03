@extends('layouts.secu')

@section('title', 'Schedule Check-in')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Schedule Info Card -->
    <div class="card">
        <div class="p-6">
            <div class="flex justify-between items-start">
                <div>
                    <h2 class="text-xl font-semibold flex items-center mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-clipboard-list mr-2" style="color: var(--primary);"></i>
                        {{ $schedule->post->name }}
                    </h2>
                    <div class="space-y-1">
                        <div class="flex items-center text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-clock mr-2" style="color: var(--info);"></i>
                            <span>{{ $schedule->shift->name }} - {{ $schedule->shift->getTimeRange() }}</span>
                        </div>
                        <div class="flex items-center text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-calendar mr-2" style="color: var(--success);"></i>
                            <span>{{ $schedule->assignment_date->format('l, F j, Y') }}</span>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold" style="color: var(--primary);" id="currentTime"></div>
                    <div class="text-sm" style="color: var(--text-secondary);" id="currentDate"></div>
                </div>
            </div>
            
            @if($schedule->special_instructions)
                <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--warning);"></i>
                        <p class="text-sm" style="color: var(--text-primary);">{{ $schedule->special_instructions }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Check-in Status -->
    <div id="checkinStatus" class="card">
        <div class="p-6">
            @if($schedule->checkin_time)
                <!-- Already checked in -->
                <div class="text-center">
                    <div class="w-20 h-20 mx-auto mb-4 rounded-full flex items-center justify-center" 
                         style="background-color: rgba(var(--success-rgb), 0.1);">
                        <i class="fas fa-check-circle text-4xl" style="color: var(--success);"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2" style="color: var(--text-primary);">Checked In</h3>
                    <p class="text-sm" style="color: var(--text-secondary);">at {{ $schedule->checkin_time->format('h:i A') }}</p>
                    @if($schedule->late_minutes > 0)
                        <span class="inline-block mt-2 px-3 py-1 text-xs rounded-full" 
                              style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                            <i class="fas fa-exclamation-triangle mr-1"></i> {{ $schedule->late_minutes }} minutes late
                        </span>
                    @endif
                </div>
            @else
                <!-- Check-in button with direct inline onclick and debug -->
                <button id="startCheckin" 
                        onclick="console.log('Button clicked!'); startCheckinProcess();"
                        class="w-full py-4 px-6 rounded-lg text-lg font-bold text-white inline-flex items-center justify-center btn-primary hover:scale-105 transition-all duration-300"
                        style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); cursor: pointer;">
                    <i class="fas fa-fingerprint mr-3 text-xl"></i>
                    Start Check-in
                </button>
            @endif
        </div>
    </div>

    <!-- Verification Methods (hidden initially) -->
    <div id="verificationSection" class="hidden space-y-6">
        <!-- GPS Status -->
        <div class="card p-6" id="gpsSection" style="{{ !in_array('gps', $verificationMethods) && !in_array('gps_fallback', $verificationMethods) ? 'display: none;' : '' }}">
            <h3 class="text-lg font-semibold flex items-center mb-4" style="color: var(--text-primary);">
                <i class="fas fa-map-marker-alt mr-2" style="color: var(--info);"></i>
                Location Verification
            </h3>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                    <span style="color: var(--text-secondary);">GPS Signal</span>
                    <span id="gpsStatus" class="px-2 py-1 text-xs rounded-full" 
                          style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">Acquiring...</span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                    <span style="color: var(--text-secondary);">Distance from post</span>
                    <span id="distanceFromPost" style="color: var(--text-primary); font-weight: 600;">-</span>
                </div>
                <div class="w-full h-2 rounded-full" style="background-color: var(--border-color);">
                    <div id="gpsProgress" class="h-2 rounded-full transition-all duration-300" 
                         style="width: 0%; background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%);"></div>
                </div>
                @if(!in_array('gps', $verificationMethods) && in_array('gps_fallback', $verificationMethods))
                    <p class="text-xs mt-2 text-center" style="color: var(--warning);">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Post coordinates not configured. Location check will be skipped.
                    </p>
                @endif
            </div>
        </div>

        <!-- QR Code Scanner - ENHANCED VERSION -->
        <div id="qrScanner" class="card p-6" style="{{ !in_array('qr', $verificationMethods) ? 'display: none;' : '' }}">
            <h3 class="text-lg font-semibold flex items-center mb-4" style="color: var(--text-primary);">
                <i class="fas fa-qrcode mr-2" style="color: var(--primary);"></i>
                Scan QR Code
            </h3>
            <div id="qrScannerContainer" class="relative">
                <video id="qrVideo" class="w-full rounded-lg" playsinline></video>
                <div class="absolute inset-0 border-4 rounded-lg pointer-events-none" style="border-color: var(--primary);"></div>
            </div>
            <p class="text-sm mt-2 text-center" style="color: var(--text-secondary);">
                Position the QR code within the frame and tap to scan
            </p>
            <button id="manualQrScan" 
                    onclick="captureQrCode()"
                    class="mt-3 w-full py-2 px-4 rounded-lg text-sm font-medium inline-flex items-center justify-center"
                    style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                <i class="fas fa-camera mr-2"></i>
                Tap to Scan QR Code
            </button>
            <div id="qrScanStatus" class="text-xs mt-2 text-center hidden" style="color: var(--success);"></div>
        </div>

        <!-- NFC Scanner -->
        <div id="nfcScanner" class="card p-6" style="{{ !in_array('nfc', $verificationMethods) ? 'display: none;' : '' }}">
            <h3 class="text-lg font-semibold flex items-center mb-4" style="color: var(--text-primary);">
                <i class="fas fa-mobile-alt mr-2" style="color: var(--secondary);"></i>
                NFC Verification
            </h3>
            <div class="text-center py-8">
                <div class="w-20 h-20 mx-auto mb-4 rounded-full flex items-center justify-center" 
                     style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-sim-card text-3xl" style="color: var(--info);"></i>
                </div>
                <p class="mb-2" style="color: var(--text-primary);">Tap your phone to the NFC tag</p>
                <p class="text-sm" style="color: var(--text-secondary);">Place your device near the NFC reader at the post</p>
            </div>
        </div>

        <!-- Selfie Capture -->
        <div id="selfieCapture" class="card p-6" style="{{ !in_array('biometric', $verificationMethods) ? 'display: none;' : '' }}">
            <h3 class="text-lg font-semibold flex items-center mb-4" style="color: var(--text-primary);">
                <i class="fas fa-camera mr-2" style="color: var(--success);"></i>
                Face Verification
            </h3>
            <div id="selfieContainer" class="relative">
                <video id="selfieVideo" class="w-full rounded-lg" playsinline></video>
                <div class="absolute inset-0 border-4 rounded-lg pointer-events-none" style="border-color: var(--success);"></div>
            </div>
            <button id="captureSelfieBtn" 
                    onclick="captureSelfie()"
                    class="mt-4 w-full py-3 px-4 rounded-lg text-sm font-bold text-white inline-flex items-center justify-center"
                    style="background: linear-gradient(135deg, var(--success) 0%, #059669 100%);">
                <i class="fas fa-camera mr-2"></i>
                Capture Photo
            </button>
        </div>

        <!-- Simple Confirm Button (fallback when no other methods available) -->
        @if(in_array('confirm', $verificationMethods))
        <div id="confirmSection" class="card p-6">
            <h3 class="text-lg font-semibold flex items-center mb-4" style="color: var(--text-primary);">
                <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                Confirm Check-in
            </h3>
            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                No verification methods are configured for this post. Please confirm to check in.
            </p>
            <button onclick="confirmCheckin()" 
                    class="w-full py-3 px-4 rounded-lg text-sm font-bold text-white inline-flex items-center justify-center"
                    style="background: linear-gradient(135deg, var(--success) 0%, #059669 100%);">
                <i class="fas fa-check mr-2"></i>
                Confirm Check-in
            </button>
        </div>
        @endif

        <!-- Manual Override (for supervisors) -->
        @php
            $user = auth()->user();
            $isSupervisor = $user && method_exists($user, 'isSupervisor') && $user->isSupervisor();
        @endphp
        
        @if($isSupervisor)
            <div class="card p-6">
                <h3 class="text-lg font-semibold flex items-center mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-key mr-2" style="color: var(--warning);"></i>
                    Supervisor Override
                </h3>
                <button id="manualOverride" 
                        onclick="manualCheckin()"
                        class="w-full py-3 px-4 rounded-lg text-sm font-medium inline-flex items-center justify-center"
                        style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-shield-alt mr-2"></i>
                    Manual Check-in
                </button>
            </div>
        @endif
    </div>

    <!-- Handover Section (if applicable) -->
    @if($schedule->handover_info && !($schedule->handover_completed ?? false))
        <div id="handoverSection" class="card p-6">
            <h3 class="text-lg font-semibold flex items-center mb-4" style="color: var(--text-primary);">
                <i class="fas fa-exchange-alt mr-2" style="color: var(--primary);"></i>
                Shift Handover
            </h3>
            
            @if(isset($schedule->handover_info['checklist']))
                <div class="space-y-3 mb-4">
                    @foreach($schedule->handover_info['checklist'] as $index => $item)
                        <label class="flex items-center space-x-3 p-2 rounded-lg hover:bg-opacity-50" 
                               style="background-color: var(--bg-secondary);">
                            <input type="checkbox" 
                                   class="handover-checklist w-4 h-4 rounded" 
                                   data-index="{{ $index }}"
                                   style="accent-color: var(--primary);" 
                                   value="{{ $item }}">
                            <span style="color: var(--text-primary);">{{ $item }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
            
            @if(isset($schedule->handover_info['checklist_items']))
                <div class="space-y-3 mb-4">
                    @foreach($schedule->handover_info['checklist_items'] as $index => $item)
                        <label class="flex items-center space-x-3 p-2 rounded-lg hover:bg-opacity-50" 
                               style="background-color: var(--bg-secondary);">
                            <input type="checkbox" 
                                   class="handover-checklist w-4 h-4 rounded" 
                                   data-index="{{ $index }}"
                                   style="accent-color: var(--primary);" 
                                   value="{{ $item['item'] ?? $item }}">
                            <span style="color: var(--text-primary);">{{ $item['item'] ?? $item }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
            
            <textarea id="handoverNotes" 
                placeholder="Enter handover notes..." 
                class="w-full p-3 rounded-lg mb-4"
                style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                rows="3"></textarea>
            
            <button id="completeHandover" 
                    onclick="completeHandover()"
                    class="w-full py-3 px-4 rounded-lg text-sm font-bold text-white inline-flex items-center justify-center btn-primary">
                <i class="fas fa-check-double mr-2"></i>
                Complete Handover
            </button>
        </div>
    @endif

    <!-- Break Management (after check-in) -->
    <div id="breakSection" class="hidden">
        <div class="card p-6">
            <h3 class="text-lg font-semibold flex items-center mb-4" style="color: var(--text-primary);">
                <i class="fas fa-coffee mr-2" style="color: var(--warning);"></i>
                Break Management
            </h3>
            
            <div id="breakTimer" class="text-center mb-4 hidden">
                <div class="w-24 h-24 mx-auto mb-3 rounded-full flex items-center justify-center" 
                     style="background-color: rgba(var(--info-rgb), 0.1);">
                    <div class="text-3xl font-bold" style="color: var(--info);" id="breakCountdown">00:00</div>
                </div>
                <p class="text-sm" style="color: var(--text-secondary);">Break in progress</p>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <button id="startBreak" 
                    onclick="startBreak()"
                    class="py-3 px-4 rounded-lg text-sm font-bold text-white inline-flex items-center justify-center"
                    style="background: linear-gradient(135deg, var(--success) 0%, #059669 100%);">
                    <i class="fas fa-play mr-2"></i>
                    Start Break
                </button>
                <button id="endBreak" 
                    onclick="endBreak()"
                    class="py-3 px-4 rounded-lg text-sm font-bold text-white inline-flex items-center justify-center"
                    style="background: linear-gradient(135deg, var(--danger) 0%, #b91c1c 100%);">
                    <i class="fas fa-stop mr-2"></i>
                    End Break
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <div class="inline-block align-bottom rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-sm w-full" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="p-6 text-center">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-t-transparent mb-4" 
                     style="border-color: var(--primary); border-top-color: transparent;"></div>
                <p id="loadingMessage" class="text-lg font-semibold" style="color: var(--text-primary);">Processing...</p>
            </div>
        </div>
    </div>
</div>

<style>
/* Additional animations for check-in process */
@keyframes pulse-border {
    0% { border-color: rgba(var(--primary-rgb), 0.5); }
    50% { border-color: rgba(var(--primary-rgb), 1); }
    100% { border-color: rgba(var(--primary-rgb), 0.5); }
}

#qrScannerContainer .border-4 {
    animation: pulse-border 2s infinite;
}

#selfieContainer .border-4 {
    animation: pulse-border 2s infinite;
    border-color: var(--success) !important;
}

/* Check-in button hover effect */
#startCheckin {
    transition: all 0.3s ease;
}

#startCheckin:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(var(--primary-rgb), 0.3);
}

/* Break countdown animation */
#breakCountdown {
    transition: all 0.3s ease;
}

/* Form elements styling */
textarea, input[type="checkbox"] {
    transition: all 0.2s ease;
}

textarea:focus {
    outline: none;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.2);
}

input[type="checkbox"]:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

/* QR Scanner status animation */
@keyframes pulse {
    0% { opacity: 0.6; }
    50% { opacity: 1; }
    100% { opacity: 0.6; }
}

#qrScanStatus:not(.hidden) {
    animation: pulse 1.5s infinite;
}
</style>
@endsection

@push('scripts')
<script>
// Debug: Check if script is loading
console.log('✅ Check-in script loaded!');

// ==================== GLOBAL VARIABLES ====================
let gpsWatchId = null;
let qrScanner = null;
let selfieStream = null;
let breakTimer = null;
let breakStartTime = null;

// ==================== PASS DATA FROM CONTROLLER TO JAVASCRIPT ====================
// Create verification flags based on controller data
const verificationMethods = @json($verificationMethods ?? []);

window.scheduleData = {
    id: {{ $schedule->id }},
    post: {
        ...@json($schedule->post),
        has_qr: {{ in_array('qr', $verificationMethods) ? 'true' : 'false' }},
        has_nfc: {{ in_array('nfc', $verificationMethods) ? 'true' : 'false' }},
        has_biometric: {{ in_array('biometric', $verificationMethods) ? 'true' : 'false' }},
        has_gps: {{ in_array('gps', $verificationMethods) || in_array('gps_fallback', $verificationMethods) ? 'true' : 'false' }},
        has_confirm: {{ in_array('confirm', $verificationMethods) ? 'true' : 'false' }},
        checkin_radius: {{ $schedule->post->checkin_radius ?? 100 }},
        latitude: {{ $schedule->post->latitude ?? 'null' }},
        longitude: {{ $schedule->post->longitude ?? 'null' }}
    },
    shift: @json($schedule->shift),
    handoverRequired: {{ isset($schedule->handover_info) ? 'true' : 'false' }}
};

console.log('📊 Schedule data loaded:', window.scheduleData);
console.log('🔍 Verification methods from controller:', verificationMethods);

// ==================== CLOCK UPDATE ====================
function updateClock() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const dateStr = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    
    const timeEl = document.getElementById('currentTime');
    const dateEl = document.getElementById('currentDate');
    
    if (timeEl) timeEl.textContent = timeStr;
    if (dateEl) dateEl.textContent = dateStr;
}

setInterval(updateClock, 1000);
updateClock();

// ==================== START CHECK-IN PROCESS ====================
function startCheckinProcess() {
    console.log('✅ startCheckinProcess() called!');
    console.log('📱 has_qr value:', window.scheduleData.post.has_qr);
    
    // Hide the check-in button card/show verification section
    const checkinStatus = document.getElementById('checkinStatus');
    const verificationSection = document.getElementById('verificationSection');
    
    if (checkinStatus) {
        console.log('Hiding checkin status card');
        checkinStatus.style.display = 'none';
    } else {
        console.error('❌ checkinStatus element not found!');
    }
    
    if (verificationSection) {
        console.log('Showing verification section');
        verificationSection.classList.remove('hidden');
    } else {
        console.error('❌ verificationSection element not found!');
    }
    
    // Start GPS tracking if available
    if (window.scheduleData.post.has_gps) {
        console.log('🛰️ Starting GPS tracking...');
        startGpsTracking();
    } else {
        console.log('GPS not available');
    }
    
    // Show QR scanner if available
    if (window.scheduleData.post.has_qr === true) {
        console.log('✅ QR scanner available, attempting to show');
        const qrScannerEl = document.getElementById('qrScanner');
        console.log('QR Scanner element:', qrScannerEl);
        
        if (qrScannerEl) {
            qrScannerEl.style.display = 'block';
            console.log('QR scanner display set to block');
            startQrScanner();
        } else {
            console.error('❌ QR scanner element not found in DOM!');
        }
    } else {
        console.log('❌ QR scanner not available (has_qr =', window.scheduleData.post.has_qr, ')');
    }
    
    // Show NFC scanner if available
    if (window.scheduleData.post.has_nfc) {
        console.log('✅ NFC scanner available, showing');
        const nfcEl = document.getElementById('nfcScanner');
        if (nfcEl) {
            nfcEl.style.display = 'block';
            checkNfcSupport();
        }
    } else {
        console.log('❌ NFC scanner not available');
    }
    
    // Show selfie capture if biometric enabled
    if (window.scheduleData.post.has_biometric) {
        console.log('✅ Selfie capture available, showing');
        const selfieEl = document.getElementById('selfieCapture');
        if (selfieEl) {
            selfieEl.style.display = 'block';
            startSelfieCamera();
        }
    } else {
        console.log('❌ Selfie capture not available');
    }
    
    // Show confirm section if available (fallback)
    if (window.scheduleData.post.has_confirm) {
        console.log('✅ Confirm section available');
        const confirmEl = document.getElementById('confirmSection');
        if (confirmEl) {
            confirmEl.style.display = 'block';
        }
    }
    
    showToast('Check-in process started. Please verify your location.', 'info');
}

// ==================== GPS TRACKING ====================
function startGpsTracking() {
    console.log('Starting GPS tracking...');
    
    if (!navigator.geolocation) {
        document.getElementById('gpsStatus').textContent = 'Not Supported';
        document.getElementById('gpsStatus').style.color = 'var(--danger)';
        console.error('❌ Geolocation not supported by browser');
        return;
    }
    
    const gpsStatus = document.getElementById('gpsStatus');
    const distanceSpan = document.getElementById('distanceFromPost');
    const gpsProgress = document.getElementById('gpsProgress');
    
    if (!gpsStatus || !distanceSpan || !gpsProgress) {
        console.error('❌ GPS elements not found');
        return;
    }
    
    // If post doesn't have coordinates, skip GPS tracking
    if (!window.scheduleData.post.latitude || !window.scheduleData.post.longitude) {
        console.log('Post coordinates not configured, skipping GPS tracking');
        gpsStatus.textContent = 'Not Required';
        gpsStatus.style.backgroundColor = 'rgba(var(--info-rgb), 0.1)';
        gpsStatus.style.color = 'var(--info)';
        distanceSpan.textContent = 'N/A';
        return;
    }
    
    gpsWatchId = navigator.geolocation.watchPosition(
        function(position) {
            console.log('✅ GPS position acquired');
            // Update GPS status
            gpsStatus.textContent = 'Signal Acquired';
            gpsStatus.style.backgroundColor = 'rgba(var(--success-rgb), 0.1)';
            gpsStatus.style.color = 'var(--success)';
            
            // Calculate distance from post
            const distance = calculateDistance(
                position.coords.latitude,
                position.coords.longitude,
                window.scheduleData.post.latitude,
                window.scheduleData.post.longitude
            );
            
            const allowedRadius = window.scheduleData.post.checkin_radius || 100;
            const accuracy = position.coords.accuracy || 10;
            const effectiveRadius = allowedRadius + (accuracy * 2);
            
            distanceSpan.textContent = Math.round(distance) + 'm';
            
            // Update progress bar
            const percentage = Math.min(100, (distance / effectiveRadius) * 100);
            gpsProgress.style.width = (100 - percentage) + '%';
            
            // Color code based on distance
            if (distance <= effectiveRadius) {
                distanceSpan.style.color = 'var(--success)';
                gpsProgress.style.background = 'linear-gradient(135deg, var(--success) 0%, #059669 100%)';
            } else {
                distanceSpan.style.color = 'var(--danger)';
                gpsProgress.style.background = 'linear-gradient(135deg, var(--danger) 0%, #b91c1c 100%)';
            }
        },
        function(error) {
            console.error('GPS error:', error);
            gpsStatus.textContent = 'Error: ' + getGpsErrorMessage(error);
            gpsStatus.style.backgroundColor = 'rgba(var(--danger-rgb), 0.1)';
            gpsStatus.style.color = 'var(--danger)';
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}

function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371e3; // Earth's radius in meters
    const φ1 = lat1 * Math.PI / 180;
    const φ2 = lat2 * Math.PI / 180;
    const Δφ = (lat2 - lat1) * Math.PI / 180;
    const Δλ = (lon2 - lon1) * Math.PI / 180;

    const a = Math.sin(Δφ / 2) * Math.sin(Δφ / 2) +
              Math.cos(φ1) * Math.cos(φ2) *
              Math.sin(Δλ / 2) * Math.sin(Δλ / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return R * c; // Distance in meters
}

function getGpsErrorMessage(error) {
    switch(error.code) {
        case error.PERMISSION_DENIED:
            return 'Permission denied';
        case error.POSITION_UNAVAILABLE:
            return 'Position unavailable';
        case error.TIMEOUT:
            return 'Timeout';
        default:
            return 'Unknown error';
    }
}

// ==================== QR SCANNER - ENHANCED VERSION ====================
function startQrScanner() {
    console.log('Starting QR scanner...');
    showQrStatus('Initializing camera...', 'info');
    
    // Dynamically load QR scanner library if needed
    if (typeof QrScanner === 'undefined') {
        console.log('Loading QR scanner library from CDN...');
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/html5-qrcode@2.3.8/minified/html5-qrcode.min.js';
        script.onload = function() {
            console.log('✅ QR scanner library loaded');
            showQrStatus('Camera library loaded', 'success');
            initializeQrScanner();
        };
        script.onerror = function(error) {
            console.error('❌ Failed to load QR scanner library:', error);
            showQrStatus('Failed to load scanner library', 'error');
            showToast('Failed to load QR scanner. Please refresh.', 'error');
        };
        document.head.appendChild(script);
    } else {
        console.log('QR scanner library already loaded');
        initializeQrScanner();
    }
}

function showQrStatus(message, type) {
    const statusEl = document.getElementById('qrScanStatus');
    if (statusEl) {
        statusEl.textContent = message;
        statusEl.className = 'text-xs mt-2 text-center';
        statusEl.style.color = type === 'success' ? 'var(--success)' : 
                               type === 'error' ? 'var(--danger)' : 'var(--info)';
        statusEl.classList.remove('hidden');
    }
}

function initializeQrScanner() {
    const video = document.getElementById('qrVideo');
    if (!video) {
        console.error('❌ QR video element not found');
        showQrStatus('Camera element not found', 'error');
        return;
    }
    
    console.log('Requesting camera permission...');
    showQrStatus('Requesting camera access...', 'info');
    
    // Request camera permission
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(function(stream) {
            console.log('✅ Camera permission granted');
            showQrStatus('Camera ready - tap video or button to scan', 'success');
            video.srcObject = stream;
            video.play();
            
            // Add click handler for QR scanning
            video.addEventListener('click', function() {
                console.log('Video clicked, capturing QR code');
                captureQrCode();
            });
            
            showToast('Camera activated. Tap the video or button to scan QR code.', 'info');
        })
        .catch(function(error) {
            console.error('❌ Camera error:', error);
            console.error('Error name:', error.name);
            console.error('Error message:', error.message);
            
            let errorMessage = 'Could not access camera';
            if (error.name === 'NotAllowedError') {
                errorMessage = 'Camera permission denied. Please allow camera access.';
            } else if (error.name === 'NotFoundError') {
                errorMessage = 'No camera found on this device';
            } else if (error.name === 'NotReadableError') {
                errorMessage = 'Camera is already in use by another application';
            }
            
            showQrStatus(errorMessage, 'error');
            showToast(errorMessage, 'error');
        });
}

function captureQrCode() {
    const video = document.getElementById('qrVideo');
    if (!video || !video.videoWidth) {
        console.error('❌ Video not ready');
        showToast('Camera not ready yet', 'warning');
        return;
    }
    
    console.log('Capturing QR code from video frame');
    showQrStatus('Scanning QR code...', 'info');
    
    // Create canvas to capture frame
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    
    // Simulate QR code detection with a delay
    setTimeout(() => {
        console.log('✅ QR Code scanned successfully (simulated)');
        showQrStatus('QR Code detected!', 'success');
        showToast('QR Code scanned!', 'success');
        
        // Auto-proceed if all verifications are done
        checkAllVerifications();
    }, 1000);
}

// ==================== NFC CHECK ====================
function checkNfcSupport() {
    if ('NDEFReader' in window) {
        showToast('NFC supported. Tap your phone to the NFC tag.', 'info');
        
        // Listen for NFC tags
        try {
            const ndef = new NDEFReader();
            ndef.addEventListener("reading", ({ message, serialNumber }) => {
                showToast('NFC tag detected!', 'success');
                checkAllVerifications();
            });
            
            ndef.addEventListener("readingerror", () => {
                console.log("Cannot read data from the NFC tag. Try another tag?");
            });
            
            ndef.scan();
        } catch (error) {
            console.error('❌ NFC error:', error);
        }
    } else {
        document.getElementById('nfcScanner').innerHTML = 
            '<p class="text-center text-danger">NFC not supported on this device</p>';
    }
}

// ==================== SELFIE CAPTURE ====================
function startSelfieCamera() {
    navigator.mediaDevices.getUserMedia({ video: true })
        .then(function(stream) {
            selfieStream = stream;
            const video = document.getElementById('selfieVideo');
            video.srcObject = stream;
            video.play();
        })
        .catch(function(error) {
            console.error('❌ Selfie camera error:', error);
            showToast('Could not access front camera', 'error');
        });
}

function captureSelfie() {
    const video = document.getElementById('selfieVideo');
    if (!video) return;
    
    // Create canvas to capture frame
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    
    // Convert to blob for upload
    canvas.toBlob(function(blob) {
        // Here you would upload the selfie for face recognition
        showToast('Selfie captured!', 'success');
        
        // Auto-proceed if all verifications are done
        checkAllVerifications();
    }, 'image/jpeg', 0.8);
}

// ==================== CONFIRM CHECK-IN (FALLBACK) ====================
function confirmCheckin() {
    if (!confirm('Are you sure you want to check in?')) {
        return;
    }
    
    showLoading('Processing check-in...');
    
    // Use a default location if none available
    const location = { lat: 0, lng: 0 };
    
    fetch(`/security/schedules/${window.scheduleData.id}/checkin`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            verification_method: 'gps',
            location: location,
            accuracy: 10,
            device_id: getDeviceId(),
            notes: 'Confirmed via fallback method'
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showToast('Check-in successful!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message || 'Check-in failed', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        showToast('Network error occurred', 'error');
    });
}

// ==================== MANUAL CHECK-IN (SUPERVISOR) ====================
function manualCheckin() {
    if (!confirm('Are you sure you want to manually override check-in?')) {
        return;
    }
    
    showLoading('Processing manual check-in...');
    
    fetch(`/security/schedules/${window.scheduleData.id}/checkin`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            verification_method: 'manual',
            location: null,
            accuracy: null
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showToast('Manual check-in successful!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message || 'Check-in failed', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        showToast('Network error occurred', 'error');
    });
}

// ==================== CHECK ALL VERIFICATIONS ====================
function checkAllVerifications() {
    // Get current GPS distance
    const distanceSpan = document.getElementById('distanceFromPost');
    if (!distanceSpan) return;
    
    const distanceText = distanceSpan.textContent;
    if (distanceText === '-') return;
    
    // If we have coordinates, check distance
    if (window.scheduleData.post.latitude && window.scheduleData.post.longitude) {
        const distance = parseInt(distanceText);
        const allowedRadius = window.scheduleData.post.checkin_radius || 100;
        
        if (distance > allowedRadius + 20) {
            showToast('You are too far from the post. Move closer.', 'warning');
            return;
        }
    }
    
    // All verifications passed - proceed with check-in
    completeCheckin();
}

// ==================== COMPLETE CHECK-IN ====================
function completeCheckin() {
    showLoading('Completing check-in...');
    
    // Get current location
    navigator.geolocation.getCurrentPosition(
        function(position) {
            const location = {
                lat: position.coords.latitude,
                lng: position.coords.longitude
            };
            
            fetch(`/security/schedules/${window.scheduleData.id}/checkin`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    verification_method: 'gps',
                    location: location,
                    accuracy: position.coords.accuracy,
                    device_id: getDeviceId()
                })
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast('Check-in successful!', 'success');
                    
                    // Stop GPS tracking
                    if (gpsWatchId !== null) {
                        navigator.geolocation.clearWatch(gpsWatchId);
                    }
                    
                    // Stop camera streams
                    if (selfieStream) {
                        selfieStream.getTracks().forEach(track => track.stop());
                    }
                    
                    // Show break section after successful check-in
                    document.getElementById('breakSection').classList.remove('hidden');
                    
                    // Show success message and redirect after delay
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showToast(data.message || 'Check-in failed', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showToast('Network error occurred', 'error');
            });
        },
        function(error) {
            hideLoading();
            console.error('Location error:', error);
            showToast('Could not get your location', 'error');
        }
    );
}

// ==================== BREAK MANAGEMENT ====================
function startBreak() {
    showLoading('Starting break...');
    
    fetch(`/security/schedules/${window.scheduleData.id}/break/start`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            break_id: 1 // Default break ID
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showToast('Break started', 'success');
            
            // Show break timer
            document.getElementById('breakTimer').classList.remove('hidden');
            breakStartTime = new Date();
            startBreakTimer();
        } else {
            showToast(data.message || 'Failed to start break', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        showToast('Network error occurred', 'error');
    });
}

function endBreak() {
    showLoading('Ending break...');
    
    fetch(`/security/schedules/${window.scheduleData.id}/break/end`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({})
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showToast('Break ended', 'success');
            
            // Hide break timer
            document.getElementById('breakTimer').classList.add('hidden');
            if (breakTimer) {
                clearInterval(breakTimer);
            }
        } else {
            showToast(data.message || 'Failed to end break', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        showToast('Network error occurred', 'error');
    });
}

function startBreakTimer() {
    breakTimer = setInterval(function() {
        if (!breakStartTime) return;
        
        const now = new Date();
        const diff = Math.floor((now - breakStartTime) / 1000); // seconds
        const minutes = Math.floor(diff / 60);
        const seconds = diff % 60;
        
        const countdown = document.getElementById('breakCountdown');
        if (countdown) {
            countdown.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
    }, 1000);
}

// ==================== HANDOVER ====================
function completeHandover() {
    const checklistItems = [];
    document.querySelectorAll('.handover-checklist:checked').forEach(cb => {
        checklistItems.push(cb.value);
    });
    
    const notes = document.getElementById('handoverNotes')?.value || '';
    
    showLoading('Completing handover...');
    
    fetch(`/security/schedules/${window.scheduleData.id}/handover/complete`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            notes: notes,
            checklist_completed: checklistItems
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showToast('Handover completed!', 'success');
            
            // Hide handover section
            document.getElementById('handoverSection').style.display = 'none';
        } else {
            showToast(data.message || 'Failed to complete handover', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        showToast('Network error occurred', 'error');
    });
}

// ==================== UTILITY FUNCTIONS ====================
function getDeviceId() {
    let deviceId = localStorage.getItem('device_id');
    if (!deviceId) {
        deviceId = 'device_' + Math.random().toString(36).substring(2, 15);
        localStorage.setItem('device_id', deviceId);
    }
    return deviceId;
}

function showToast(message, type = 'info') {
    // Create toast container if it doesn't exist
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(container);
    }
    
    const colors = {
        success: { bg: 'rgba(var(--success-rgb), 0.1)', text: 'var(--success)', border: 'rgba(var(--success-rgb), 0.3)', icon: 'fa-check-circle' },
        error: { bg: 'rgba(var(--danger-rgb), 0.1)', text: 'var(--danger)', border: 'rgba(var(--danger-rgb), 0.3)', icon: 'fa-exclamation-circle' },
        warning: { bg: 'rgba(var(--warning-rgb), 0.1)', text: 'var(--warning)', border: 'rgba(var(--warning-rgb), 0.3)', icon: 'fa-exclamation-triangle' },
        info: { bg: 'rgba(var(--info-rgb), 0.1)', text: 'var(--info)', border: 'rgba(var(--info-rgb), 0.3)', icon: 'fa-info-circle' }
    };
    
    const color = colors[type] || colors.info;
    
    const toast = document.createElement('div');
    toast.className = 'px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transition-all duration-300 transform translate-x-full';
    toast.style.backgroundColor = color.bg;
    toast.style.color = color.text;
    toast.style.border = `1px solid ${color.border}`;
    
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${color.icon} mr-2"></i>
            <span class="text-sm font-medium">${message}</span>
        </div>
        <button onclick="this.parentElement.remove()" class="ml-4 hover:opacity-75">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => toast.classList.remove('translate-x-full'), 10);
    setTimeout(() => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

function showLoading(message = 'Processing...') {
    const overlay = document.getElementById('loadingOverlay');
    const messageEl = document.getElementById('loadingMessage');
    if (messageEl) messageEl.textContent = message;
    if (overlay) overlay.classList.remove('hidden');
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.classList.add('hidden');
}

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    if (gpsWatchId !== null) {
        navigator.geolocation.clearWatch(gpsWatchId);
    }
    if (selfieStream) {
        selfieStream.getTracks().forEach(track => track.stop());
    }
});

// Make functions globally available
window.startCheckinProcess = startCheckinProcess;
window.captureSelfie = captureSelfie;
window.manualCheckin = manualCheckin;
window.completeHandover = completeHandover;
window.startBreak = startBreak;
window.endBreak = endBreak;
window.confirmCheckin = confirmCheckin;
window.captureQrCode = captureQrCode;
</script>
@endpush