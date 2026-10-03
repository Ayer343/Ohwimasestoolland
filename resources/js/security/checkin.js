// resources/js/security/checkin.js

class SmartCheckinSystem {
    constructor() {
        this.watchId = null;
        this.currentLocation = null;
        this.verificationResults = {};
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.checkBrowserSupport();
        this.loadPendingVerifications();
    }

    setupEventListeners() {
        document.getElementById('startCheckin')?.addEventListener('click', () => this.startCheckin());
        document.getElementById('verifyQR')?.addEventListener('click', () => this.scanQR());
        document.getElementById('verifyNFC')?.addEventListener('click', () => this.scanNFC());
        document.getElementById('takeSelfie')?.addEventListener('click', () => this.captureSelfie());
        document.getElementById('manualOverride')?.addEventListener('click', () => this.requestManualOverride());
        
        // Break management
        document.getElementById('startBreak')?.addEventListener('click', () => this.startBreak());
        document.getElementById('endBreak')?.addEventListener('click', () => this.endBreak());
        
        // Handover
        document.getElementById('completeHandover')?.addEventListener('click', () => this.completeHandover());
    }

    checkBrowserSupport() {
        if (!navigator.geolocation) {
            this.showError('Geolocation is not supported by your browser');
            return false;
        }
        
        if (!navigator.mediaDevices?.getUserMedia) {
            this.showWarning('Camera access may not be available');
        }
        
        return true;
    }

    async startCheckin() {
        try {
            this.showLoading('Starting check-in process...');
            
            // Step 1: Get location
            const location = await this.getCurrentLocation();
            
            // Step 2: Determine best verification method
            const method = await this.determineVerificationMethod();
            
            // Step 3: Perform verification
            const verificationResult = await this.performVerification(method, location);
            
            // Step 4: Submit check-in
            const result = await this.submitCheckin(location, method, verificationResult);
            
            if (result.success) {
                this.showSuccess('Check-in successful!');
                this.updateUIAfterCheckin(result);
                this.scheduleBreakReminders(result.break_schedule);
            } else {
                this.handleCheckinFailure(result);
            }
            
        } catch (error) {
            this.handleError(error);
        } finally {
            this.hideLoading();
        }
    }

    async getCurrentLocation() {
        return new Promise((resolve, reject) => {
            const options = {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            };

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.currentLocation = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                        accuracy: position.coords.accuracy,
                        timestamp: position.timestamp
                    };
                    
                    // Start watching position for continuous verification
                    this.startLocationWatch();
                    
                    resolve(this.currentLocation);
                },
                (error) => {
                    this.handleLocationError(error);
                    reject(error);
                },
                options
            );
        });
    }

    startLocationWatch() {
        if (this.watchId) return;

        const options = {
            enableHighAccuracy: true,
            timeout: 5000,
            maximumAge: 0
        };

        this.watchId = navigator.geolocation.watchPosition(
            (position) => {
                this.currentLocation = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                    accuracy: position.coords.accuracy,
                    timestamp: position.timestamp
                };
                
                // Check if still within allowed radius
                this.verifyLocationBoundary();
            },
            (error) => console.warn('Location watch error:', error),
            options
        );
    }

    stopLocationWatch() {
        if (this.watchId) {
            navigator.geolocation.clearWatch(this.watchId);
            this.watchId = null;
        }
    }

    async determineVerificationMethod() {
        const availableMethods = [];
        
        // Check GPS
        if (this.currentLocation) {
            availableMethods.push('gps');
        }
        
        // Check camera for QR/selfie
        if (navigator.mediaDevices?.getUserMedia) {
            availableMethods.push('qr', 'biometric');
        }
        
        // Check NFC support (Android/Chrome)
        if ('NDEFReader' in window) {
            availableMethods.push('nfc');
        }
        
        // Get post capabilities from server
        const postInfo = await this.getPostInfo();
        
        // Prefer methods based on post configuration
        if (postInfo.requires_qr && availableMethods.includes('qr')) {
            return 'qr';
        } else if (postInfo.has_nfc && availableMethods.includes('nfc')) {
            return 'nfc';
        } else if (postInfo.requires_biometric && availableMethods.includes('biometric')) {
            return 'biometric';
        }
        
        return 'gps'; // Default to GPS
    }

    async performVerification(method, location) {
        switch(method) {
            case 'gps':
                return await this.verifyGPS(location);
            case 'qr':
                return await this.scanQR();
            case 'nfc':
                return await this.scanNFC();
            case 'biometric':
                return await this.captureSelfie();
            default:
                throw new Error('Unknown verification method');
        }
    }

    async scanQR() {
        return new Promise(async (resolve, reject) => {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { facingMode: 'environment' } 
                });
                
                const video = document.createElement('video');
                video.srcObject = stream;
                video.setAttribute('playsinline', true);
                video.play();
                
                // Show QR scanner UI
                this.showQRScanner(video);
                
                // Initialize QR scanner (using library like jsQR)
                const scanInterval = setInterval(() => {
                    const canvas = document.createElement('canvas');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    const context = canvas.getContext('2d');
                    context.drawImage(video, 0, 0, canvas.width, canvas.height);
                    
                    const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
                    const code = jsQR(imageData.data, canvas.width, canvas.height);
                    
                    if (code) {
                        clearInterval(scanInterval);
                        stream.getTracks().forEach(track => track.stop());
                        this.hideQRScanner();
                        
                        resolve({
                            method: 'qr',
                            verified: true,
                            code: code.data,
                            timestamp: new Date().toISOString()
                        });
                    }
                }, 500);
                
                // Timeout after 30 seconds
                setTimeout(() => {
                    clearInterval(scanInterval);
                    stream.getTracks().forEach(track => track.stop());
                    this.hideQRScanner();
                    reject(new Error('QR scan timeout'));
                }, 30000);
                
            } catch (error) {
                reject(error);
            }
        });
    }

    async scanNFC() {
        return new Promise((resolve, reject) => {
            if (!('NDEFReader' in window)) {
                reject(new Error('NFC not supported'));
                return;
            }

            const reader = new NDEFReader();
            
            reader.addEventListener('reading', (event) => {
                const decoder = new TextDecoder();
                for (const record of event.message.records) {
                    if (record.recordType === "text") {
                        const text = decoder.decode(record.data);
                        resolve({
                            method: 'nfc',
                            verified: true,
                            tag_id: text,
                            timestamp: new Date().toISOString()
                        });
                    }
                }
            });

            reader.addEventListener('readingerror', (error) => {
                reject(error);
            });

            reader.scan().catch(error => {
                reject(error);
            });

            // Timeout after 30 seconds
            setTimeout(() => {
                reject(new Error('NFC scan timeout'));
            }, 30000);
        });
    }

    async captureSelfie() {
        return new Promise(async (resolve, reject) => {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { facingMode: 'user' } 
                });
                
                const video = document.createElement('video');
                video.srcObject = stream;
                video.setAttribute('playsinline', true);
                video.play();
                
                // Show selfie capture UI
                this.showSelfieCapture(video);
                
                // Add capture button
                const captureBtn = document.createElement('button');
                captureBtn.textContent = 'Capture';
                captureBtn.onclick = () => {
                    const canvas = document.createElement('canvas');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    const context = canvas.getContext('2d');
                    context.drawImage(video, 0, 0);
                    
                    const imageData = canvas.toDataURL('image/jpeg', 0.8);
                    
                    stream.getTracks().forEach(track => track.stop());
                    this.hideSelfieCapture();
                    
                    resolve({
                        method: 'biometric',
                        verified: true, // Will be verified on server
                        selfie: imageData,
                        timestamp: new Date().toISOString()
                    });
                };
                
                document.getElementById('selfieCapture').appendChild(captureBtn);
                
            } catch (error) {
                reject(error);
            }
        });
    }

    async submitCheckin(location, method, verification) {
        const response = await fetch(`/security/schedules/${scheduleId}/smart-checkin`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                location: {
                    lat: location.lat,
                    lng: location.lng
                },
                accuracy: location.accuracy,
                verification_method: method,
                verification_code: verification.code,
                device_id: this.getDeviceId(),
                selfie: verification.selfie,
                timestamp: new Date().toISOString()
            })
        });

        return await response.json();
    }

    async startBreak() {
        try {
            const location = await this.getCurrentLocation();
            const breakId = this.getSelectedBreakId();
            
            const response = await fetch(`/security/schedules/${scheduleId}/break/start`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    break_id: breakId,
                    location: {
                        lat: location.lat,
                        lng: location.lng
                    }
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Break started');
                this.startBreakTimer(result.break.duration);
            }
            
        } catch (error) {
            this.handleError(error);
        }
    }

    async endBreak() {
        try {
            const location = await this.getCurrentLocation();
            
            const response = await fetch(`/security/schedules/${scheduleId}/break/end`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    location: {
                        lat: location.lat,
                        lng: location.lng
                    },
                    notes: this.getBreakNotes()
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess(`Break ended. Duration: ${result.duration} minutes`);
                this.stopBreakTimer();
            }
            
        } catch (error) {
            this.handleError(error);
        }
    }

    async completeHandover() {
        const checklist = this.getCompletedChecklist();
        const notes = document.getElementById('handoverNotes').value;
        
        try {
            const response = await fetch(`/security/schedules/${scheduleId}/handover/complete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    checklist_completed: checklist,
                    notes: notes
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Handover completed');
                this.enableCheckout();
            }
            
        } catch (error) {
            this.handleError(error);
        }
    }

    // Utility methods
    getDeviceId() {
        let deviceId = localStorage.getItem('device_id');
        
        if (!deviceId) {
            deviceId = 'device_' + Math.random().toString(36).substring(2, 15) + 
                      Math.random().toString(36).substring(2, 15);
            localStorage.setItem('device_id', deviceId);
        }
        
        return deviceId;
    }

    verifyLocationBoundary() {
        if (!this.currentLocation || !this.postLocation) return;
        
        const distance = this.calculateDistance(
            this.currentLocation.lat,
            this.currentLocation.lng,
            this.postLocation.lat,
            this.postLocation.lng
        );
        
        if (distance > this.postLocation.radius) {
            this.showWarning('You have left the allowed area!');
            this.logBoundaryViolation();
        }
    }

    calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371e3; // Earth's radius in meters
        const φ1 = lat1 * Math.PI/180;
        const φ2 = lat2 * Math.PI/180;
        const Δφ = (lat2-lat1) * Math.PI/180;
        const Δλ = (lon2-lon1) * Math.PI/180;

        const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
                Math.cos(φ1) * Math.cos(φ2) *
                Math.sin(Δλ/2) * Math.sin(Δλ/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));

        return R * c;
    }

    scheduleBreakReminders(breakSchedule) {
        if (!breakSchedule) return;
        
        breakSchedule.forEach((break_item, index) => {
            const breakTime = new Date(break_item.start_time).getTime();
            const now = new Date().getTime();
            const timeUntilBreak = breakTime - now;
            
            if (timeUntilBreak > 0) {
                setTimeout(() => {
                    this.showNotification('Break Time', `Time for your ${break_item.duration} minute break`);
                }, timeUntilBreak - 5 * 60 * 1000); // 5 minutes before
            }
        });
    }

    // UI methods
    showLoading(message) {
        document.getElementById('loadingOverlay').classList.remove('hidden');
        document.getElementById('loadingMessage').textContent = message;
    }

    hideLoading() {
        document.getElementById('loadingOverlay').classList.add('hidden');
    }

    showSuccess(message) {
        // Show success toast
        this.showToast(message, 'success');
    }

    showError(message) {
        this.showToast(message, 'error');
    }

    showWarning(message) {
        this.showToast(message, 'warning');
    }

    showToast(message, type) {
        // Implementation depends on your UI framework
        console.log(`[${type}] ${message}`);
    }

    handleError(error) {
        console.error('Check-in error:', error);
        this.showError(error.message || 'An unexpected error occurred');
    }

    handleCheckinFailure(result) {
        if (result.warnings) {
            result.warnings.forEach(warning => this.showWarning(warning));
        }
        
        if (result.verification_results) {
            this.showVerificationDetails(result.verification_results);
        }
    }

    loadPendingVerifications() {
        // Check if there was an incomplete check-in
        const pending = localStorage.getItem('pending_checkin');
        if (pending) {
            this.showWarning('You have a pending check-in from your last session');
        }
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    window.smartCheckin = new SmartCheckinSystem();
});