@extends('layouts.app')

@section('title', 'Good things for everyday')

@section('content')
    <section class="hero" aria-labelledby="hero-title">
        <div class="hero-copy">
            <p class="eyebrow"><span class="eyebrow-dot" aria-hidden="true"></span> A little more considered</p>
            <h1 id="hero-title">Good things<br>for <em>everyday.</em></h1>
            <p class="hero-description">A thoughtful collection for the rituals, rooms, and little moments that make a day feel like yours.</p>
            <a class="button button-dark" href="#collection">Explore the collection
                <svg aria-hidden="true" viewBox="0 0 20 20" fill="none"><path d="M3.5 10h13m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
            <div class="hero-note"><span class="note-line" aria-hidden="true"></span><span>Small details. Made to matter.</span></div>
        </div>
        <div class="hero-art" role="img" aria-label="Abstract still life in warm, natural colors">
            <div class="art-sun"></div><div class="art-arch art-arch-back"></div><div class="art-arch art-arch-front"></div>
            <div class="art-vase"><div class="vase-neck"></div><div class="vase-body"></div></div>
            <div class="art-stem stem-one"><i></i><i></i><i></i></div><div class="art-stem stem-two"><i></i><i></i></div>
            <div class="art-table"></div><span class="art-caption">OBJECTS FOR SLOWER DAYS <span>— 01</span></span>
        </div>
        <div class="hero-index" aria-hidden="true"><span>01</span><i></i><span>03</span></div>
    </section>
    <section class="intro-strip" aria-label="Our approach">
        <p>Made for the everyday, <span>kept for a little longer.</span></p><span class="intro-symbol" aria-hidden="true">✳</span>
        <p>Useful things, <span>with a point of view.</span></p>
    </section>
    <section class="collection-section" id="collection" aria-labelledby="collection-title">
        <div class="section-heading"><div><p class="eyebrow">A considered edit</p><h2 id="collection-title">The collection</h2></div><span class="section-count">01 — 04</span></div>
        <div class="collection-empty">
            <div class="empty-mark" aria-hidden="true">✳</div><p class="empty-title">Something good is taking shape.</p>
            <p class="empty-copy">We’re putting the finishing touches on our first collection. Come back soon to find your new everyday favorite.</p>
            <span class="empty-label"><span class="eyebrow-dot" aria-hidden="true"></span> First collection, coming soon</span>
        </div>
    </section>
    <section class="story-section" id="our-story" aria-labelledby="story-title">
        <div class="story-number" aria-hidden="true">A.</div>
        <div class="story-copy"><p class="eyebrow">A note from us</p><h2 id="story-title">Less, but<br><em>more meaningful.</em></h2>
            <p>We believe the things we live with should earn their place. AntaMart is being built around useful objects, considered choices, and the small joy of finding just the right thing.</p>
        </div>
        <div class="story-aside"><span>THOUGHTFULLY<br>GATHERED</span><i aria-hidden="true">✳</i><span>FOR REAL LIFE</span></div>
    </section>
@endsection
