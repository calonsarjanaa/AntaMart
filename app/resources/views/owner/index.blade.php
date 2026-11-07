@extends('layouts.app')

@section('title', 'Owner dashboard')

@section('content')
    <section class="owner-placeholder page-container" aria-labelledby="owner-title">
        <p class="eyebrow">AntaMart · Store operations</p>
        <h1 id="owner-title">Your workspace<br><em>is taking shape.</em></h1>
        <p class="owner-placeholder-copy">The owner console will bring orders, cancellation requests, refunds, and shipments together in one place.</p>
        <a class="text-link" href="{{ route('storefront.home') }}">← Back to the storefront</a>
    </section>
@endsection
