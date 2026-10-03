{{-- resources/views/chat/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Chat Assistant')

@section('content')
<div class="container mx-auto px-4 py-4">
    @include('chat._widget', [
        'userRoleName'    => $userRoleName    ?? 'User',
        'userRoleId'      => $userRoleId      ?? null,
        'recentMessages'  => $recentMessages  ?? collect(),
        'quickHelpTopics' => $quickHelpTopics ?? [],
    ])
</div>
@endsection