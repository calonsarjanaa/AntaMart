<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2563eb">
    <title>@yield('title', 'Console toko') · AntaMart</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="owner-body">
    <a class="skip-link" href="#main-content">Lewati ke konten</a>
    <header class="owner-site-header">
        <div class="owner-header-inner">
            <a class="wordmark" href="{{ auth('web')->user()?->isStoreOwner() ? route('owner.dashboard') : route('owner.login') }}" aria-label="Console AntaMart">
                <span class="wordmark-mark" aria-hidden="true">A</span>
                <span>AntaMart <small>Console</small></span>
            </a>
            @if (auth('web')->user()?->isStoreOwner())
                <nav class="owner-nav" aria-label="Navigasi console">
                    <a @class(['is-active' => request()->routeIs('owner.dashboard')]) href="{{ route('owner.dashboard') }}">Dashboard</a>
                    <a @class(['is-active' => request()->routeIs('owner.orders.*')]) href="{{ route('owner.orders.index') }}">Pesanan</a>
                </nav>
                <div class="owner-header-actions">
                    <span class="owner-user-name">{{ auth('web')->user()->name }}</span>
                    <form method="post" action="{{ route('owner.logout') }}">
                        @csrf
                        <button class="auth-nav-link" type="submit">Keluar</button>
                    </form>
                </div>
            @else
                <a class="owner-store-link" href="{{ route('storefront.home') }}">Kembali ke toko</a>
            @endif
        </div>
    </header>
    @if (session('status'))
        <x-alert tone="success" class="owner-flash">{{ session('status') }}</x-alert>
    @endif
    @if ($errors->any())
        <x-alert tone="error" class="owner-flash">
            <p>Periksa kembali informasi berikut:</p>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </x-alert>
    @endif
    <main id="main-content" class="owner-main">
        @hasSection('breadcrumbs')
            <nav class="owner-breadcrumbs" aria-label="Breadcrumb">@yield('breadcrumbs')</nav>
        @endif
        @yield('content')
    </main>
    <footer class="owner-footer"><a href="{{ route('storefront.home') }}">← Buka storefront</a><span>AntaMart · Console toko</span></footer>
</body>
</html>
