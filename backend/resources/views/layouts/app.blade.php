<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  {{-- Zoom stays enabled: the PDP's lens/magnifier is desktop-only (products/show
       gates it behind innerWidth >= 1024), so pinch is the only way a phone shopper
       can inspect a piece — and blocking it fails WCAG 2.1 SC 1.4.4. --}}
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('meta_title', ($siteSettings['site_name'] ?? 'Estele').' — '.($siteSettings['site_tagline'] ?? ''))</title>
  <meta name="description" content="@yield('meta_description', $siteSettings['site_tagline'] ?? '')">
  <link rel="canonical" href="@yield('canonical', url()->current())">

  {{-- hreflang scaffolding (spec §4.1 — "even if single-language at launch,
       future multi-region readiness"). Self-referencing en-IN plus x-default
       is the minimum valid hreflang set; add more <link> tags here per
       locale/region if the site ever ships additional languages. --}}
  <link rel="alternate" hreflang="en-in" href="@yield('canonical', url()->current())">
  <link rel="alternate" hreflang="x-default" href="@yield('canonical', url()->current())">

  {{-- rel=next/prev on paginated listing pages (spec §4.1 — avoids duplicate-
       content signals across category/blog/search pagination); pushed from
       the individual views via @push('pagination_links'), see x-pagination-links. --}}
  @stack('pagination_links')

  <meta property="og:type" content="@yield('og_type', 'website')">
  <meta property="og:site_name" content="{{ $siteSettings['site_name'] ?? 'Estele' }}">
  <meta property="og:url" content="@yield('canonical', url()->current())">
  <meta property="og:title" content="@yield('meta_title', ($siteSettings['site_name'] ?? 'Estele').' — '.($siteSettings['site_tagline'] ?? ''))">
  <meta property="og:description" content="@yield('meta_description', $siteSettings['site_tagline'] ?? '')">
  @hasSection('og_image')
    <meta property="og:image" content="@yield('og_image')">
  @endif

  <meta name="twitter:card" content="{{ $__env->hasSection('og_image') ? 'summary_large_image' : 'summary' }}">
  <meta name="twitter:title" content="@yield('meta_title', ($siteSettings['site_name'] ?? 'Estele').' — '.($siteSettings['site_tagline'] ?? ''))">
  <meta name="twitter:description" content="@yield('meta_description', $siteSettings['site_tagline'] ?? '')">
  @hasSection('og_image')
    <meta name="twitter:image" content="@yield('og_image')">
  @endif

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Allura&family=Cinzel:wght@500;600;700&family=Lato:wght@400;700;900&family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,400&display=swap">
  <link rel="stylesheet" href="{{ asset('theme/app.css') }}?v={{ @filemtime(public_path('theme/app.css')) }}">

  {{-- Admin-managed GA4/GSC/Meta Pixel etc (Settings key 'tracking_head_scripts',
       see the SEO Settings section of the admin Settings resource) — raw
       markup rendered verbatim, blank/no-op until an admin pastes a real
       snippet in. Trusted input: only super_admin can edit Settings. --}}
  @if(! empty($siteSettings['tracking_head_scripts']))
    {!! $siteSettings['tracking_head_scripts'] !!}
  @endif
</head>
<body>
<a class="sr-only-custom" href="#main">Skip to content</a>

@php $announcements = json_decode($siteSettings['announcement_messages'] ?? '[]', true) ?: []; @endphp
<div class="w-full bg-announce py-2 text-center text-[11px] font-medium uppercase tracking-[0.12em] text-white" data-announcement>
  @if(count($announcements))
    <div class="relative h-4">
      @foreach($announcements as $index => $message)
        <p class="absolute inset-x-0 top-0 m-0 px-3 transition-opacity duration-500 {{ $index === 0 ? 'opacity-100' : 'opacity-0 pointer-events-none' }}" data-announce-item>{{ $message }}</p>
      @endforeach
    </div>
  @else
    <p class="m-0 px-3">Free Express Shipping on Orders Above &#8377;1,499 &middot; Use Code <span class="font-semibold text-gold">ESTELE50</span> for Flat 50% Off</p>
  @endif
</div>
@if(count($activeOffers))
  <div class="hidden w-full border-b border-line bg-pinksoft py-1.5 text-[11px] text-heading md:block">
    <div class="mx-auto flex w-full max-w-wrapper items-center justify-center gap-6 px-4">
      @foreach($activeOffers as $offer)
        <span class="inline-flex items-center gap-1.5"><span class="text-gold">&#10022;</span>{{ $offer }}</span>
      @endforeach
    </div>
  </div>
@endif

<header class="header-gradient sticky top-0 z-[100] px-1.5 py-2 md:px-[30px] md:pt-[15px] md:pb-3" data-header>
  <div class="flex items-center gap-1 lg:grid lg:grid-cols-[1fr_auto_1fr] lg:gap-5">
    <div class="flex items-center lg:gap-1.5">
      <button class="grid h-10 w-10 place-items-center text-heading lg:hidden" type="button"
              data-menu-open aria-label="Open menu" aria-expanded="false">
        <span class="sr-only-custom">Menu</span>
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
      </button>
    </div>

    <a class="wordmark shrink-0 text-[22px] text-accent-dark md:text-[24px] lg:justify-self-center lg:text-heading" href="{{ route('home') }}">
      {{ $siteSettings['site_name'] ?? 'Estele' }}
    </a>

    <div class="ml-auto flex items-center justify-end md:gap-2 lg:ml-0">
      {{-- overflow-hidden used to live on this <form> itself (to round the pill
           shape), but the suggestions dropdown below is an absolutely-positioned
           child of the same form sitting at top-full — entirely outside the
           form's own box — so that overflow-hidden clipped it to invisible even
           though the JS was populating it correctly. Moved the pill clipping to
           an inner wrapper around just the input+button row so the dropdown,
           still a direct child of the form, is no longer inside a clipping box. --}}
      <form class="relative hidden items-center lg:flex lg:w-[230px] xl:w-[300px]"
            action="{{ route('search') }}" method="get" role="search" data-search-autocomplete>
        <div class="flex w-full items-center overflow-hidden rounded-full border border-line-strong bg-white/70 text-muted">
          <input class="w-full min-w-0 border-0 bg-transparent px-3 py-2 text-[13px] text-ink outline-none placeholder:text-muted" type="search" name="q" placeholder="Search for products" aria-label="Search for products" autocomplete="off">
          <button class="grid h-[38px] w-[38px] shrink-0 place-items-center text-heading" type="submit" aria-label="Submit search">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
          </button>
        </div>
        <div class="absolute left-0 top-full z-20 mt-1 hidden w-full min-w-[280px] overflow-hidden rounded-lg border border-line bg-white text-ink shadow-lg" data-search-suggestions></div>
      </form>
      <button class="relative grid h-10 w-10 place-items-center text-heading transition-colors hover:[color:var(--nav-hover-color)] lg:hidden" type="button" data-search-open aria-label="Search">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><circle cx="11" cy="11" r="7.5"/><path d="M21 21l-4.3-4.3"/></svg>
      </button>
      <a class="relative grid h-10 w-10 place-items-center text-heading transition-colors hover:[color:var(--nav-hover-color)]" href="{{ route('wishlist') }}" aria-label="Wishlist">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21.2l7.7-7.7 1.1-1.1a5.5 5.5 0 0 0 0-7.8z"/></svg>
        <span class="absolute right-0.5 top-0.5 hidden h-4 min-w-4 place-items-center rounded-lg bg-accent-dark px-1 text-[10px] font-bold leading-none text-white" data-wishlist-count>0</span>
      </a>
      <a class="relative grid h-10 w-10 place-items-center text-heading transition-colors hover:[color:var(--nav-hover-color)]" href="{{ auth()->check() ? route('account.index') : route('login') }}" aria-label="Account">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="10" r="3.2"/><path d="M5.6 19.2a7 7 0 0 1 12.8 0"/></svg>
      </a>
      <a class="relative grid h-10 w-10 place-items-center text-heading transition-colors hover:[color:var(--nav-hover-color)]" href="{{ route('cart.index') }}" aria-label="Bag" data-cart-open>
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><path d="M5 8h14l1 13H4z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
        <span class="absolute right-0.5 top-0.5 grid h-4 min-w-4 place-items-center rounded-lg bg-accent-dark px-1 text-[10px] font-bold leading-none text-white" style="{{ $cartCount > 0 ? '' : 'display:none' }}" data-cart-count-badge>{{ $cartCount }}</span>
      </a>
    </div>
  </div>

  @php
    $navCollectionsBySlug = collect($navCollections)->keyBy('slug');
    $hasliCollection = $navCollectionsBySlug->get('hasli-collection');
    $crystalBloomsCollection = $navCollectionsBySlug->get('crystal-blooms');
    $newArrivalsCollection = $navCollectionsBySlug->get('new-arrivals');
    $sitaraCollection = $navCollectionsBySlug->get('sitara-collection');
    $weddingSeasonCollection = $navCollectionsBySlug->get('wedding-season');
    $roseCollection = $navCollectionsBySlug->get('rose-collection');
    $bestSellerCollection = $navCollectionsBySlug->get('best-seller');
    $navCategoriesBySlug = collect($navCategories)->keyBy('slug');
    $necklaceSubCategories = collect(['necklace-sets', 'pendant-sets', 'choker-sets', 'mangalsutra'])
      ->map(fn ($slug) => $navCategoriesBySlug->get($slug))
      ->filter();
  @endphp
  <nav class="mt-2 hidden lg:block" aria-label="Main navigation">
    <ul class="flex items-center justify-between">
      @if($hasliCollection)
        <li><a class="relative inline-flex items-center whitespace-nowrap py-2 text-[11.5px] font-medium leading-[14px] uppercase tracking-[0.1em] text-heading transition-colors hover:[color:var(--nav-hover-color)]" href="{{ route('collections.show', $hasliCollection['slug']) }}">HASLI COLLECTION<span class="ml-1 rounded-full bg-accent px-1.5 py-0.5 text-[8px] font-semibold leading-none text-white">NEW</span></a></li>
      @endif
      @if($crystalBloomsCollection)
        <li><a class="relative inline-flex items-center whitespace-nowrap py-2 text-[11.5px] font-medium leading-[14px] uppercase tracking-[0.1em] text-heading transition-colors hover:[color:var(--nav-hover-color)]" href="{{ route('collections.show', $crystalBloomsCollection['slug']) }}">CRYSTAL BLOOMS</a></li>
      @endif
      @if($newArrivalsCollection)
        <li><a class="relative inline-flex items-center whitespace-nowrap py-2 text-[11.5px] font-medium leading-[14px] uppercase tracking-[0.1em] text-heading transition-colors hover:[color:var(--nav-hover-color)]" href="{{ route('collections.show', $newArrivalsCollection['slug']) }}">NEW ARRIVALS</a></li>
      @endif
      @if($sitaraCollection)
        <li><a class="relative inline-flex items-center whitespace-nowrap py-2 text-[11.5px] font-medium leading-[14px] uppercase tracking-[0.1em] text-heading transition-colors hover:[color:var(--nav-hover-color)]" href="{{ route('collections.show', $sitaraCollection['slug']) }}">SITARA COLLECTION</a></li>
      @endif
      @if($weddingSeasonCollection)
        <li><a class="relative inline-flex items-center whitespace-nowrap py-2 text-[11.5px] font-medium leading-[14px] uppercase tracking-[0.1em] text-heading transition-colors hover:[color:var(--nav-hover-color)]" href="{{ route('collections.show', $weddingSeasonCollection['slug']) }}">WEDDING SEASON</a></li>
      @endif
      @if($roseCollection)
        <li><a class="relative inline-flex items-center whitespace-nowrap py-2 text-[11.5px] font-medium leading-[14px] uppercase tracking-[0.1em] text-heading transition-colors hover:[color:var(--nav-hover-color)]" href="{{ route('collections.show', $roseCollection['slug']) }}">ROSE COLLECTION</a></li>
      @endif
      @if($necklaceSubCategories->isNotEmpty())
        <li class="group relative">
          <a class="relative flex items-center gap-1 whitespace-nowrap py-2 text-[11.5px] font-medium leading-[14px] uppercase tracking-[0.1em] text-heading transition-colors hover:[color:var(--nav-hover-color)]" href="{{ route('categories.show', $necklaceSubCategories->first()['slug']) }}">NECKLACES
            <svg class="h-2.5 w-2.5 shrink-0 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
          </a>
          <div class="invisible absolute left-1/2 top-full z-20 w-56 -translate-x-1/2 translate-y-1 rounded-lg border border-line header-gradient p-2 opacity-0 shadow-lg transition-all duration-150 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100">
            @foreach($necklaceSubCategories as $category)
              <a class="block rounded-md px-3 py-2 text-[12px] text-heading transition-colors hover:bg-pinksoft hover:text-accent" href="{{ route('categories.show', $category['slug']) }}">{{ $category['name'] }}</a>
            @endforeach
          </div>
        </li>
      @endif
      @if(count($navCategories))
        <li class="group relative">
          <a class="relative flex items-center gap-1 whitespace-nowrap py-2 text-[11.5px] font-medium leading-[14px] uppercase tracking-[0.1em] text-heading transition-colors hover:[color:var(--nav-hover-color)]" href="{{ route('categories.index') }}">CATEGORIES
            <svg class="h-2.5 w-2.5 shrink-0 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
          </a>
          <div class="invisible absolute left-1/2 top-full z-20 w-56 -translate-x-1/2 translate-y-1 rounded-lg border border-line header-gradient p-2 opacity-0 shadow-lg transition-all duration-150 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100">
            @foreach($navCategories as $category)
              <a class="block rounded-md px-3 py-2 text-[12px] text-heading transition-colors hover:bg-pinksoft hover:text-accent" href="{{ route('categories.show', $category['slug']) }}">{{ $category['name'] }}</a>
            @endforeach
          </div>
        </li>
      @endif
      @if($bestSellerCollection)
        <li><a class="relative inline-flex items-center whitespace-nowrap py-2 text-[11.5px] font-medium leading-[14px] uppercase tracking-[0.1em] text-heading transition-colors hover:[color:var(--nav-hover-color)]" href="{{ route('collections.show', $bestSellerCollection['slug']) }}">BEST SELLER</a></li>
      @endif
      @if(count($navCollections))
        <li class="group relative">
          <a class="relative flex items-center gap-1 whitespace-nowrap py-2 text-[11.5px] font-medium leading-[14px] uppercase tracking-[0.1em] text-heading transition-colors hover:[color:var(--nav-hover-color)]" href="{{ route('collections.index') }}">COLLECTIONS
            <svg class="h-2.5 w-2.5 shrink-0 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
          </a>
          {{-- Right-anchored (not center-anchored like the other two dropdowns
               above): this is the last item in the nav bar, so a w-56 panel
               centered under its trigger runs past the right edge of the
               viewport — invisible (visibility:hidden, not display:none) so
               nothing looks visually broken, but it still occupies layout
               space and was forcing a horizontal scrollbar on every single
               page (this partial is shared by the whole site's header).
               right-0 keeps the panel's right edge flush with the trigger's
               own right edge, which is already safely inside the viewport. --}}
          <div class="invisible absolute right-0 top-full z-20 max-h-[380px] w-56 translate-y-1 overflow-y-auto rounded-lg border border-line header-gradient p-2 opacity-0 shadow-lg transition-all duration-150 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100">
            @foreach($navCollections as $collection)
              <a class="block rounded-md px-3 py-2 text-[12px] text-heading transition-colors hover:bg-pinksoft hover:text-accent" href="{{ route('collections.show', $collection['slug']) }}">{{ $collection['name'] }}</a>
            @endforeach
          </div>
        </li>
      @endif
    </ul>
  </nav>

</header>

<div class="fixed inset-0 z-[200]" data-drawer hidden>
  <div class="absolute inset-0 bg-black/45 opacity-0 transition-opacity duration-300" data-drawer-backdrop data-menu-close></div>
  <nav class="absolute left-0 top-0 flex h-full w-[87vw] max-w-[380px] -translate-x-full flex-col bg-white transition-transform duration-300"
       data-drawer-panel aria-label="Mobile navigation">
    <div class="flex h-[52px] shrink-0 items-center justify-end border-b border-line-strong px-4">
      <button class="grid h-10 w-10 place-items-center text-heading" type="button" data-menu-close aria-label="Close menu">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M5 5l14 14M19 5L5 19"/></svg>
      </button>
    </div>
    @php
      $drawerLinks = collect([$hasliCollection, $crystalBloomsCollection, $newArrivalsCollection, $sitaraCollection, $weddingSeasonCollection, $roseCollection, $bestSellerCollection])->filter();
    @endphp
    <ul class="flex-1 overflow-y-auto pb-4 pt-1">
      @foreach($drawerLinks as $link)
        <li><a class="flex items-center justify-between px-2.5 py-3 text-[16px] text-heading active:bg-pinksoft" href="{{ route('collections.show', $link['slug']) }}">{{ $link['name'] }}<svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 5l7 7-7 7"/></svg></a></li>
      @endforeach
      @if(count($navCategories))
        <li>
          <details class="group/menu">
            <summary class="flex items-center justify-between px-2.5 py-3 text-[16px] text-heading">Shop By Category<svg class="h-4 w-4 shrink-0 rotate-90 transition-transform duration-200 group-open/menu:-rotate-90" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 5l7 7-7 7"/></svg></summary>
            <ul class="pb-2">
              @foreach($navCategories as $category)
                <li><a class="block py-2.5 pl-[34px] pr-5 text-[15px] text-heading active:bg-pinksoft" href="{{ route('categories.show', $category['slug']) }}">{{ $category['name'] }}</a></li>
              @endforeach
            </ul>
          </details>
        </li>
      @endif
      @if(count($navCollections))
        <li>
          <details class="group/menu">
            <summary class="flex items-center justify-between px-2.5 py-3 text-[16px] text-heading">Shop By Collection<svg class="h-4 w-4 shrink-0 rotate-90 transition-transform duration-200 group-open/menu:-rotate-90" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 5l7 7-7 7"/></svg></summary>
            <ul class="pb-2">
              @foreach($navCollections as $collection)
                <li><a class="block py-2.5 pl-[34px] pr-5 text-[15px] text-heading active:bg-pinksoft" href="{{ route('collections.show', $collection['slug']) }}">{{ $collection['name'] }}</a></li>
              @endforeach
            </ul>
          </details>
        </li>
      @endif
    </ul>
    <div class="shrink-0 px-5 pb-[calc(16px+env(safe-area-inset-bottom))]">
      <div class="grid grid-cols-2 border-y border-line-strong">
        <a class="flex items-center justify-center gap-2 border-r border-line-strong py-4 text-[15px] text-heading" href="{{ auth()->check() ? route('account.index') : route('login') }}">
          <svg class="h-5 w-5 text-accent-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a7 7 0 0 1 7-7h2a7 7 0 0 1 7 7v1"/></svg>
          {{ auth()->check() ? 'My Account' : 'Login' }}
        </a>
        <a class="flex items-center justify-center gap-2 py-4 text-[15px] text-heading" href="{{ ! empty($siteSettings['contact_phone']) ? 'tel:'.$siteSettings['contact_phone'] : route('home') }}">
          <svg class="h-5 w-5 text-accent-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>
          Contact Us
        </a>
      </div>
      <div class="pt-4 text-center">
        <p class="mb-2 text-[10.5px] font-semibold uppercase tracking-[0.18em] text-muted">Download the app</p>
        <div class="flex justify-center gap-2.5">
          <img src="{{ asset('assets/images/badges/app-store.svg') }}" class="h-8" alt="App Store" width="100" height="32">
          <img src="{{ asset('assets/images/badges/google-play.svg') }}" class="h-8" alt="Google Play" width="100" height="32">
        </div>
      </div>
    </div>
  </nav>
</div>

@include('partials.cart-drawer')
@include('partials.coupons-modal')
@include('partials.popup')

<div class="fixed inset-0 z-[200]" data-search hidden>
  <div class="absolute inset-0 bg-black/45" data-search-close></div>
  <div class="relative bg-white px-3 py-2.5 shadow-[0_2px_12px_rgba(0,0,0,0.08)] md:py-10">
    <form class="relative mx-auto flex max-w-[720px] items-center gap-1" action="{{ route('search') }}" method="get" role="search" data-search-autocomplete>
      <button class="grid h-11 w-10 shrink-0 place-items-center text-heading" type="button" data-search-close aria-label="Close search">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
      </button>
      <div class="field h-11 flex-1 rounded-lg">
        <input type="search" name="q" placeholder="Search for jewellery..." aria-label="Search" autocomplete="off">
        <button class="grid h-9 w-9 shrink-0 place-items-center text-heading" type="submit" aria-label="Submit search">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="11" cy="11" r="7.5"/><path d="M21 21l-4.3-4.3"/></svg>
        </button>
      </div>
      <div class="absolute left-10 right-0 top-full z-20 mt-1 hidden overflow-hidden rounded-lg border border-line bg-white shadow-lg" data-search-suggestions></div>
    </form>
  </div>
</div>

<div class="page-loader" data-page-loader aria-hidden="true"><span class="page-loader__disc"><span class="page-loader__arc"></span></span></div>

<main id="main">
  @if(session('success'))
    <div class="mx-auto w-full max-w-wrapper px-3 pt-4 md:px-4">
      <p class="rounded-lg border border-line bg-pinksoft px-4 py-3 text-[13px] text-heading">{{ session('success') }}</p>
    </div>
  @endif
  @if(session('error'))
    <div class="mx-auto w-full max-w-wrapper px-3 pt-4 md:px-4">
      <p class="rounded-lg border border-salebadge bg-white px-4 py-3 text-[13px] text-salebadge">{{ session('error') }}</p>
    </div>
  @endif
  @yield('content')
</main>

{{--
  A few rules below (mobile/desktop split, copyright row layout, social-icon hover) are
  hand-written instead of Tailwind md:/hover:bg-* utility classes: backend/public/theme has
  no live Tailwind rebuild, so any utility class not already compiled in silently no-ops on
  the deployed site (see project memory). Scoped here rather than relying on new classes.
--}}
<style>
  .footer-mobile { display: block; }
  .footer-desktop { display: none; }
</style>
@include('partials.footer')

{{-- Mobile tab bar. Rendered after the footer so it's the last fixed element
     in the source order; it hides itself from md up. The spacer keeps the
     footer's final row clear of the fixed bar on phones. --}}
<div class="bottom-nav-spacer h-[calc(56px+env(safe-area-inset-bottom))] md:hidden" aria-hidden="true"></div>
@include('partials.bottom-nav')

{{-- ============================================================
     FLOATING CART BUBBLE — bottom-right, visible only when cart
     has items. Tapping opens the cart drawer. Desktop only: on a phone
     the header bag icon already shows the live count, and the bottom
     edge belongs to the tab bar and the pages' sticky action bars.
     ============================================================ --}}
<a href="/cart"
   id="floating-cart-btn"
   data-cart-open
   aria-label="View cart"
   hidden
   class="fixed bottom-[22px] right-[22px] z-[120] hidden h-[58px] w-[58px] items-center justify-center rounded-full bg-heading text-white shadow-xl transition-all duration-300 hover:bg-accent md:flex"
   style="transform:scale(0);opacity:0;transition:transform .3s cubic-bezier(.4,0,.2,1),opacity .3s,background .2s;">
  <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
    <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
    <line x1="3" y1="6" x2="21" y2="6"/>
    <path d="M16 10a4 4 0 0 1-8 0"/>
  </svg>
  <span class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-accent px-1 text-[11px] font-semibold leading-none text-white"
        data-floating-cart-count aria-live="polite">0</span>
</a>

{{-- ============================================================
     CHATBOT — collapsed as a vertical tab on the right edge.
     Click to expand full chat panel.
     ============================================================ --}}
<style>
  /* Vertical chat tab pill on the right edge */
  .chat-tab-pill {
    position: fixed;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    z-index: 119;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    background: #1f1f1f;
    color: #fff;
    padding: 14px 9px;
    border-radius: 12px 0 0 12px;
    cursor: pointer;
    box-shadow: -2px 0 18px rgba(0,0,0,.2);
    border: none;
    transition: background .2s, right .3s;
  }
  .chat-tab-pill:hover { background: #2d2d2d; }
  .chat-tab-pill img { width: 26px; height: 26px; border-radius: 50%; object-fit: cover; }
  .chat-tab-pill .tab-label {
    font-size: 10.5px; letter-spacing: .05em; opacity: .8;
    writing-mode: vertical-rl; text-orientation: mixed;
  }
  .chat-tab-pill .online-dot { width: 7px; height: 7px; border-radius: 50%; background: #22c55e; }

  /* Full chat panel — slides from right */
  .chat-full-panel {
    position: fixed;
    right: 0;
    top: 50%;
    transform: translateY(-50%) translateX(110%);
    z-index: 125;
    width: min(340px, calc(100vw - 16px));
    max-height: min(520px, calc(100vh - 100px));
    border-radius: 18px 0 0 18px;
    background: #fff;
    box-shadow: -6px 0 40px rgba(0,0,0,.2);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: transform .3s cubic-bezier(.4,0,.2,1);
  }
  .chat-full-panel.is-open {
    transform: translateY(-50%) translateX(0);
  }
  /* Mobile: a compact round bubble just above the tab bar (a vertical tab
     on the right edge covered product tiles and carousel arrows), and the
     panel slides up from the bottom. */
  @media (max-width: 767px) {
    .chat-tab-pill {
      top: auto; right: 12px; bottom: calc(68px + env(safe-area-inset-bottom)); transform: none;
      width: 48px; height: 48px; padding: 0; justify-content: center; border-radius: 9999px;
      box-shadow: 0 6px 18px rgba(0,0,0,.22);
    }
    .chat-tab-pill img { width: 30px; height: 30px; }
    .chat-tab-pill .tab-label { display: none; }
    .chat-tab-pill .online-dot { position: absolute; right: 3px; bottom: 3px; width: 11px; height: 11px; border: 2px solid #1f1f1f; }
    /* Pages that pin their own action bar (product, cart, listings) hide the
       tab bar; lift the bubble clear of the taller product/cart bar. */
    body:has(.buybar) .chat-tab-pill { bottom: calc(79px + env(safe-area-inset-bottom)); }
    body:has(.listing-bar) .chat-tab-pill { bottom: calc(64px + env(safe-area-inset-bottom)); }
    /* Bag, checkout and payment: the bubble floated over order totals and
       the confirmation buttons, and the page's own CTA is what matters. */
    .chat-tab-pill--checkout { display: none; }
    .chat-full-panel {
      top: auto; bottom: 0;
      transform: translateX(0) translateY(110%);
      border-radius: 18px 18px 0 0;
      width: 100%; max-height: 70vh;
      right: 0;
    }
    .chat-full-panel.is-open { transform: translateX(0) translateY(0); }
  }
</style>

{{-- Vertical tab trigger --}}
<button class="chat-tab-pill {{ request()->routeIs('cart.*', 'checkout.*', 'payment.*') ? 'chat-tab-pill--checkout' : '' }}" type="button" id="chat-tab-btn"
        aria-label="Open support chat" aria-expanded="false" aria-controls="chat-full-panel">
  <img src="{{ asset('assets/images/chat-avatar.svg') }}" alt="" width="26" height="26">
  <span class="online-dot"></span>
  <span class="tab-label">Chat</span>
</button>

{{-- Expandable chat panel --}}
<div class="chat-full-panel" id="chat-full-panel" role="dialog" aria-label="Chat with us" aria-modal="true" hidden>
  <div class="flex items-center justify-between gap-3 bg-[#232323] px-4 py-3.5 text-white">
    <div class="flex items-center gap-3">
      <img src="{{ asset('assets/images/chat-avatar.svg') }}" alt="" width="36" height="36" class="rounded-full object-cover shrink-0">
      <div>
        <strong class="block text-sm font-semibold">{{ $siteSettings['site_name'] ?? 'Estele' }} Style Expert</strong>
        <div class="mt-0.5 flex items-center gap-2 text-[12px] text-[#cbd5e1]">
          <span class="h-2 w-2 rounded-full bg-[#22c55e]"></span>
          <span>Online</span>
        </div>
      </div>
    </div>
    <button class="text-2xl leading-none text-white opacity-70 transition hover:opacity-100" type="button"
            id="chat-close-btn" aria-label="Close chat">&times;</button>
  </div>
  <div class="flex-1 overflow-y-auto">
    <div class="flex flex-col gap-3 bg-[#f7f5f6] p-4" data-chat-log>
      <p class="max-w-[85%] self-start rounded-[28px] border border-line bg-white px-4 py-3 text-[13px] leading-relaxed shadow-sm">Hey! <strong>How can I help you?</strong></p>
    </div>
    <div class="flex flex-col gap-2.5 bg-[#f7f5f6] px-4 pb-4 pt-2">
      <button class="rounded-full border border-[#dadada] bg-white px-4 py-3 text-[13px] text-heading transition hover:bg-[#fafafa]" type="button" data-chat-quick>Suggest something for me</button>
      <button class="rounded-full border border-[#dadada] bg-white px-4 py-3 text-[13px] text-heading transition hover:bg-[#fafafa]" type="button" data-chat-quick>Tell me about best seller</button>
    </div>
  </div>
  <form class="flex items-center gap-2 border-t border-line bg-white px-4 py-3" data-chat-form>
    <label class="sr-only-custom" for="chat-input">Message</label>
    <input class="w-full rounded-full border border-line-strong bg-[#f4f2f3] px-4 py-2.5 text-[13px] outline-none focus:border-accent"
           id="chat-input" type="text" placeholder="Talk to me in any language" autocomplete="off">
    <button class="grid h-[36px] w-[36px] shrink-0 place-items-center rounded-full bg-[#1f1f1f] text-white transition hover:bg-[#111111]" type="submit" aria-label="Send">
      <svg class="h-[16px] w-[16px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 2 11 13"/><path d="M22 2l-7 20-4-9-9-4z"/></svg>
    </button>
  </form>
</div>

{{-- Page-specific bar pinned to the bottom edge on phones: the product
     page's Add to Bag / Buy Now, the cart's Go To Checkout and the listing
     pages' Sort / Filter. On product pages this is the ONLY add-to-bag
     control below md, so it must always be rendered. --}}
@yield('sticky_bar')

{{--
  Back-to-top sits above the chat bubble.
  Mobile: chat bubble at 68px (48px tall) → clear at 128px; pages with a
  .buybar lift both (see app.css).
  Desktop: floating cart at bottom-22px (58px tall) → clear at 92px.
  pointer-events-none until it fades in (app.js), so the invisible button
  never swallows taps meant for whatever is underneath it.
--}}
<style>
  .back-to-top-btn { bottom: calc(128px + env(safe-area-inset-bottom)); right: 12px; }
  @media (min-width: 768px) { .back-to-top-btn { bottom: 92px; right: 22px; } }
</style>
<button class="back-to-top-btn pointer-events-none fixed z-[90] grid h-[42px] w-[42px] translate-y-2.5 place-items-center rounded-full bg-heading text-white opacity-0 transition-all hover:bg-accent"
        type="button" data-to-top aria-label="Back to top">
  <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
</button>


<script src="{{ asset('theme/app.js') }}?v={{ @filemtime(public_path('theme/app.js')) }}"></script>
@stack('scripts')

{{-- Admin-managed tracking scripts that need to run after page content (e.g.
     a GTM noscript fallback or a pixel that expects the DOM to be ready) —
     counterpart to 'tracking_head_scripts' above. --}}
@if(! empty($siteSettings['tracking_body_scripts']))
  {!! $siteSettings['tracking_body_scripts'] !!}
@endif
</body>
</html>
