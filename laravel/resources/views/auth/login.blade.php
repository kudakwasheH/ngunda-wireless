<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ \App\Support\Settings::get('business.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/nws.css') }}?v=2">
</head>
<body class="bg-nws d-flex align-items-center min-vh-100">
<div class="container" style="max-width: 420px">
    <div class="text-center text-white mb-4">
        <img src="{{ asset('images/logo-square.png') }}" alt="Ngunda Wireless Logo" width="110" height="110" class="rounded-4 mb-3 shadow">
        <h1 class="h3 fw-bold">{{ \App\Support\Settings::get('business.name') }}</h1>
        <p class="text-white-50">Administration</p>
    </div>
    <div class="card"><div class="card-body p-4">
        <form method="post" action="{{ url('/login') }}" autocomplete="off">@csrf
            <div class="mb-3"><label class="form-label">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autofocus>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="mb-3"><label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required></div>
            <button class="btn btn-primary w-100">Sign in</button>
        </form>
    </div></div>
</div>
</body>
</html>