@extends('layouts.owner')

@section('title', 'Dashboard owner')

@section('breadcrumbs')
    <span>Dashboard</span>
@endsection

@section('content')
    <section class="owner-dashboard" aria-labelledby="owner-title">
        <p class="section-eyebrow">ANTAMART · OPERASIONAL TOKO</p>
        <h1 id="owner-title">Dashboard</h1>
        <div class="owner-welcome-card">
            <h2>Selamat datang, {{ auth('web')->user()->name }}</h2>
            <p>Console owner siap digunakan. Pengelolaan pesanan, pembatalan, pengembalian dana, dan pengiriman akan tersedia pada bagian berikutnya.</p>
        </div>
    </section>
@endsection
