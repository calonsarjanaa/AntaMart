@extends('layouts.app')

@section('title', 'Access denied')

@section('content')
    <section class="owner-placeholder page-container" aria-labelledby="forbidden-title">
        <p class="eyebrow">403 · Access restricted</p>
        <h1 id="forbidden-title">This space is<br><em>for the store team.</em></h1>
        <p class="owner-placeholder-copy">You don’t have permission to view this page.</p>
        <a class="button button-dark" href="{{ route('storefront.home') }}">Return to AntaMart</a>
    </section>
@endsection
