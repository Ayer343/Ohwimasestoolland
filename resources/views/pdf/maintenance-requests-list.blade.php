{{-- resources/views/pdf/maintenance-requests-list.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Maintenance Requests — {{ $unit->unit_number }}</title>
    <style>
        @page { margin: 35px; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #2c3e50; }

        .doc-header { border-bottom: 3px solid #2c3e50; padding-bottom: 10px; margin-bottom: 15px; }
        .doc-header h1 { font-size: 16px; margin: 0 0 3px; }
        .doc-header h2 { font-size: 11px; font-weight: normal; color: #54637a; margin: 0; }
        .doc-meta { font-size: 9px; color: #7f8c8d; margin-top: 5px; }

        table.list { width: 100%; border-collapse: collapse; }
        table.list th, table.list td {
            border: 1px solid #dfe4ea; padding: 5px 7px; font-size: 9.5px; text-align: left; vertical-align: top;
        }
        table.list th { background: #f6f8fa; font-weight: bold; }

        .badge { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 8.5px; font-weight: bold; text-transform: uppercase; }
        .badge-urgent  { background: #fdecea; color: #c0392b; }
        .badge-high    { background: #fff3cd; color: #856404; }
        .badge-medium  { background: #cce5ff; color: #004085; }
        .badge-low     { background: #e2e3e5; color: #383d41; }

        .footer { position: fixed; bottom: -25px; left: 0; right: 0; text-align: center;
                  font-size: 8px; color: #95a5a6; border-top: 1px solid #dfe4ea; padding-top: 4px; }
    </style>
</head>
<body>
    <div class="doc-header">
        <h1>Maintenance Requests</h1>
        <h2>{{ $unit->property->property_name }} — Unit {{ $unit->unit_number }}</h2>
        <div class="doc-meta">
            <strong>Scope:</strong> {{ ucfirst(str_replace('-', ' ', $scope)) }} ·
            <strong>Total:</strong> {{ $requests->count() }} ·
            <strong>Generated:</strong> {{ $generated_date }}
        </div>
    </div>

    @if($requests->isEmpty())
        <p style="text-align:center; padding: 40px; color:#7f8c8d;"><em>No maintenance requests match the current filters.</em></p>
    @else
        <table class="list">
            <thead>
                <tr>
                    <th style="width:12%">Reference</th>
                    <th style="width:26%">Title</th>
                    <th style="width:12%">Category</th>
                    <th style="width:10%">Priority</th>
                    <th style="width:12%">Status</th>
                    <th style="width:14%">Reported</th>
                    <th style="width:14%">Updated</th>
                </tr>
            </thead>
            <tbody>
                @foreach($requests as $req)
                <tr>
                    <td>{{ $req->reference_id }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($req->title, 60) }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $req->category)) }}</td>
                    <td><span class="badge badge-{{ $req->priority }}">{{ $req->priority }}</span></td>
                    <td><span class="badge badge-{{ str_replace('-', '_', $req->status) }}">{{ str_replace('_', ' ', $req->status) }}</span></td>
                    <td>{{ optional($req->created_at)->format('M j, Y') }}</td>
                    <td>{{ optional($req->updated_at)->format('M j, Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Generated {{ $generated_date }} · Governed by the {{ $governing_law }}
    </div>
</body>
</html>