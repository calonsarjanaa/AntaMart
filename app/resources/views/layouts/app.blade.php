<!DOCTYPE html>
<html lang="id">
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
    <a class="skip-link" href="#main-content">Lewati ke konten</a>
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
                @if (request()->routeIs('storefront.home'))
                    @if (request('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif
                    @if (request('availability'))<input type="hidden" name="availability" value="{{ request('availability') }}">@endif
                @endif
            </form>
            <div class="header-actions">
                <a class="tracking-nav-link" href="{{ route('storefront.orders.track') }}">Lacak pesanan</a>
                @if (auth('web')->user()?->isCustomer())
                    <a class="auth-nav-link account-nav-link" href="{{ route('storefront.account.index') }}">Akun</a>
                    <form class="header-logout-form" method="post" action="{{ route('storefront.account.logout') }}">
                        @csrf
                        <button class="auth-nav-link" type="submit">Keluar</button>
                    </form>
                @elseif (auth('web')->user()?->isStoreOwner())
                    <a class="auth-nav-link" href="{{ route('owner.dashboard') }}">Console</a>
                @else
                    <a class="auth-nav-link" href="{{ route('login') }}">Masuk</a>
                    <a class="auth-nav-link auth-register-link" href="{{ route('register') }}">Daftar</a>
                @endif
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
            <p>Periksa kembali informasi berikut:</p>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </x-alert>
    @endif
    <main id="main-content">@yield('content')</main>
    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-main">
                <div class="footer-col footer-col-brand">
                    <a class="footer-brand" href="{{ route('storefront.home') }}" aria-label="AntaMart">
                        <span class="footer-brand-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3.5" y="5.5" width="17" height="15" rx="3.5"/>
                                <path d="M8.5 9.5a3.5 3.5 0 0 0 7 0"/>
                            </svg>
                        </span>
                        <span class="footer-brand-name">AntaMart</span>
                    </a>
                    <p class="footer-desc">
                        Platform e-commerce terpercaya untuk memenuhi segala kebutuhan harianmu. Belanja aman, mudah, dan cepat dengan layanan dukungan pelanggan terbaik.
                    </p>
                </div>
                <div class="footer-col">
                    <h3 class="footer-heading">Layanan Pelanggan</h3>
                    <ul class="footer-links">
                        <li><a href="#">Pusat Bantuan</a></li>
                        <li><a href="#">Cara Pengembalian</a></li>
                        <li><a href="{{ route('storefront.orders.track') }}">Lacak Pesanan</a></li>
                        <li><a href="#">Syarat & Ketentuan</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h3 class="footer-heading">Jelajahi</h3>
                    <ul class="footer-links">
                        <li><a href="#">Tentang Kami</a></li>
                        <li><a href="#">Karir</a></li>
                        <li><a href="#">Blog</a></li>
                        <li><a href="#">Mitra</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p class="footer-copyright">© {{ now()->year }} AntaMart. Hak Cipta Dilindungi.</p>
                <div class="footer-socials">
                    <a href="https://facebook.com" target="_blank" rel="noopener noreferrer">Facebook</a>
                    <a href="https://instagram.com" target="_blank" rel="noopener noreferrer">Instagram</a>
                    <a href="https://twitter.com" target="_blank" rel="noopener noreferrer">Twitter</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
