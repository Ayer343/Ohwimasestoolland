@extends('layouts.guest')

@section('title', 'Terms and Conditions')

@section('content')
<div class="container py-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">Terms and Conditions</h1>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <div class="prose dark:prose-invert max-w-none">
                <h2>1. Acceptance of Terms</h2>
                <p>By accessing and using this Property Registration System, you accept and agree to be bound by the terms and provision of this agreement.</p>
                
                <h2>2. Use License</h2>
                <p>Permission is granted to temporarily use the system for field agent registration and property management purposes.</p>
                
                <h2>3. User Responsibilities</h2>
                <p>Users are responsible for maintaining the confidentiality of their account and password.</p>
                
                <h2>4. Privacy</h2>
                <p>Your privacy is important to us. Please review our <a href="{{ route('privacy') }}" class="text-blue-600 hover:text-blue-500">Privacy Policy</a>.</p>
                
                <h2>5. Contact Information</h2>
                <p>If you have any questions about these Terms, please contact us at support@propertyreg.com</p>
            </div>
        </div>
    </div>
</div>
@endsection