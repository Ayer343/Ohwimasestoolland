{{-- resources/views/pdf/maintenance-request.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Maintenance Request - {{ $request->reference_id }}</title>
    <style>
        @page { margin: 40px; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11px; color: #2c3e50; line-height: 1.55; }

        .doc-header { border-bottom: 3px solid #2c3e50; padding-bottom: 12px; margin-bottom: 20px; }
        .doc-header h1 { font-size: 20px; margin: 0 0 4px; }
        .doc-header h2 { font-size: 12px; font-weight: normal; color: #54637a; margin: 0; }
        .doc-meta { font-size: 10px; color: #7f8c8d; margin-top: 6px; }

        .section { margin-bottom: 18px; }
        .section-title { font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px;
                         border-bottom: 1px solid #cfd6df; padding-bottom: 4px; margin-bottom: 10px; }

        .info-table { width: 100%; border-collapse: collapse; }
        .info-table th, .info-table td { border: 1px solid #dfe4ea; padding: 7px 9px; text-align: left;
                                          font-size: 10.5px; vertical-align: top; }
        .info-table th { background: #f6f8fa; width: 32%; font-weight: bold; }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px;
                 font-weight: bold; text-transform: uppercase; }
        .badge-urgent   { background: #fdecea; color: #c0392b; }
        .badge-high     { background: #fff3cd; color: #856404; }
        .badge-medium   { background: #cce5ff; color: #004085; }
        .badge-low      { background: #e2e3e5; color: #383d41; }

        .badge-pending      { background: #fff3cd; color: #856404; }
        .badge-in_progress  { background: #cce5ff; color: #004085; }
        .badge-completed    { background: #d4edda; color: #155724; }
        .badge-cancelled    { background: #e2e3e5; color: #383d41; }

        .description-box { border-left: 4px solid #3498db; background: #f6f8fa; padding: 10px 12px;
                           font-size: 10.5px; white-space: pre-line; }

        .photos-grid { width: 100%; border-collapse: separate; border-spacing: 8px; }
        .photos-grid td { width: 33%; vertical-align: top; }
        .photo-thumb { max-width: 100%; max-height: 150px; border: 1px solid #dfe4ea; }

        .footer { position: fixed; bottom: -30px; left: 0; right: 0; text-align: center;
                  font-size: 8.5px; color: #95a5a6; border-top: 1px solid #dfe4ea; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="doc-header">
        <h1>Maintenance Request</h1>
        <h2>{{ $unit->property->property_name }} — Unit {{ $unit->unit_number }}</h2>
        <div class="doc-meta">
            <strong>Reference:</strong> {{ $request->reference_id }} ·
            <strong>Generated:</strong> {{ $generated_date }} ·
            <strong>Currency:</strong> {{ $currency_symbol }}
        </div>
    </div>

    <div class="section">
        <div class="section-title">1. Request Information</div>
        <table class="info-table">
            <tr><th>Reference</th><td><strong>{{ $request->reference_id }}</strong></td></tr>
            <tr><th>Title</th><td>{{ $request->title }}</td></tr>
            <tr><th>Category</th><td>{{ ucfirst(str_replace('_', ' ', $request->category)) }}</td></tr>
            <tr>
                <th>Priority</th>
                <td><span class="badge badge-{{ $request->priority }}">{{ $request->priority }}</span></td>
            </tr>
            <tr>
                <th>Status</th>
                <td><span class="badge badge-{{ str_replace('-', '_', $request->status) }}">{{ str_replace('_', ' ', $request->status) }}</span></td>
            </tr>
            <tr><th>Reported On</th><td>{{ optional($request->created_at)->format('F j, Y H:i') }}</td></tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">2. Description</div>
        <div class="description-box">{{ $request->description }}</div>
    </div>

    <div class="section">
        <div class="section-title">3. Parties</div>
        <table class="info-table">
            <tr>
                <th>Tenant</th>
                <td>
                    <strong>{{ $request->tenant->name ?? '—' }}</strong><br>
                    @if($request->tenant?->email) Email: {{ $request->tenant->email }}<br> @endif
                    @if($request->tenant?->phone) Phone: {{ $request->tenant->phone }} @endif
                </td>
            </tr>
            <tr>
                <th>Landlord</th>
                <td>
                    <strong>{{ $request->landlord->name ?? '—' }}</strong><br>
                    @if($request->landlord?->email) Email: {{ $request->landlord->email }}<br> @endif
                    @if($request->landlord?->phone) Phone: {{ $request->landlord->phone }} @endif
                </td>
            </tr>
        </table>
    </div>

    @if(is_array($request->images) && count($request->images) > 0)
    <div class="section">
        <div class="section-title">4. Attached Photos</div>
        <table class="photos-grid">
            <tr>
                @foreach($request->images as $i => $image)
                    @if($i > 0 && $i % 3 === 0)
                        </tr><tr>
                    @endif
                    <td style="text-align:center;">
                        @php $path = storage_path('app/public/' . $image); @endphp
                        @if(file_exists($path))
                            <img src="{{ $path }}" class="photo-thumb" alt="Photo">
                        @else
                            <em style="font-size:10px;color:#999;">[missing]</em>
                        @endif
                    </td>
                @endforeach
            </tr>
        </table>
    </div>
    @endif

    @if($request->notes)
    <div class="section">
        <div class="section-title">5. Notes</div>
        <div class="description-box">{{ $request->notes }}</div>
    </div>
    @endif

    <div class="footer">
        Request {{ $request->reference_id }} · {{ $unit->property->property_name }} — Unit {{ $unit->unit_number }} ·
        Governed by the {{ $governing_law }}
    </div>
</body>
</html>