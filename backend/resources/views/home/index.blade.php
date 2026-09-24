@extends('layouts.app')

@if($banners->isNotEmpty() && $banners->first()->hasMedia('image'))
  @section('og_image', $banners->first()->getFirstMediaUrl('image', 'desktop'))
@endif

@section('content')

  {{-- Organization JSON-LD (SEO checklist item) — name, logo, social links, all admin-editable via Settings. --}}
  <script type="application/ld+json">
    {!! json_encode(array_filter([
      '@context' => 'https://schema.org',
      '@type' => 'Organization',
      'name' => $siteSettings['site_name'] ?? 'Estele',
      'url' => route('home'),
      'logo' => $siteSettings['site_logo_url'] ?? null,
      'sameAs' => array_values(array_filter([
        $siteSettings['social_instagram'] ?? null,
        $siteSettings['social_facebook'] ?? null,
        $siteSettings['social_twitter'] ?? null,
        $siteSettings['social_youtube'] ?? null,
      ])),
      'contactPoint' => ($siteSettings['contact_phone'] ?? null) ? array_filter([
        '@type' => 'ContactPoint',
        'telephone' => $siteSettings['contact_phone'],
        'email' => $siteSettings['contact_email'] ?? null,
        'contactType' => 'customer service',
      ]) : null,
    ]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
  </script>

  {{-- WebSite + SearchAction JSON-LD (spec §4.1) — tells Google the site has
       an internal search box it can offer as a "Sitelinks Search Box" in
       results; {search_term_string} is the schema.org placeholder Google's
       own docs specify, substituted with the real query at click time. --}}
  <script type="application/ld+json">
    {!! json_encode([
      '@context' => 'https://schema.org',
      '@type' => 'WebSite',
      'name' => $siteSettings['site_name'] ?? 'Estele',
      'url' => route('home'),
      'potentialAction' => [
        '@type' => 'SearchAction',
        'target' => [
          '@type' => 'EntryPoint',
          'urlTemplate' => route('search').'?q={search_term_string}',
        ],
        'query-input' => 'required name=search_term_string',
      ],
    ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
  </script>

  @if($banners->isNotEmpty())
    <div class="mx-auto w-full">
      {{--
        Banner height reduced 40% from the original aspect ratios (mobile
        750/1000 -> 750/600, desktop 1800/700 -> 1800/420). Uses an inline
        style rather than a new Tailwind aspect-[...] utility class: this
        backend has no live Tailwind build of its own — public/theme/app.css
        is a static copy of the separate root project's compiled CSS (see
        tcongs-d2c-spec memory), so any brand-new arbitrary-value class
        written only in a backend Blade file compiles to nothing and the
        element would silently lose its aspect ratio entirely. The old class
        list also had a latent bug: two unprefixed aspect-[...] utilities of
        equal specificity with no breakpoint on the mobile one, so which
        ratio actually won depended on Tailwind's generation order rather
        than intent — sidestepped here since inline style always wins CSS
        cascade order regardless of stylesheet compile order.

        The wrapper has no padding of its own; the section carries the same
        px-3 md:px-4 inset as every block below as margin, plus rounded
        corners, so the banner sits as a card instead of running full-bleed.
      --}}
      {{-- Phones: the banner shows at its own aspect ratio, full width and
           never cropped — the first slide sits in flow and sizes the box,
           the rest stack over it (a fixed 50vh box used to leave the first
           slide's landscape art floating in grey space and crop every other
           slide's text). From md up the box has a fixed aspect ratio, so
           every slide is absolutely positioned inside it. --}}
      <style>
        @media (min-width: 768px) {
          .hero-banner-shortened { aspect-ratio: 1800 / 420 !important; }
          .hero-banner-shortened > .hero-slide:first-child { position: absolute; }
        }
      </style>
      <section class="hero-fade hero-banner-shortened skeleton relative min-h-[150px] overflow-hidden md:mx-4 md:mt-4 md:min-h-0 md:rounded-2xl" aria-label="Featured collections" data-carousel data-autoplay="5000" data-fade>
        @foreach($banners as $index => $banner)
          <div class="hero-slide {{ $index === 0 ? 'is-active' : '' }}" data-carousel-slide>
            <a class="block h-full" href="{{ $banner->link_url ?? '#' }}" aria-label="{{ $banner->title }}">
              @if($banner->hasMedia('image') || $banner->hasMedia('mobile_image'))
                @if($banner->hasMedia('image'))
                  <img class="hidden md:block h-full w-full object-cover" src="{{ $banner->getFirstMediaUrl('image', 'desktop') }}" alt="{{ $banner->image_alt_text ?: $banner->title }}" loading="{{ $index === 0 ? 'eager' : 'lazy' }}" fetchpriority="{{ $index === 0 ? 'high' : 'auto' }}">
                @endif
                @if($banner->getMobileImageUrl())
                  <img class="block w-full md:hidden {{ $index === 0 ? 'h-auto' : 'h-full object-cover' }}" src="{{ $banner->getMobileImageUrl() }}" alt="{{ $banner->image_alt_text ?: $banner->title }}" loading="{{ $index === 0 ? 'eager' : 'lazy' }}" fetchpriority="{{ $index === 0 ? 'high' : 'auto' }}">
                @endif
              @endif
            </a>
          </div>
        @endforeach
        {{-- Arrows from md up; phones swipe (see the hero carousel in app.js),
             where 24px arrows were below a usable tap size and sat on the art. --}}
        <button class="absolute left-5 top-1/2 z-[3] hidden h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-white/85 text-heading shadow-sm backdrop-blur-sm transition-colors hover:bg-white md:grid" type="button" data-hero-prev aria-label="Previous slide">
          <svg class="h-2.5 w-2.5 md:h-4 md:w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <button class="absolute right-5 top-1/2 z-[3] hidden h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-white/85 text-heading shadow-sm backdrop-blur-sm transition-colors hover:bg-white md:grid" type="button" data-hero-next aria-label="Next slide">
          <svg class="h-2.5 w-2.5 md:h-4 md:w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
        </button>
        {{-- Dots sit over the slide's lower edge rather than in a white strip
             below it, so the banner keeps its full-bleed edge. --}}
        <div class="absolute inset-x-0 bottom-2.5 z-[3] flex justify-center gap-2 md:bottom-4" data-hero-dots>
          @foreach($banners as $index => $banner)
            <button class="h-1.5 rounded-full transition-all duration-300 {{ $index === 0 ? 'w-6 bg-white' : 'w-1.5 bg-white/55' }}" type="button" data-hero-dot="{{ $index }}" aria-label="Go to slide {{ $index + 1 }}"></button>
          @endforeach
        </div>
      </section>
    </div>
  @endif

  @foreach($homepageBlocks as $block)
    @includeIf('home.blocks.'.str_replace('_', '-', $block->type), ['block' => $block, 'categories' => $categories])
  @endforeach

@endsection
