{{-- resources/views/certificates/ownership-transfer.blade.php --}}
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Certificate of Property Ownership Transfer</title>

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
    size: A4 portrait;
    margin: 0;
}

body {
    margin: 0;
    padding: 0;
    font-family: 'DejaVu Sans', 'Georgia', 'Times New Roman', serif;
    background: #e8e8e8;
}

.certificate {
    width: 210mm;
    height: 297mm;
    background: #fff;
    margin: 0 auto;
    position: relative;
    box-sizing: border-box;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

/* ===== DECORATIVE BORDERS ===== */
.frame-outer {
    position: absolute;
    top: 8mm;
    left: 8mm;
    right: 8mm;
    bottom: 8mm;
    border: 3px double #c6a54a;
}

.frame-inner {
    position: absolute;
    top: 12mm;
    left: 12mm;
    right: 12mm;
    bottom: 12mm;
    border: 1px solid #d8b75c;
}

/* Corner Ornaments */
.corner {
    position: absolute;
    width: 25px;
    height: 25px;
    border-color: #c6a54a;
    border-style: solid;
    border-width: 0;
}

.corner-tl {
    top: 6mm;
    left: 6mm;
    border-top-width: 3px;
    border-left-width: 3px;
}

.corner-tr {
    top: 6mm;
    right: 6mm;
    border-top-width: 3px;
    border-right-width: 3px;
}

.corner-bl {
    bottom: 6mm;
    left: 6mm;
    border-bottom-width: 3px;
    border-left-width: 3px;
}

.corner-br {
    bottom: 6mm;
    right: 6mm;
    border-bottom-width: 3px;
    border-right-width: 3px;
}

/* ===== WATERMARK ===== */
.watermark {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-25deg);
    opacity: 0.04;
    font-size: 100px;
    font-weight: bold;
    letter-spacing: 15px;
    color: #000;
    white-space: nowrap;
    text-transform: uppercase;
}

/* ===== CONTENT AREA ===== */
.content {
    position: absolute;
    top: 20mm;
    left: 22mm;
    right: 22mm;
    bottom: 22mm;
}

/* ===== HEADER SECTION ===== */
.header {
    text-align: center;
    margin-bottom: 8mm;
}

.logo {
    max-height: 55px;
    margin-bottom: 4px;
}

.system-name {
    font-size: 13px;
    font-weight: bold;
    letter-spacing: 2.5px;
    color: #0f2f57;
    text-transform: uppercase;
    margin-top: 3px;
}

.title {
    font-size: 26px;
    font-weight: bold;
    margin-top: 8px;
    color: #0f2f57;
    letter-spacing: 3px;
    text-transform: uppercase;
}

.subtitle {
    font-size: 10px;
    margin-top: 4px;
    color: #888;
    font-style: italic;
}

/* Decorative Line */
.decorative-line {
    width: 60px;
    height: 2px;
    background: #c6a54a;
    margin: 6px auto 0;
}

/* ===== MAIN STATEMENT ===== */
.statement {
    margin-top: 20px;
    text-align: center;
    line-height: 1.8;
    font-size: 12px;
    color: #333;
    background: #fefef8;
    padding: 12px;
    border-radius: 8px;
}

.highlight-name {
    font-size: 20px;
    font-weight: bold;
    color: #0f2f57;
    margin: 10px 0;
    letter-spacing: 1px;
    text-transform: uppercase;
}

/* ===== PROPERTY BOX ===== */
.property-box {
    margin-top: 18px;
    border: 1px solid #e0d5b5;
    background: #fefef8;
    padding: 12px 15px;
    border-radius: 6px;
}

.section-title {
    font-weight: bold;
    font-size: 11px;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #c6a54a;
    margin-bottom: 10px;
    border-left: 3px solid #c6a54a;
    padding-left: 8px;
}

.row {
    margin-bottom: 6px;
    font-size: 11px;
    display: flex;
    align-items: baseline;
}

.label {
    font-weight: bold;
    width: 140px;
    display: inline-block;
    color: #555;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.value {
    flex: 1;
    color: #1a1a1a;
    font-weight: 500;
}

/* ===== PARTIES SECTION ===== */
.parties {
    margin-top: 18px;
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.party {
    flex: 1;
    border: 1px solid #e0d5b5;
    background: linear-gradient(135deg, #fefef8 0%, #faf8f0 100%);
    padding: 12px;
    text-align: center;
    border-radius: 8px;
    position: relative;
}

.party:first-child::after {
    content: "→";
    position: absolute;
    right: -12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 18px;
    color: #c6a54a;
    font-weight: bold;
}

.party-title {
    font-weight: bold;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 2px;
    color: #c6a54a;
    margin-bottom: 8px;
}

.party-name {
    font-size: 13px;
    font-weight: bold;
    color: #0f2f57;
    margin-bottom: 5px;
}

.party-detail {
    font-size: 9px;
    color: #666;
    line-height: 1.4;
}

/* ===== TRANSFER DETAILS BAR ===== */
.transfer-bar {
    margin-top: 18px;
    background: #0f2f57;
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    display: flex;
    justify-content: space-around;
    text-align: center;
}

.bar-item {
    flex: 1;
}

.bar-label {
    font-size: 7px;
    text-transform: uppercase;
    letter-spacing: 1px;
    opacity: 0.8;
    margin-bottom: 3px;
}

.bar-value {
    font-size: 10px;
    font-weight: bold;
}

/* ===== SIGNATURES SECTION ===== */
.signatures {
    margin-top: 25px;
    display: flex;
    justify-content: space-between;
}

.signature-box {
    width: 45%;
    text-align: center;
}

.signature-line {
    margin-top: 35px;
    border-top: 1px solid #333;
    width: 100%;
}

.signature-label {
    font-size: 8px;
    margin-top: 5px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.signature-name {
    font-size: 9px;
    font-weight: bold;
    margin-top: 3px;
    color: #0f2f57;
}

/* ===== SEAL ===== */
.seal {
    position: absolute;
    bottom: 28mm;
    right: 22mm;
    width: 55px;
    height: 55px;
    border: 2px solid #c6a54a;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    background: #fefef8;
    opacity: 0.85;
}

.seal-icon {
    font-size: 22px;
    color: #c6a54a;
}

.seal-text {
    font-size: 5px;
    color: #8b6914;
    text-transform: uppercase;
    margin-top: 2px;
    letter-spacing: 0.5px;
}

/* ===== FOOTER ===== */
.footer {
    position: absolute;
    bottom: 10mm;
    left: 22mm;
    right: 22mm;
    text-align: center;
    font-size: 7px;
    color: #999;
    border-top: 1px solid #e5e5e5;
    padding-top: 4mm;
}

.footer-line1 {
    margin-bottom: 2px;
}

.footer-line2 {
    font-family: monospace;
    font-size: 7px;
}

/* ===== PRINT OPTIMIZATION ===== */
@media print {
    body {
        background: white;
        margin: 0;
        padding: 0;
    }
    .certificate {
        box-shadow: none;
        margin: 0;
    }
    .watermark {
        opacity: 0.03;
    }
}
</style>
</head>

<body>
<div class="certificate">

    <!-- Decorative Borders -->
    <div class="frame-outer"></div>
    <div class="frame-inner"></div>
    
    <!-- Corner Ornaments -->
    <div class="corner corner-tl"></div>
    <div class="corner corner-tr"></div>
    <div class="corner corner-bl"></div>
    <div class="corner corner-br"></div>

    <!-- Watermark -->
    <div class="watermark">
        {{ $systemShortName ?? 'HSM' }}
    </div>

    <div class="content">

        <!-- HEADER SECTION -->
        <div class="header">
            @if(isset($systemSettings) && $systemSettings->system_logo)
                <img src="{{ public_path('storage/'.$systemSettings->system_logo) }}" class="logo" alt="Logo">
            @else
                <div style="font-size: 38px;">🏛️</div>
            @endif

            <div class="system-name">
                {{ $systemName ?? 'HILLTOP ESTATE MANAGEMENT' }}
            </div>

            <div class="title">
                CERTIFICATE OF OWNERSHIP
            </div>

            <div class="subtitle">
                Official Confirmation of Property Ownership Transfer
            </div>
            <div class="decorative-line"></div>
        </div>

        <!-- MAIN STATEMENT -->
        <div class="statement">
            This is to formally certify that
            <div class="highlight-name">
                {{ $new_owner->name ?? 'THE TRANSFEREE' }}
            </div>
            has lawfully acquired full ownership rights of the property described below,
            previously held by <strong>{{ $current_owner->name ?? 'THE TRANSFEROR' }}</strong>.
        </div>

        <!-- PROPERTY DETAILS BOX -->
        <div class="property-box">
            <div class="section-title">PROPERTY INFORMATION</div>

            <div class="row">
                <span class="label">Property Name:</span>
                <span class="value"><strong>{{ $property->property_name ?? 'N/A' }}</strong></span>
            </div>

            <div class="row">
                <span class="label">Registration Number:</span>
                <span class="value">{{ $property->registration_pattern ?? 'N/A' }}</span>
            </div>

            <div class="row">
                <span class="label">Location / Address:</span>
                <span class="value">{{ $property->street_name ?? '' }} {{ $property->zone ?? '' }}</span>
            </div>

            <div class="row">
                <span class="label">Property Type:</span>
                <span class="value">{{ $propertyTypeName ?? ($property->propertyType->name ?? 'N/A') }}</span>
            </div>

            <div class="row">
                <span class="label">Digital Address:</span>
                <span class="value">{{ $property->digital_address ?? 'N/A' }}</span>
            </div>
        </div>

        <!-- PARTIES INVOLVED -->
        <div class="parties">
            <div class="party">
                <div class="party-title">TRANSFEROR</div>
                <div class="party-name">{{ $current_owner->name ?? 'N/A' }}</div>
                <div class="party-detail">{{ $current_owner->email ?? 'N/A' }}</div>
                <div class="party-detail">{{ $current_owner->phone ?? 'N/A' }}</div>
            </div>

            <div class="party">
                <div class="party-title">TRANSFEREE</div>
                <div class="party-name">{{ $new_owner->name ?? 'N/A' }}</div>
                <div class="party-detail">{{ $new_owner->email ?? 'N/A' }}</div>
                <div class="party-detail">{{ $new_owner->phone ?? 'N/A' }}</div>
            </div>
        </div>

        <!-- TRANSFER DETAILS BAR -->
        <div class="transfer-bar">
            <div class="bar-item">
                <div class="bar-label">TRANSFER REFERENCE</div>
                <div class="bar-value">{{ $transfer->document_reference ?? 'N/A' }}</div>
            </div>
            <div class="bar-item">
                <div class="bar-label">COMPLETION DATE</div>
                <div class="bar-value">{{ $transfer->completed_at ? $transfer->completed_at->format('d M Y') : now()->format('d M Y') }}</div>
            </div>
            <div class="bar-item">
                <div class="bar-label">CERTIFICATE NO.</div>
                <div class="bar-value">{{ $certificate_number }}</div>
            </div>
        </div>

        <!-- SIGNATURES SECTION -->
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">AUTHORIZED SIGNATURE</div>
                <div class="signature-name">For: {{ $systemName ?? 'Hilltop Estate' }}</div>
            </div>

            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">OFFICIAL STAMP & DATE</div>
                <div class="signature-name">{{ $issue_date ?? now()->format('F j, Y') }}</div>
            </div>
        </div>

    </div>

    <!-- OFFICIAL SEAL -->
    <div class="seal">
        <div class="seal-icon">⚖️</div>
        <div class="seal-text">OFFICIAL SEAL</div>
    </div>

    <!-- FOOTER -->
    <div class="footer">
        <div class="footer-line1">This certificate is electronically generated and legally valid without physical alteration.</div>
        <div class="footer-line2">Certificate ID: {{ $certificate_number }} | Issued: {{ $issue_date ?? now()->format('F j, Y') }}</div>
    </div>

</div>
</body>
</html>