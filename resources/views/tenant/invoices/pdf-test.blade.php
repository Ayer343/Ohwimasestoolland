<!DOCTYPE html>
<html>
<head>
    <title>Test PDF</title>
</head>
<body>
    <h1>PDF Test</h1>
    <p>This is a test PDF generated at {{ now() }}</p>
    <p>Invoice ID: {{ $invoice->id ?? 'N/A' }}</p>
    <p>Invoice Number: {{ $invoice->invoice_number ?? 'N/A' }}</p>
</body>
</html>