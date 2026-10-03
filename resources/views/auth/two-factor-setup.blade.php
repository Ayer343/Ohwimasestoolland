@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Two-Factor Authentication Setup') }}</div>

                <div class="card-body">
                    @if (session('backup_codes'))
                        <div class="alert alert-warning">
                            <strong>Save your backup codes!</strong>
                            <p>These codes can be used to recover your account if you lose access to your authenticator app.</p>
                            <div class="bg-light p-3 rounded">
                                @foreach (session('backup_codes') as $code)
                                    <code>{{ $code }}</code><br>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <p>Scan the QR code below with your authenticator app (Google Authenticator, Authy, etc.)</p>
                    
                    <div class="text-center my-4">
                        {!! $qr_code_svg !!}
                    </div>
                    
                    <p>Or enter this code manually: <strong>{{ $secret }}</strong></p>
                    
                    <form method="POST" action="{{ route('2fa.verify-setup') }}">
                        @csrf
                        <div class="form-group">
                            <label for="code">Enter the 6-digit code from your authenticator app</label>
                            <input type="text" name="code" id="code" class="form-control" required autocomplete="off">
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">Verify and Enable 2FA</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection