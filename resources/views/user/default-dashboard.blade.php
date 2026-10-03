@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Welcome, {{ Auth::user()->name }}!</div>
                <div class="card-body">
                    <p>This is your dashboard. Your user type is: <strong>{{ Auth::user()->type }}</strong></p>
                    <a href="{{ route('profile.edit') }}" class="btn btn-primary">Update Profile</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection