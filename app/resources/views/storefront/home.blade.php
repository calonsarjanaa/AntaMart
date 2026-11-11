@extends('layouts.app')

@section('title', 'Belanja kebutuhan sehari-hari')

@section('content')
    <style>
        .promo-carousel-section { width: min(var(--content-width, 1240px), calc(100% - 24px)); margin: 16px auto 0; }
        .promo-carousel { position: relative; width: 100%; aspect-ratio: 16 / 6.8; min-height: 180px; max-height: 400px; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08); background: #0284c7; }
        .carousel-track { display: flex; width: 100%; height: 100%; transition: transform 0.6s cubic-bezier(0.25, 1, 0.5, 1); }
        .carousel-slide { flex: 0 0 100%; width: 100%; min-width: 100%; height: 100%; position: relative; }
        .carousel-slide-link { display: block; width: 100%; height: 100%; }
        .carousel-slide-link img { width: 100%; height: 100%; object-fit: cover; object-position: center; display: block; user-select: none; -webkit-user-drag: none; }
        .carousel-arrow { position: absolute; top: 50%; transform: translateY(-50%); width: 38px; height: 38px; border-radius: 50%; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.6); color: #1e293b; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15); transition: background 0.2s ease, transform 0.2s ease, opacity 0.2s ease; opacity: 0; z-index: 10; }
        .promo-carousel:hover .carousel-arrow, .carousel-arrow:focus-visible { opacity: 1; }
        .carousel-arrow:hover { background: #ffffff; transform: translateY(-50%) scale(1.08); }
        .carousel-arrow-prev { left: 14px; }
        .carousel-arrow-next { right: 14px; }
        .carousel-arrow svg { width: 18px; height: 18px; }
        .carousel-indicators { position: absolute; bottom: 12px; left: 50%; transform: translateX(-50%); display: flex; align-items: center; gap: 8px; padding: 5px 10px; background: rgba(15, 23, 42, 0.35); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); border-radius: 9999px; z-index: 10; }
        .carousel-dot { width: 8px; height: 8px; border-radius: 50%; background: rgba(255, 255, 255, 0.55); border: 0; padding: 0; cursor: pointer; transition: all 0.25s ease; }
        .carousel-dot.is-active { width: 22px; border-radius: 9999px; background: #ffffff; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25); }
        .carousel-dot:hover:not(.is-active) { background: rgba(255, 255, 255, 0.85); }
        @media (max-width: 768px) {
            .promo-carousel { aspect-ratio: 16 / 7.5; border-radius: 8px; }
            .carousel-arrow { opacity: 0.9; width: 32px; height: 32px; }
            .carousel-arrow-prev { left: 8px; }
            .carousel-arrow-next { right: 8px; }
        }
        @media (max-width: 520px) {
            .promo-carousel-section { width: calc(100% - 14px); margin-top: 10px; }
            .promo-carousel { aspect-ratio: 16 / 9; min-height: 160px; }
            .carousel-arrow { display: none; }
        }
    </style>

    <section class="promo-carousel-section" aria-roledescription="carousel" aria-label="Promosi AntaMart">
        <div class="promo-carousel" id="promoCarousel" style="overflow: hidden; position: relative;">
            <div class="carousel-track" id="carouselTrack" style="display: flex; width: 100%; height: 100%;">
                <article class="carousel-slide" role="group" aria-roledescription="slide" aria-label="Slide 1 dari 2: Stok Sembako & Kebutuhan Dapur Menipis?" style="flex: 0 0 100%; min-width: 100%; height: 100%;">
                    <a href="#products" class="carousel-slide-link" style="display: block; width: 100%; height: 100%;" aria-label="Stok Sembako & Kebutuhan Dapur Menipis? Diskon beras, minyak goreng. Belanja sekarang">
                        <img src="{{ asset('images/banners/banner-1.jpg') }}" alt="Stok Sembako & Kebutuhan Dapur Menipis? diskon beras, minyak goreng" style="width: 100%; height: 100%; object-fit: cover; display: block;" loading="eager" decoding="async">
                    </a>
                </article>
                <article class="carousel-slide" role="group" aria-roledescription="slide" aria-label="Slide 2 dari 2: Belanja Harian Lebih Hemat & Praktis!" style="flex: 0 0 100%; min-width: 100%; height: 100%;">
                    <a href="#products" class="carousel-slide-link" style="display: block; width: 100%; height: 100%;" aria-label="Belanja Harian Lebih Hemat & Praktis! Penuhi kebutuhan sembako, sayur, & perlengkapan rumah tangga. Belanja sekarang">
                        <img src="{{ asset('images/banners/banner-2.jpg') }}" alt="Belanja Harian Lebih Hemat & Praktis! Penuhi kebutuhan sembako, sayur, & perlengkapan rumah tangga" style="width: 100%; height: 100%; object-fit: cover; display: block;" loading="lazy" decoding="async">
                    </a>
                </article>
            </div>

            {{-- Previous & Next Navigation Buttons --}}
            <button class="carousel-arrow carousel-arrow-prev" id="carouselPrevBtn" type="button" aria-label="Slide sebelumnya">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            <button class="carousel-arrow carousel-arrow-next" id="carouselNextBtn" type="button" aria-label="Slide berikutnya">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>

            {{-- Indicators / Dots --}}
            <div class="carousel-indicators" id="carouselIndicators" role="tablist" aria-label="Pilih slide promosi">
                <button type="button" class="carousel-dot is-active" role="tab" aria-selected="true" aria-label="Slide 1" data-index="0"></button>
                <button type="button" class="carousel-dot" role="tab" aria-selected="false" aria-label="Slide 2" data-index="1"></button>
            </div>
        </div>
    </section>

    <section class="service-strip" aria-label="Informasi layanan AntaMart">
        <article class="service-card">
            <span class="service-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M12 3 19 6v5c0 4.4-2.8 7.8-7 10-4.2-2.2-7-5.6-7-10V6l7-3Z" stroke="currentColor" stroke-width="1.5"/><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <span><strong>Checkout sebagai tamu</strong><small>Belanja tanpa membuat akun</small></span>
        </article>
        <article class="service-card">
            <span class="service-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><rect x="4" y="5" width="16" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M8 10h8M8 14h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            </span>
            <span><strong>Pembayaran via Xendit</strong><small>Status pembayaran diperbarui</small></span>
        </article>
        <article class="service-card">
            <span class="service-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.5"/><path d="m16 16 4 4M8 11h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            </span>
            <span><strong>Lacak pesanan</strong><small>Gunakan ID pesanan dan email</small></span>
        </article>
        <article class="service-card">
            <span class="service-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M4 7h16v13H4zM8 7V4h8v3M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <span><strong>Stok diperiksa saat checkout</strong><small>Ketersediaan dikonfirmasi kembali</small></span>
        </article>
    </section>

    <section class="catalog-section" id="products" aria-labelledby="catalog-title">
        <div class="section-heading">
            <div><p class="section-eyebrow">ANTAMART</p><h2 id="catalog-title">{{ $selectedCategory?->name ?? 'Produk' }}</h2></div>
            <span class="result-count">{{ $products->total() }} produk</span>
        </div>

        <nav class="filter-chips" aria-label="Filter ketersediaan produk">
            @foreach (['all' => 'Semua', 'in-stock' => 'Tersedia', 'sold-out' => 'Habis'] as $filter => $label)
                <a class="filter-chip {{ $availability === $filter ? 'is-active' : '' }}"
                   href="{{ route('storefront.home', array_filter(['q' => $search, 'availability' => $filter === 'all' ? null : $filter, 'category' => $categoryFilter ?: null])) }}"
                   @if ($availability === $filter) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <nav class="filter-chips category-filter-chips" aria-label="Filter kategori produk">
            <a class="filter-chip {{ $categoryFilter === '' ? 'is-active' : '' }}"
               href="{{ route('storefront.home', array_filter(['q' => $search, 'availability' => $availability === 'all' ? null : $availability])) }}"
               @if ($categoryFilter === '') aria-current="page" @endif>Semua kategori</a>
            @foreach ($categories as $category)
                <a class="filter-chip {{ $categoryFilter === $category->slug ? 'is-active' : '' }} {{ $category->isAgeRestricted() ? 'chip-age-restricted' : '' }}"
                   href="{{ route('storefront.home', array_filter(['q' => $search, 'availability' => $availability === 'all' ? null : $availability, 'category' => $category->slug])) }}"
                   @if ($categoryFilter === $category->slug) aria-current="page" @endif>
                    {{ $category->name }}
                    @if ($category->isAgeRestricted())
                        <span class="badge-age-limit" aria-label="Batas usia {{ $category->min_age }} tahun">{{ $category->min_age }}+</span>
                    @endif
                </a>
            @endforeach
        </nav>

        @if ($products->count())
            <div class="product-grid">
                @foreach ($products as $product)
                    <x-product-card :product="$product" :href="route('storefront.products.show', $product['slug'])" />
                @endforeach
            </div>

            @if ($products->hasPages())
                <nav class="pagination" aria-label="Navigasi halaman produk">
                    @if ($products->onFirstPage())
                        <span class="pagination-disabled">Sebelumnya</span>
                    @else
                        <a href="{{ $products->previousPageUrl() }}">Sebelumnya</a>
                    @endif
                    <span>Halaman {{ $products->currentPage() }} dari {{ $products->lastPage() }}</span>
                    @if ($products->hasMorePages())
                        <a href="{{ $products->nextPageUrl() }}">Berikutnya</a>
                    @else
                        <span class="pagination-disabled">Berikutnya</span>
                    @endif
                </nav>
            @endif
        @else
            <x-empty-state
                class="catalog-empty"
                :title="$search ? 'Produk tidak ditemukan' : ($selectedCategory ? 'Belum ada produk di kategori ini' : 'Belum ada produk untuk ditampilkan')"
                :description="$search ? 'Coba kata pencarian lain.' : ($selectedCategory ? 'Belum ada produk aktif di kategori ini.' : 'Silakan kembali lagi nanti.')"
            />
        @endif
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const carousel = document.getElementById('promoCarousel');
            const track = document.getElementById('carouselTrack');
            const prevBtn = document.getElementById('carouselPrevBtn');
            const nextBtn = document.getElementById('carouselNextBtn');
            const indicatorsContainer = document.getElementById('carouselIndicators');
            if (!carousel || !track) return;

            const slides = track.querySelectorAll('.carousel-slide');
            const dots = indicatorsContainer ? indicatorsContainer.querySelectorAll('.carousel-dot') : [];
            const totalSlides = slides.length;
            if (totalSlides <= 1) return;

            let currentIndex = 0;
            let autoSlideTimer = null;
            const autoSlideInterval = 5000; // 5 seconds

            function goToSlide(index) {
                currentIndex = (index + totalSlides) % totalSlides;
                track.style.transform = 'translateX(-' + (currentIndex * 100) + '%)';

                dots.forEach(function (dot, i) {
                    const isActive = i === currentIndex;
                    dot.classList.toggle('is-active', isActive);
                    dot.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });
            }

            function nextSlide() {
                goToSlide(currentIndex + 1);
            }

            function prevSlide() {
                goToSlide(currentIndex - 1);
            }

            function startTimer() {
                stopTimer();
                autoSlideTimer = setInterval(nextSlide, autoSlideInterval);
            }

            function stopTimer() {
                if (autoSlideTimer) {
                    clearInterval(autoSlideTimer);
                    autoSlideTimer = null;
                }
            }

            function resetTimer() {
                startTimer();
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    nextSlide();
                    resetTimer();
                });
            }

            if (prevBtn) {
                prevBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    prevSlide();
                    resetTimer();
                });
            }

            dots.forEach(function (dot) {
                dot.addEventListener('click', function (e) {
                    e.preventDefault();
                    const index = parseInt(dot.getAttribute('data-index'), 10);
                    if (!isNaN(index)) {
                        goToSlide(index);
                        resetTimer();
                    }
                });
            });

            // Pause on hover
            carousel.addEventListener('mouseenter', stopTimer);
            carousel.addEventListener('mouseleave', startTimer);

            // Pause when page is hidden / tab inactive
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    stopTimer();
                } else {
                    startTimer();
                }
            });

            // Touch swipe support for mobile
            let touchStartX = 0;
            let touchEndX = 0;

            carousel.addEventListener('touchstart', function (e) {
                stopTimer();
                touchStartX = e.changedTouches[0].screenX;
            }, { passive: true });

            carousel.addEventListener('touchend', function (e) {
                touchEndX = e.changedTouches[0].screenX;
                const diffX = touchEndX - touchStartX;
                if (Math.abs(diffX) > 40) {
                    if (diffX > 0) {
                        prevSlide();
                    } else {
                        nextSlide();
                    }
                }
                startTimer();
            }, { passive: true });

            // Start auto-slide timer
            startTimer();
        });
    </script>
@endsection
