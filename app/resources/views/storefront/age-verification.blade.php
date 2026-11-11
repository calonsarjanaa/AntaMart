@extends('layouts.app')

@section('title', 'Verifikasi Usia ' . $minAge . '+ · AntaMart')

@section('content')
    <section class="age-verification-page page-container" aria-labelledby="age-verify-title">
        <div class="age-verify-card">
            <div class="age-badge-circle" aria-hidden="true">{{ $minAge }}+</div>
            <p class="section-eyebrow">KATEGORI KHUSUS DEWASA</p>
            <h1 id="age-verify-title">Verifikasi Usia Anda</h1>
            <p class="age-verify-intro">
                Kategori <strong>{{ $category->name }}</strong> mengandung produk khusus konsumen berusia {{ $minAge }} tahun ke atas. Sesuai ketentuan hukum yang berlaku, mohon masukkan tanggal lahir Anda untuk melanjutkan.
            </p>

            @if ($errors->any())
                <div class="alert alert-danger" role="alert" style="margin-bottom: 16px; text-align: left;">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form class="age-verify-form" method="post" action="{{ route('storefront.age-verification.verify', $category->slug) }}">
                @csrf
                @if (!empty($returnUrl))
                    <input type="hidden" name="return_url" value="{{ $returnUrl }}">
                @endif

                <div class="form-field">
                    <label class="field-label" for="birth_date">
                        Tanggal Lahir Anda <span class="required-mark" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="birth_date"
                        name="birth_date"
                        type="date"
                        class="field-input @error('birth_date') field-input-invalid @enderror"
                        value="{{ old('birth_date') }}"
                        max="{{ now()->format('Y-m-d') }}"
                        required
                        autofocus
                    >
                    <small style="display: block; margin-top: 5px; color: #64748b; font-size: 10px;">
                        Masukkan tanggal, bulan, dan tahun kelahiran Anda.
                    </small>
                </div>

                <button class="button button-primary" type="submit">Verifikasi & Lanjutkan</button>
            </form>

            <a class="age-verify-back" href="{{ route('storefront.home') }}">← Kembali ke Beranda</a>
        </div>
    </section>
@endsection
