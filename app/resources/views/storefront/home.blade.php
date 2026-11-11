@extends('layouts.app')

@section('title', 'Belanja kebutuhan sehari-hari')

@section('content')
    <section class="promo-carousel-section" aria-roledescription="carousel" aria-label="Promosi AntaMart">
        <div class="promo-carousel" id="promoCarousel">
            <div class="carousel-track" id="carouselTrack">
                <article class="carousel-slide" role="group" aria-roledescription="slide" aria-label="Slide 1 dari 2: Stok Sembako & Kebutuhan Dapur Menipis?">
                    <a href="#products" class="carousel-slide-link" aria-label="Stok Sembako & Kebutuhan Dapur Menipis? Diskon beras, minyak goreng. Belanja sekarang">
                        <img src="{{ asset('images/banners/banner-1.jpg') }}" alt="Stok Sembako & Kebutuhan Dapur Menipis? diskon beras, minyak goreng" loading="eager" decoding="async">
                    </a>
                </article>
                <article class="carousel-slide" role="group" aria-roledescription="slide" aria-label="Slide 2 dari 2: Belanja Harian Lebih Hemat & Praktis!">
                    <a href="#products" class="carousel-slide-link" aria-label="Belanja Harian Lebih Hemat & Praktis! Penuhi kebutuhan sembako, sayur, & perlengkapan rumah tangga. Belanja sekarang">
                        <img src="{{ asset('images/banners/banner-2.jpg') }}" alt="Belanja Harian Lebih Hemat & Praktis! Penuhi kebutuhan sembako, sayur, & perlengkapan rumah tangga" loading="lazy" decoding="async">
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
