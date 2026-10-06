<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover"
    >

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        @yield('title', __('Authentication')) | {{ __('BPSC Exam Scheduler') }}
    </title>

    @include('shared.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>

<body class="d-flex flex-column bg-white">
    <div class="page page-center">
        <div class="container container-tight py-4">
            <div class="auth-brand product-name mb-2"><i class="bi bi-calendar2-check" aria-hidden="true"></i> 
                {{ __('BPSC Exam Scheduler') }}
            </div>

            <div class="auth-subtitle mb-4">
                {{ __('Bangladesh Public Service Commission') }}
            </div>

            <div class="d-flex justify-content-center mb-3">@include('shared.language-switch')</div>
            @yield('content')
        </div>
    </div>

    @include('shared.footer')
    @stack('scripts')
</body>
</html>