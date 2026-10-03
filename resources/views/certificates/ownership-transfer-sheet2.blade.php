{{-- certificates/ownership-transfer-sheet2.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Property Ownership Transfer Certificate - Page 2 of 2</title>
    
    <!-- ============ FAVICON ============ -->
    @php
        $settings = \App\Models\SystemSetting::getSettings();
    @endphp
    @if($settings->hasFavicon())
        <link rel="icon" href="{{ $settings->getFaviconUrl() }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ $settings->getFaviconUrl() }}" type="image/x-icon">
        <link rel="apple-touch-icon" href="{{ $settings->getFaviconUrl() }}">
        <!-- Additional favicon sizes for better browser support -->
        <link rel="icon" type="image/png" sizes="16x16" href="{{ $settings->getFaviconUrl() }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ $settings->getFaviconUrl() }}">
        <link rel="icon" type="image/png" sizes="64x64" href="{{ $settings->getFaviconUrl() }}">
    @else
        <!-- Default favicon fallback -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    @endif
    
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            background: white;
            margin: 0;
            padding: 0;
        }
        
        .certificate {
            width: 100%;
            height: 100vh;
            padding: 15mm 12mm 12mm 12mm;
            background: white;
            page-break-after: avoid;
            break-inside: avoid;
        }
        
        .border {
            border: 3px double #c5a028;
            padding: 8mm;
            height: 100%;
            position: relative;
        }
        
        .watermark {
            position: absolute;
            bottom: 30%;
            right: 5%;
            opacity: 0.06;
            font-size: 48px;
            font-weight: bold;
            transform: rotate(-15deg);
        }
        
        .header {
            text-align: center;
            margin-bottom: 6mm;
            border-bottom: 2px solid #c5a028;
            padding-bottom: 4mm;
        }
        
        .continuation {
            background: #6b7280;
            color: white;
            display: inline-block;
            padding: 2px 10px;
            font-size: 8px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        
        h2 {
            font-size: 16px;
            color: #1a3e60;
            margin: 3px 0;
        }
        
        .header-subtitle {
            font-size: 8px;
            color: #666;
        }
        
        /* Three Columns */
        .three-columns {
            display: flex;
            gap: 6mm;
            margin-bottom: 6mm;
        }
        
        .col {
            flex: 1;
        }
        
        .section {
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            margin-bottom: 5mm;
        }
        
        .section-title {
            background: #f5f5f5;
            padding: 3mm 4mm;
            font-size: 9px;
            font-weight: bold;
            color: #1a3e60;
            border-bottom: 2px solid #c5a028;
        }
        
        .section-body {
            padding: 3mm;
        }
        
        /* Units Table */
        .units-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
        }
        
        .units-table th {
            background: #1a3e60;
            color: white;
            padding: 2px 3px;
            text-align: left;
            font-size: 7px;
        }
        
        .units-table td {
            padding: 2px 3px;
            border-bottom: 1px solid #eee;
            font-size: 7px;
        }
        
        /* Approval Grid */
        .approval-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 3px;
        }
        
        .approval-item {
            text-align: center;
            padding: 2mm;
            background: #fafafa;
            border-radius: 3px;
        }
        
        .approval-label {
            font-size: 6px;
            font-weight: bold;
            color: #888;
        }
        
        .approval-value {
            font-size: 8px;
            font-weight: bold;
            color: #1a3e60;
            margin-top: 2px;
        }
        
        /* Signature */
        .signature-box {
            text-align: center;
            margin-bottom: 4mm;
        }
        
        .signature-line {
            border-bottom: 1px solid #333;
            width: 100%;
            margin: 4px 0;
        }
        
        .signature-label {
            font-size: 7px;
            font-weight: bold;
            color: #666;
        }
        
        /* Seal */
        .seal {
            text-align: center;
            margin: 4mm 0;
        }
        
        .seal-circle {
            width: 45px;
            height: 45px;
            border: 2px solid #c5a028;
            border-radius: 50%;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .seal-circle i {
            font-size: 18px;
            color: #c5a028;
        }
        
        .seal-text {
            font-size: 5px;
            color: #8b6914;
        }
        
        /* Legal Notice */
        .legal-notice {
            background: #fef9e6;
            border-left: 3px solid #c5a028;
            padding: 3mm;
            margin: 4mm 0;
            font-size: 7px;
            line-height: 1.3;
        }
        
        .verification {
            background: #f5f5f5;
            padding: 2mm;
            text-align: center;
            border-radius: 3px;
            font-size: 7px;
        }
        
        .verification-code {
            font-family: monospace;
            font-size: 9px;
            font-weight: bold;
        }
        
        .footer {
            position: absolute;
            bottom: 12mm;
            left: 20mm;
            right: 20mm;
            text-align: center;
            font-size: 7px;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 3mm;
        }
        
        .page-number {
            position: absolute;
            bottom: 12mm;
            right: 20mm;
            font-size: 9px;
            color: #999;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .border {
                border: 2px solid #c5a028;
            }
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="border">
            
            <!-- Watermark -->
            <div class="watermark">
                @if(isset($systemSettings) && $systemSettings && $systemSettings->system_logo)
                    <div style="font-size: 60px;">🏛️</div>
                @else
                    {{ $systemShortName ?? 'HSM' }}
                @endif
            </div>
            
            <!-- Header -->
            <div class="header">
                <div class="continuation">CONTINUATION</div>
                <h2>CERTIFICATE OF OWNERSHIP TRANSFER</h2>
                <div class="header-subtitle">Additional Information & Legal Validation</div>
            </div>
            
            <!-- Three Columns -->
            <div class="three-columns">
                
                <!-- Column 1: Units -->
                <div class="col">
                    @if(isset($units) && $units->count() > 0)
                    <div class="section">
                        <div class="section-title">PROPERTY UNITS</div>
                        <div class="section-body">
                            <table class="units-table">
                                <thead>
                                    <tr><th>Unit</th><th>Type</th><th>Tenant</th></tr>
                                </thead>
                                <tbody>
                                    @foreach($units->take(4) as $unit)
                                    <tr>
                                        <td>{{ $unit->unit_number }}</td>
                                        <td>{{ $unit->unit_type ?? 'N/A' }}</td>
                                        <td>{{ $unit->tenant->name ?? 'Vacant' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if(isset($tenants_transferred) && $tenants_transferred > 0)
                            <div style="background: #e8f5e9; padding: 2mm; margin-top: 3mm; border-radius: 3px; font-size: 7px; text-align: center;">
                                ✓ {{ $tenants_transferred }} tenant(s) transferred
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
                
                <!-- Column 2: Approval -->
                <div class="col">
                    <div class="section">
                        <div class="section-title">APPROVAL WORKFLOW</div>
                        <div class="section-body">
                            <div class="approval-grid">
                                <div class="approval-item">
                                    <div class="approval-label">REQUESTED BY</div>
                                    <div class="approval-value">{{ $transfer->requestedBy->name ?? 'N/A' }}</div>
                                </div>
                                <div class="approval-item">
                                    <div class="approval-label">APPROVED BY</div>
                                    <div class="approval-value">{{ $transfer->approvedBy->name ?? 'Admin' }}</div>
                                </div>
                                <div class="approval-item">
                                    <div class="approval-label">COMPLETED BY</div>
                                    <div class="approval-value">{{ $transfer->completedBy->name ?? 'Admin' }}</div>
                                </div>
                                <div class="approval-item">
                                    <div class="approval-label">PROCESSING</div>
                                    <div class="approval-value">{{ $processingDays ?? 'N/A' }} days</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    @if(isset($hasDigitalSignature) && $hasDigitalSignature)
                    <div class="section">
                        <div class="section-title">DIGITAL SIGNATURE</div>
                        <div class="section-body">
                            <div class="info-row" style="display: flex; justify-content: space-between;">
                                <span style="font-size: 8px; font-weight: bold;">Status:</span>
                                <span style="font-size: 8px;">@if($signatureVerified ?? false) ✓ Verified @else ⚠ Pending @endif</span>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
                
                <!-- Column 3: Signatures -->
                <div class="col">
                    <div class="section">
                        <div class="section-title">SIGNATURES</div>
                        <div class="section-body">
                            <div class="signature-box">
                                <div class="signature-line"></div>
                                <div class="signature-label">TRANSFEROR</div>
                                <div style="font-size: 8px;">{{ $current_owner->name ?? 'Previous Owner' }}</div>
                            </div>
                            <div class="signature-box">
                                <div class="signature-line"></div>
                                <div class="signature-label">TRANSFEREE</div>
                                <div style="font-size: 8px;">{{ $new_owner->name ?? 'New Owner' }}</div>
                            </div>
                            <div class="signature-box">
                                <div class="signature-line"></div>
                                <div class="signature-label">WITNESSED BY</div>
                                <div style="font-size: 8px;">Authorized Officer</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="seal">
                        <div class="seal-circle">
                            <i>⚖️</i>
                            <div class="seal-text">OFFICIAL SEAL</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Legal Notice -->
            <div class="legal-notice">
                <strong>📜 LEGAL NOTICE:</strong> This certificate confirms the legal transfer of property ownership. The Transferee assumes all rights and obligations effective from the Transfer Date.
            </div>
            
            <!-- Verification -->
            <div class="verification">
                <strong>VERIFICATION CODE:</strong> <span class="verification-code">{{ $certificate_number ?? 'OTC-' . strtoupper(uniqid()) }}</span>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                This is a computer-generated document. Valid without signature.
            </div>
            <div class="page-number">Page 2 of 2</div>
            
        </div>
    </div>
</body>
</html>