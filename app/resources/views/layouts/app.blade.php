<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f6f5f0">
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
                <span>anta<span class="wordmark-light">mart</span></span>
            </a>
            <nav class="main-nav" aria-label="Main navigation">
                <a href="{{ route('storefront.home') }}#collection">Collection</a>
                <a href="{{ route('storefront.home') }}#our-story">Our story</a>
            </nav>
            <div class="header-actions">
                <a class="bag-link" href="{{ route('storefront.home') }}#collection" aria-label="Shopping bag, 0 items">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none">
                        <path d="M5 8.5h14l1 12H4l1-12Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                        <path d="M9 9V6a3 3 0 0 1 6 0v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span>Bag <span class="bag-count">0</span></span>
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
                <span>anta<span class="wordmark-light">mart</span></span>
            </a>
            <p>Good things, chosen with care.</p>
            <span class="footer-copyright">© {{ now()->year }} AntaMart</span>
        </div>
    </footer>
</body>
</html>
