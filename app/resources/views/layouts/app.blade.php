<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2563eb">
    <title>@yield('title', 'AntaMart') · AntaMart</title>
    <meta name="description" content="Thoughtful finds for everyday living. Discover AntaMart.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>
    <header class="site-header">
        <div class="header-inner">
            <a class="wordmark" href="{{ route('storefront.home') }}" aria-label="AntaMart home">
                <span class="wordmark-mark" aria-hidden="true">A</span>
                <span>AntaMart</span>
            </a>
            <form class="header-search" action="{{ route('storefront.home') }}" method="get" role="search">
                <label class="visually-hidden" for="site-search">Cari produk</label>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" stroke-width="1.6"/><path d="m16 16 4.2 4.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                <input id="site-search" type="search" name="q" value="{{ request('q') }}" placeholder="Cari barang kebutuhanmu di sini...">
            </form>
            <div class="header-actions">
                <a class="bag-link" href="{{ route('storefront.cart.index') }}" aria-label="Keranjang, {{ array_sum(session('cart', [])) }} item">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none">
                        <path d="M5 8.5h14l1 12H4l1-12Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                        <path d="M9 9V6a3 3 0 0 1 6 0v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span class="bag-count">{{ array_sum(session('cart', [])) }}</span>
                </a>
            </div>
        </div>
    </header>
    @if (session('status'))
        <x-alert tone="success" class="flash-message">{{ session('status') }}</x-alert>
    @endif
    @if ($errors->any())
        <x-alert tone="error" class="flash-message">
            <p>Please review the following:</p>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </x-alert>
    @endif
    <main id="main-content">@yield('content')</main>
    <footer class="site-footer">
        <div class="footer-inner">
            <a class="wordmark footer-wordmark" href="{{ route('storefront.home') }}">
                <span class="wordmark-mark" aria-hidden="true">A</span>
                <span>AntaMart</span>
            </a>
            <p>Belanja kebutuhan sehari-hari.</p>
            <span class="footer-copyright">© {{ now()->year }} AntaMart</span>
        </div>
    </footer>
</body>
</html>
