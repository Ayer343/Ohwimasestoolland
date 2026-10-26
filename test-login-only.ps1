$BaseUrl  = "http://127.0.0.1:8000"
$Email    = "stevenayer1@hotmail.com"
$Password = "TestPassword123!"

Write-Host ""
Write-Host "[1] GET /login - seed session + CSRF" -ForegroundColor Cyan

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$page = Invoke-WebRequest -Uri "$BaseUrl/login" -WebSession $session -UseBasicParsing

$csrfMatch = [regex]::Match($page.Content, 'name="csrf-token" content="([^"]+)"')
$csrf = $csrfMatch.Groups[1].Value

if (-not $csrf) {
    Write-Host "    FAILED: no CSRF token found in login page" -ForegroundColor Red
    exit 1
}

Write-Host "    CSRF: $($csrf.Substring(0,16))..." -ForegroundColor Green

Write-Host ""
Write-Host "[2] POST /login" -ForegroundColor Cyan

$body = @{
    login    = $Email
    password = $Password
    _token   = $csrf
} | ConvertTo-Json -Compress

$headers = @{
    "Accept"       = "application/json"
    "X-CSRF-TOKEN" = $csrf
}

# Let PowerShell follow redirects - we'll inspect the final URL.
$finalUrl = $null
$statusCode = $null
$responseContent = $null

try {
    $resp = Invoke-WebRequest -Method POST `
        -Uri "$BaseUrl/login" `
        -ContentType "application/json" `
        -Headers $headers `
        -Body $body `
        -WebSession $session `
        -ErrorAction Stop `
        -UseBasicParsing

    $statusCode = $resp.StatusCode
    $finalUrl = $resp.BaseResponse.ResponseUri.AbsoluteUri
    $responseContent = $resp.Content
}
catch {
    # Non-2xx responses land here
    $ex = $_.Exception

    if ($ex.Response) {
        $statusCode = [int]$ex.Response.StatusCode
        $finalUrl = $ex.Response.ResponseUri.AbsoluteUri

        try {
            $reader = New-Object System.IO.StreamReader($ex.Response.GetResponseStream())
            $responseContent = $reader.ReadToEnd()
        } catch { }
    } else {
        Write-Host "    Unexpected error: $($ex.Message)" -ForegroundColor Red
        exit 1
    }
}

Write-Host "    HTTP Status: $statusCode"
Write-Host "    Final URL:   $finalUrl"

# Interpret the result
Write-Host ""
Write-Host "[3] Interpretation" -ForegroundColor Cyan

if ($finalUrl -match '/dashboard') {
    Write-Host "    ✓ LOGIN SUCCESSFUL - landed on /dashboard" -ForegroundColor Green
    Write-Host "    → This user has 2FA disabled" -ForegroundColor Yellow
}
elseif ($finalUrl -match '/2fa/verify') {
    Write-Host "    ✓ LOGIN SUCCESSFUL - 2FA required" -ForegroundColor Green
    Write-Host "    → Redirected to /2fa/verify as expected" -ForegroundColor Yellow
}
elseif ($finalUrl -match '/billing') {
    Write-Host "    ✓ LOGIN SUCCESSFUL - billing redirect" -ForegroundColor Green
    Write-Host "    → Super admin with overdue/pending billing" -ForegroundColor Yellow
}
elseif ($finalUrl -match '/login') {
    Write-Host "    ✗ LOGIN FAILED" -ForegroundColor Red
    Write-Host "    → Response body:" -ForegroundColor Yellow
    Write-Host "    $responseContent" -ForegroundColor DarkGray
}
else {
    Write-Host "    ? Unexpected destination: $finalUrl" -ForegroundColor Yellow
}

Write-Host ""
