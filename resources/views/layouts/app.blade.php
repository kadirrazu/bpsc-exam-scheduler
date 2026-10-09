<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title',__('Dashboard')) | BPSC Exam Scheduler</title>
@include('shared.favicon')
@vite(['resources/css/app.css','resources/js/app.js'])@stack('styles')
</head>
<body><div class="page">
<header class="scheduler-header d-print-none">
    <div class="container-xl scheduler-masthead">
        <a href="{{ route('dashboard') }}" class="scheduler-product text-decoration-none"><span class="brand-emblem"><i class="bi bi-calendar2-check" aria-hidden="true"></i></span><span class="product-name">BPSC Exam Scheduler</span></a>
        <div class="scheduler-commission" lang="en"><strong>Bangladesh Public Service Commission</strong> (BPSC)</div>
    </div>
    <nav class="navbar navbar-expand-md scheduler-navigation" aria-label="{{ __('Toggle navigation') }}">
        <div class="container-xl">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu" aria-controls="navbar-menu" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}"><span class="navbar-toggler-icon"></span></button>
            <div class="navbar-nav flex-row order-md-last align-items-center gap-2 scheduler-account">
                @include('shared.language-switch')
                <div class="nav-item dropdown">
                    <button type="button" class="nav-link dropdown-toggle border-0 bg-transparent" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('User menu') }}"><span class="avatar avatar-sm">{{ mb_substr(auth()->user()->name,0,1) }}</span><span class="d-none d-lg-block ps-2">{{ auth()->user()->name }}</span></button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <div class="px-3 py-2"><strong>{{ auth()->user()->name }}</strong><div class="small text-secondary">{{ auth()->user()->designation?->name ?? __('Not assigned') }}</div><div class="small text-secondary">{{ auth()->user()->unit ?? __('Not assigned') }}</div></div><div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="{{ route('staff-profile.edit') }}"><i class="bi bi-person-circle" aria-hidden="true"></i>{{ __('My Profile') }}</a>
                        <a class="dropdown-item" href="{{ route('staff-profile.password') }}"><i class="bi bi-key" aria-hidden="true"></i>{{ __('Change Password') }}</a>
                        <form action="{{ route('logout') }}" method="post">@csrf<button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>{{ __('Sign out') }}</button></form>
                    </div>
                </div>
            </div>
            <div class="collapse navbar-collapse" id="navbar-menu"><ul class="navbar-nav gap-md-2">
                <li class="nav-item {{ request()->routeIs('dashboard') ? 'active':'' }}"><a class="nav-link" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif><i class="bi bi-grid-1x2" aria-hidden="true"></i>{{ __('Dashboard') }}</a></li>
                <li class="nav-item dropdown {{ request()->routeIs('schedules.*') ? 'active':'' }}">
                    <button class="nav-link dropdown-toggle border-0 bg-transparent" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-calendar2-week" aria-hidden="true"></i>{{ __('Exam Management') }}</button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="{{ route('schedules.index') }}"><i class="bi bi-calendar3" aria-hidden="true"></i>{{ __('Schedules') }}</a>
                        @can('edit-schedules')<a class="dropdown-item" href="{{ route('schedules.create') }}"><i class="bi bi-plus-circle" aria-hidden="true"></i>{{ __('Add Schedule') }}</a>@endcan
                    </div>
                </li>
                @can('manage-system')
                <li class="nav-item dropdown {{ request()->routeIs('users.*','designations.*') ? 'active':'' }}">
                    <button class="nav-link dropdown-toggle border-0 bg-transparent" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-people" aria-hidden="true"></i>{{ __('User Management') }}</button>
                    <div class="dropdown-menu"><a class="dropdown-item" href="{{ route('users.index') }}"><i class="bi bi-people" aria-hidden="true"></i>{{ __('Users') }}</a><a class="dropdown-item" href="{{ route('users.create') }}"><i class="bi bi-person-plus" aria-hidden="true"></i>{{ __('Add User') }}</a><div class="dropdown-divider"></div><a class="dropdown-item" href="{{ route('designations.index') }}"><i class="bi bi-person-badge" aria-hidden="true"></i>{{ __('Designations') }}</a></div>
                </li>
                <li class="nav-item {{ request()->routeIs('audit-logs.*') ? 'active':'' }}"><a class="nav-link" href="{{ route('audit-logs.index') }}"><i class="bi bi-shield-check" aria-hidden="true"></i>{{ __('Audit Logs') }}</a></li>
                @endcan
            </ul></div>
        </div>
    </nav>
</header>
<div class="page-wrapper"><main class="page-body"><div class="container-xl">
@if(session('success'))<div class="alert alert-success" role="alert">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')</div></main>
@include('shared.footer')
</div></div>@stack('scripts')</body></html>
