{{--
 | resources/views/auth/two-factor-setup.blade.php
 |
 | Two-factor setup wizard. Handles TWO flows:
 |
 |   1. VOLUNTARY — authenticated user enabling 2FA from their profile
 |      or settings page.
 |        • $mandatory = false
 |        • form POSTs to  route('2fa.enable')
 |        • Cancel link is hidden (nothing to cancel to)
 |
 |   2. MANDATORY — user was redirected here by LoginController because
 |      DeveloperSetting::isTwoFactorRequiredFor($user) returned true.
 |        • $mandatory = true
 |        • form POSTs to  route('2fa.setup.required.enable')
 |        • policy banner is shown
 |        • Cancel button posts to route('2fa.setup.required.cancel')
 |
 | The controller passes:
 |   $user, $qr_code_url, $qr_code_svg, $secret, $method,
 |   $backup_codes_count, $mandatory (optional, defaults false),
 |   $policy (optional, only set in the mandatory flow)
 |
 | The `2fa.verify-setup` endpoint is an AJAX validator only — it does
 | NOT enable 2FA. This form therefore posts to the correct enable
 | route based on the $mandatory flag.
--}}
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">

            {{-- ────────────────────────────────────────────────────
                 Backup codes flash (shown once, immediately after
                 the enable step completes on another request).
                 ──────────────────────────────────────────────────── --}}
            @if (session('backup_codes') && is_array(session('backup_codes')) && count(session('backup_codes')) > 0)
                <div class="alert alert-warning">
                    <strong>Save your backup codes!</strong>
                    <p class="mb-2">
                        These codes can be used to recover your account if you lose access
                        to your authenticator app. Each code can only be used once.
                    </p>
                    <div class="bg-light p-3 rounded">
                        @foreach (session('backup_codes') as $code)
                            <code>{{ $code }}</code><br>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>
                        {{ __('Two-Factor Authentication Setup') }}
                        @if(!empty($mandatory))
                            <span class="badge badge-warning ml-2" style="background: #f0ad4e; color: #fff;">
                                Required
                            </span>
                        @endif
                    </span>

                    {{-- Cancel button only exists in the mandatory flow. --}}
                    @if(!empty($mandatory))
                        <form method="POST"
                              action="{{ route('2fa.setup.required.cancel') }}"
                              class="m-0"
                              onsubmit="return confirm('Cancel two-factor setup? You will be signed out and must complete setup to use this account.');">
                            @csrf
                            <button type="submit"
                                    class="btn btn-sm btn-outline-secondary"
                                    style="background: transparent; border: 1px solid #ccc; color: #666;">
                                Cancel
                            </button>
                        </form>
                    @endif
                </div>

                <div class="card-body">

                    {{-- ────────────────────────────────────────────────
                         Mandatory-flow policy banner.
                         ──────────────────────────────────────────────── --}}
                    @if(!empty($mandatory))
                        <div class="alert mb-4"
                             style="background-color: #fff8e1; border: 1px solid #ffe082; color: #6d4c00;">
                            <p class="mb-1 font-weight-bold">
                                <i class="fas fa-shield-alt mr-1"></i>
                                Two-factor authentication is required
                            </p>
                            <p class="mb-0 small">
                                @if(!empty($policy))
                                    Your account has a two-factor policy of
                                    <strong>{{ ucfirst($policy) }}</strong>.
                                @endif
                                You must set up 2FA before you can use the application.
                                This protects both your account and the system.
                            </p>
                        </div>
                    @endif

                    {{-- ────────────────────────────────────────────────
                         Validation errors.
                         ──────────────────────────────────────────────── --}}
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- ────────────────────────────────────────────────
                         Instructions + QR code.
                         ──────────────────────────────────────────────── --}}
                    <p>
                        Scan the QR code below with your authenticator app
                        (Google Authenticator, Authy, Microsoft Authenticator, etc.).
                    </p>

                    <div class="text-center my-4">
                        {{-- Prefer inline SVG: offline, no external request,
                             and works behind firewalls that block Google's
                             chart API. Fall back to the QR image URL if
                             the service didn't return SVG. --}}
                        @if (!empty($qr_code_svg ?? null))
                            <div class="d-inline-block p-3"
                                 style="background: #fff; border-radius: 8px;">
                                {!! $qr_code_svg !!}
                            </div>
                        @elseif (!empty($qr_code_url ?? null))
                            <img src="{{ $qr_code_url }}"
                                 alt="QR Code"
                                 width="220"
                                 height="220"
                                 style="background: #fff; border-radius: 8px; padding: 8px;">
                        @else
                            <div class="alert alert-warning mb-0">
                                QR code unavailable. Use the manual code below.
                            </div>
                        @endif
                    </div>

                    <p>
                        Or enter this code manually:
                        <strong style="font-family: monospace; letter-spacing: 0.15em; font-size: 1.05em;">
                            {{ $secret }}
                        </strong>
                    </p>

                    {{-- ────────────────────────────────────────────────
                         Enable form.
                         Routes differ by flow:
                           • voluntary  → route('2fa.enable')
                           • mandatory  → route('2fa.setup.required.enable')
                         ──────────────────────────────────────────────── --}}
                    @php
                        $enableRoute = !empty($mandatory)
                            ? route('2fa.setup.required.enable')
                            : route('2fa.enable');
                    @endphp

                    <form method="POST" action="{{ $enableRoute }}" id="twoFactorEnableForm">
                        @csrf

                        <div class="form-group">
                            <label for="verification_code">
                                Enter the 6-digit code from your authenticator app
                            </label>
                            <input
                                type="text"
                                name="verification_code"
                                id="verification_code"
                                class="form-control @error('verification_code') is-invalid @enderror"
                                required
                                autocomplete="off"
                                inputmode="numeric"
                                pattern="[0-9]{6}"
                                maxlength="6"
                                autofocus
                                placeholder="123456"
                            >
                            @error('verification_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        @if (config('auth.two_factor.allow_method_selection', false))
                            <div class="form-group mt-3">
                                <label for="method">Preferred 2FA method</label>
                                <select name="method" id="method" class="form-control">
                                    <option value="email"
                                            @selected(old('method', $method ?? 'email') === 'email')>
                                        Email code
                                    </option>
                                    <option value="sms"
                                            @selected(old('method', $method ?? 'email') === 'sms')>
                                        SMS code
                                    </option>
                                </select>
                                <small class="text-muted">
                                    Authenticator apps (Google Authenticator, Authy) are the default.
                                    Email/SMS are fallbacks.
                                </small>
                            </div>
                        @endif

                        <button type="submit" class="btn btn-primary mt-3">
                            Verify and Enable 2FA
                        </button>
                    </form>

                    <hr class="mt-4">

                    <div class="text-muted small">
                        <p class="mb-1">
                            <strong>Setup in progress:</strong>
                            Your account is not protected until you submit a valid code above.
                        </p>
                        <p class="mb-0">
                            If you refresh this page, the same QR code will be shown.
                            The secret is only saved to your account once you verify it.
                        </p>

                        @if(!empty($mandatory))
                            <p class="mb-0 mt-2" style="color: #b94a48;">
                                <i class="fas fa-info-circle mr-1"></i>
                                You cannot access the rest of the application
                                until setup is complete.
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ────────────────────────────────────────────────
                 Optional: live-validation hint.
                 The controller exposes a pure AJAX validator at
                 2fa.setup.required.verify (mandatory) and 2fa.verify-setup
                 (voluntary). We don't wire it up here by default —
                 the form works fine without it and every extra AJAX
                 request is a chance for CSRF-related flakiness. If you
                 want live feedback, uncomment the script below.
                 ──────────────────────────────────────────────── --}}
            {{--
            @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const input = document.getElementById('verification_code');
                    const form  = document.getElementById('twoFactorEnableForm');
                    const validateUrl = @json(
                        !empty($mandatory)
                            ? route('2fa.setup.required.verify')
                            : route('2fa.verify-setup')
                    );
                    const csrf = '{{ csrf_token() }}';

                    let lastValue = '';
                    let timer = null;

                    input.addEventListener('input', function () {
                        const value = input.value.replace(/\D/g, '').slice(0, 6);
                        input.value = value;

                        if (value.length !== 6 || value === lastValue) {
                            return;
                        }
                        lastValue = value;

                        clearTimeout(timer);
                        timer = setTimeout(async () => {
                            try {
                                const response = await fetch(validateUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': csrf,
                                        'Accept': 'application/json',
                                    },
                                    body: JSON.stringify({ code: value }),
                                });
                                const data = await response.json();
                                if (data.valid === true) {
                                    input.classList.remove('is-invalid');
                                    input.classList.add('is-valid');
                                } else if (data.valid === false) {
                                    input.classList.remove('is-valid');
                                    input.classList.add('is-invalid');
                                }
                            } catch (e) {
                                // Silent — validation hint is not load-bearing.
                            }
                        }, 250);
                    });
                });
            </script>
            @endpush
            --}}
        </div>
    </div>
</div>
@endsection