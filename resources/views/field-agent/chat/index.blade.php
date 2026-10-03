@extends('layouts.field')
@section('title', 'Chat Assistant')
@section('content')
    @include('chat._widget', [
        'userRoleName'    => $userRoleName,
        'userRoleId'      => $userRoleId,
        'recentMessages'  => $recentMessages,
        'quickHelpTopics' => $quickHelpTopics,
    ])
@endsection