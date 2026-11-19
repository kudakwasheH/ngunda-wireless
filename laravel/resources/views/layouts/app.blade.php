<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ \App\Support\Settings::get('business.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/nws.css') }}?v=2">
</head>
<body>
@php($u = auth()->user())
<nav class="navbar navbar-dark bg-nws d-lg-none px-3">
    <button class="btn btn-outline-light btn-sm" data-bs-toggle="offcanvas" data-bs-target="#sidebar"><i class="bi bi-list"></i></button>
    <a class="navbar-brand mb-0 d-flex align-items-center gap-2" href="{{ route('dashboard') }}">
        <img src="{{ asset('images/logo-icon.png') }}" alt="Logo" width="28" height="28" class="rounded">
        <span class="h6 mb-0">{{ \App\Support\Settings::get('business.name') }}</span>
    </a>
</nav>
<div class="d-flex">
    <div class="offcanvas-lg offcanvas-start bg-nws text-white sidebar" tabindex="-1" id="sidebar">
        <div class="p-3 sidebar-brand d-none d-lg-flex align-items-center gap-2">
            <img src="{{ asset('images/logo-icon.png') }}" alt="Logo" width="36" height="36" class="rounded">
            <div>
                <div class="h6 mb-0 fw-bold text-white">{{ \App\Support\Settings::get('business.name') }}</div>
                <div class="sidebar-subtitle">Hotspot management</div>
            </div>
        </div>
        <ul class="nav flex-column p-2 mt-1">
            @if($u->hasPermission('dashboard.view'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            @endif
            @if($u->hasPermission('packages.view') || $u->hasPermission('packages.manage'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('packages.*') ? 'active' : '' }}" href="{{ route('packages.index') }}"><i class="bi bi-box-seam"></i> Packages</a></li>
            @endif
            @if($u->hasPermission('tokens.view'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('tokens.*') ? 'active' : '' }}" href="{{ route('tokens.index') }}"><i class="bi bi-ticket-perforated"></i> Tokens</a></li>
            @endif
            @if($u->hasPermission('users.manage'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}"><i class="bi bi-people"></i> Users</a></li>
            @endif
            @if($u->hasPermission('settings.manage') || $u->hasPermission('network.configure'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.edit') }}"><i class="bi bi-gear"></i> Settings</a></li>
            @endif
            @if($u->hasPermission('audit.view'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('audit.*') ? 'active' : '' }}" href="{{ route('audit.index') }}"><i class="bi bi-journal-text"></i> Audit log</a></li>
            @endif
        </ul>
        <div class="mt-auto p-3 sidebar-footer small">
            <div class="text-truncate fw-semibold text-white">{{ $u->name }}</div>
            <div class="sidebar-subtitle mb-2">{{ $u->role?->name }}</div>
            <form method="post" action="{{ route('logout') }}">@csrf
                <button class="btn btn-outline-light btn-sm w-100"><i class="bi bi-box-arrow-right"></i> Sign out</button>
            </form>
        </div>
    </div>
    <main class="flex-grow-1 p-3 p-lg-4 content">
        @if(session('status'))<div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
</div>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
@stack('scripts')
</body>
</html>
