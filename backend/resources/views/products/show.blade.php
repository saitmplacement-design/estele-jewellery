@extends('layouts.app')

@section('meta_title', ($product->seoMeta?->title ?? $product->title) . ' | ' . ($siteSettings['site_name'] ?? 'Estele'))
@section('meta_description', $product->seoMeta?->description ?: ($product->description ?: $product->title))
@section('og_type', 'product')
@if($product->seoMeta?->og_image || $product->hasMedia('gallery'))
@section('og_image', $product->seoMeta?->og_image ?? $product->getFirstMediaUrl('gallery', 'detail'))
@endif

@section('sticky_bar')
  <div class="buybar md:hidden">
    {{-- The real wishlist toggle now that the duplicate heart beside the
    title is gone — it used to proxy its click through to that one. --}}
    <button class="grid h-[49px] w-11 shrink-0 place-items-center text-heading" type="button" aria-label="Add to wishlist"
      data-wishlist-toggle data-pdp-wishlist data-wishlist-key="{{ $product->title }}">
      <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3">
        <path
          d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21.2l7.7-7.7 1.1-1.1a5.5 5.5 0 0 0 0-7.8z" />
      </svg>
    </button>
    @if($product->stock_quantity > 0)
      <button class="btn-cta-outline h-[49px] flex-1 text-[18px]" type="submit" form="pdp-form" name="express" value="1"
        formaction="{{ route('checkout.express.start', $product) }}" data-express-submit>Buy Now</button>
      <button class="btn-cta h-[49px] flex-1 text-[18px]" type="submit" form="pdp-form">Add to Bag</button>
    @else
      <button class="btn-cta h-[49px] flex-1 text-[18px]" type="button" disabled>Out of Stock</button>
    @endif
  </div>
@endsection

@section('content')

  @php
    $primaryCategory = $product->categories->first();
    $discountPercent = $product->compare_at_price
      ? (int) round((($product->compare_at_price - $product->price) / $product->compare_at_price) * 100)
      : null;
    $galleryImages = $product->getMedia('gallery');
    $mainMedia = $galleryImages->first();
    $ratingAverage = $product->reviewsAverageRating();
    $ratingCount = $product->reviewsCount();
    $breadcrumbItems = array_filter([
      $primaryCategory ? ['label' => $primaryCategory->name, 'url' => route('categories.show', $primaryCategory)] : null,
      ['label' => $product->title],
    ]);
  @endphp

  <script type="application/ld+json">
      {!! json_encode(array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $product->title,
    'image' => $galleryImages->map(fn($media) => $media->getUrl('detail'))->all(),
    'description' => $product->description,
    'sku' => $product->sku,
    'brand' => ['@type' => 'Brand', 'name' => $siteSettings['site_name'] ?? 'Estele'],
    'offers' => [
      '@type' => 'Offer',
      'url' => route('products.show', $product),
      'priceCurrency' => 'INR',
      'price' => (string) $product->price,
      'availability' => $product->stock_quantity > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
    ],
    'aggregateRating' => $ratingCount > 0 ? [
      '@type' => 'AggregateRating',
      'ratingValue' => $ratingAverage,
      'reviewCount' => $ratingCount,
    ] : null,
    'review' => $reviews->isNotEmpty() ? $reviews->map(fn($review) => [
      '@type' => 'Review',
      'author' => ['@type' => 'Person', 'name' => $review->customer_name],
      'datePublished' => $review->displayDate()->toIso8601String(),
      'reviewBody' => $review->body,
      'reviewRating' => [
        '@type' => 'Rating',
        'ratingValue' => $review->rating,
        'bestRating' => 5,
        'worstRating' => 1,
      ],
    ])->all() : null,
  ]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
    </script>

  <x-breadcrumb-schema :items="$breadcrumbItems" />

  <nav class="mx-auto w-full max-w-wrapper px-4 flex flex-wrap items-center gap-1.5 py-2 text-[13px] text-muted border-b border-line" aria-label="Breadcrumb">
    <x-breadcrumb :items="$breadcrumbItems" />
  </nav>

  {{--
  Gallery layout: real Estele product pages run a vertical thumbnail strip
  beside the main image on desktop, not a grid below it (verified live via
  browser). Reproduced with plain scoped CSS rather than new Tailwind
  utility classes — see the note on padding above for why: this backend's
  public/theme/app.css is a static copy of a separate project's compiled
  CSS, so a fresh utility class written only here would render as nothing.
  Individual thumbnail buttons keep their original Tailwind classes
  (aspect-square/border-accent/etc.) since those are already proven
  compiled in this exact file.

  Same reasoning covers everything else new below (.pdp-image-wrap /
  .pdp-zoom-* / .pdp-title-row / .pdp-icon-*): plain scoped
  CSS/JS, not new Tailwind classes. These reproduce three things confirmed
  missing here vs. the real Estele PDP (checked live via browser) — a
  hover-to-zoom lens with a magnified side panel, an expand icon that opens
  the current image full-screen, and wishlist/share icons beside the title.
  --}}
  <style>
    .pdp-gallery {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .pdp-image-wrap {
      position: relative;
    }

    /* Swipeable slider — one image per scroll-snap slide, dots below. */
    .pdp-slider {
      display: flex;
      overflow-x: auto;
      scroll-snap-type: x mandatory;
      scrollbar-width: none;
      -ms-overflow-style: none;
    }

    .pdp-slider::-webkit-scrollbar {
      display: none;
    }

    .pdp-slide {
      flex: 0 0 100%;
      width: 100%;
      scroll-snap-align: center;
    }

    .pdp-dots {
      display: flex;
      justify-content: center;
      gap: 6px;
      padding-top: 10px;
    }

    .pdp-dot {
      height: 6px;
      width: 6px;
      padding: 0;
      border: 0;
      border-radius: 999px;
      background: var(--color-line-strong);
      cursor: pointer;
      transition: width .2s ease, background-color .2s ease;
    }

    .pdp-dot.is-active {
      width: 18px;
      background: var(--color-accent);
    }

    .pdp-icon-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      height: 34px;
      width: 34px;
      padding: 0;
      border-radius: 999px;
      border: 1px solid var(--color-line);
      background: transparent;
      color: var(--color-heading);
      cursor: pointer;
      transition: color .15s ease, border-color .15s ease;
    }

    .pdp-icon-btn:hover,
    .pdp-icon-btn.is-active {
      border-color: var(--color-accent);
      color: var(--color-accent);
    }

    .pdp-icon-btn svg {
      height: 16px;
      width: 16px;
    }

    .pdp-expand-btn {
      position: absolute;
      top: 10px;
      right: 10px;
      z-index: 5;
      background: var(--color-white);
      border: 0;
      box-shadow: 0 1px 4px rgba(0, 0, 0, .18);
    }

    /* Share sits in the image's top-right, stacked under the expand icon. */
    .pdp-share-float {
      position: absolute;
      top: 54px;
      right: 10px;
      z-index: 5;
      height: 30px;
      width: 30px;
      background: var(--color-white);
      border: 0;
      box-shadow: 0 1px 4px rgba(0, 0, 0, .18);
    }

    .pdp-share-float svg {
      height: 14px;
      width: 14px;
    }

    /* Hover-to-zoom lens + magnified side panel, matching estele.co's PDP gallery. */
    .pdp-zoom-lens {
      position: absolute;
      display: none;
      pointer-events: none;
      z-index: 4;
      border: 1px solid rgba(20, 20, 20, .45);
      background: rgba(255, 255, 255, .35);
    }

    .pdp-zoom-pane {
      position: absolute;
      display: none;
      top: 0;
      left: 100%;
      margin-left: 16px;
      width: 100%;
      height: 100%;
      z-index: 20;
      border-radius: 4px;
      background-color: var(--color-placeholder);
      background-repeat: no-repeat;
      box-shadow: 0 8px 30px rgba(0, 0, 0, .16);
    }

    @media (min-width: 1024px) {
      .pdp-main {
        cursor: zoom-in;
      }
    }

    .pdp-title-row {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 12px;
    }

    .pdp-title-row h1 {
      margin: 0;
    }

    .pdp-icon-group {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-shrink: 0;
    }

    .pdp-share-tip {
      position: absolute;
      bottom: calc(100% + 8px);
      left: 50%;
      transform: translateX(-50%);
      white-space: nowrap;
      background: var(--color-heading);
      color: var(--color-white);
      font-size: 11px;
      padding: 4px 8px;
      border-radius: 4px;
    }

      {
        {
        -- Sticky buy box: on mobile the qty/Add-to-Cart/Buy-It-Now group pins to the bottom of the viewport while the rest of the page scrolls (standard PDP pattern — otherwise the purchase actions scroll away under the description/accordion content). Desktop keeps the normal in-flow layout since there's room beside the gallery already. The chat bubble
   and back-to-top button (both fixed bottom-right, see layouts/app.blade.php) get pushed up on mobile only here so the new bar doesn't sit under them —
   inline/scoped-CSS per this app's no-live-Tailwind-build constraint,
   matching the pattern already used for back-to-top's own offset.
   --
      }
    }

    @media (max-width: 767px) {
      .pdp-image-wrap {
        margin-left: -16px;
        margin-right: -16px;
      }

      .pdp-main {
        padding: 0 !important;
        border-radius: 0;
      }

    }
  </style>

  {{--
  Wrapped in <article> (not just a plain <div>) so the site-wide wishlist heart
      click-handler in app.js — which walks up to the nearest article/li to find an
      <img> for its localStorage key — resolves to this product's own image here too,
      same as it does for product cards on listing pages.
      --}}
      <article class="mx-auto w-full max-w-wrapper px-4 grid grid-cols-1 gap-8 pb-4 md:grid-cols-2 md:gap-[46px] md:pb-8">

        {{--
        Swipeable slider instead of a thumbnail strip: each gallery image is a
        full-width scroll-snap slide with dots underneath, and tapping the
        current slide opens the swipeable full-screen viewer (app.js). This replaces
        the old vertical thumbnail rail per the client's reference.
        --}}
        <div class="pdp-gallery">
          <div class="pdp-image-wrap">
            <div class="pdp-slider" id="pdp-slider">
              @foreach($galleryImages as $index => $media)
                <div class="pdp-slide">
                  {{-- Image area reduced ~20% via inline padding — see product-card.blade.php for why this isn't a Tailwind
                  p-[...] class. --}}
                  <div class="pdp-main aspect-square overflow-hidden rounded bg-placeholder" style="padding: 5.3%"
                    @if($index === 0) id="pdp-zoom-frame" @endif>
                    <img class="h-full w-full object-cover" data-lightbox @if($index === 0) id="pdp-main-img" @endif
                      data-slide-full="{{ $media->getUrl('detail') }}" src="{{ $media->getUrl('detail') }}"
                      srcset="{{ $media->getUrl('mobile') }} 768w, {{ $media->getUrl('tablet') }} 1024w, {{ $media->getUrl('detail') }} 1600w"
                      sizes="(max-width: 768px) 100vw, 50vw" alt="{{ $product->title }} view {{ $index + 1 }}" width="1000"
                      height="1000" @if($index === 0) fetchpriority="high" @else loading="lazy" @endif>
                    @if($index === 0)
                    <div class="pdp-zoom-lens" id="pdp-zoom-lens"></div>@endif
                  </div>
                </div>
              @endforeach
            </div>

            @if($galleryImages->count() > 1)
              <div class="pdp-dots" id="pdp-dots" role="tablist" aria-label="Product images">
                @foreach($galleryImages as $index => $media)
                  <button class="pdp-dot{{ $index === 0 ? ' is-active' : '' }}" type="button" data-pdp-dot="{{ $index }}"
                    aria-label="Go to image {{ $index + 1 }}"></button>
                @endforeach
              </div>
            @endif

            @if($mainMedia)
              {{-- Share moved here from beside the title, per the client's reference. --}}
              <button class="pdp-icon-btn pdp-share-float" type="button" id="pdp-share-btn" aria-label="Share">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                  <circle cx="18" cy="5" r="2.4" />
                  <circle cx="6" cy="12" r="2.4" />
                  <circle cx="18" cy="19" r="2.4" />
                  <path d="M8.1 10.7l7.8-4.4M8.1 13.3l7.8 4.4" />
                </svg>
              </button>
              <button class="pdp-icon-btn pdp-expand-btn" type="button" id="pdp-expand-btn" aria-label="View full image">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                  <path d="M9 3H3v6M15 3h6v6M9 21H3v-6M15 21h6v-6" />
                </svg>
              </button>
              <div class="pdp-zoom-pane" id="pdp-zoom-pane"></div>
            @endif
          </div>
        </div>

        <div>
          {{-- Wishlist lives only in the sticky buy bar now; the duplicate heart
          and the share icon that sat here have moved (share to the image). --}}
          <div class="pdp-title-row mb-1.5">
            <h1 class="text-[20px] font-bold leading-tight text-black md:text-[30px] md:text-heading">
              {{ $product->title }}</h1>
          </div>
          @if($product->sku)
            <p class="mb-2.5 text-[12px] font-medium tracking-wider text-muted uppercase">SKU: {{ $product->sku }}</p>
          @endif

          {{-- Rating right under the name, like Amazon/Flipkart: a green
               "4.6★" pill + review count, jumping to the reviews section. --}}
          @if($ratingCount > 0)
            <a class="mb-3 inline-flex items-center gap-2 text-[13px]" href="#reviews">
              <span class="inline-flex items-center gap-0.5 rounded bg-[#1f9d55] px-1.5 py-0.5 text-[12px] font-bold leading-none text-white">{{ number_format($ratingAverage, 1) }}&#9733;</span>
              <x-review-stars :rating="$ratingAverage" size="text-[15px]" />
              <span class="font-medium text-accent-dark underline-offset-2 hover:underline">{{ number_format($ratingCount) }} {{ \Illuminate\Support\Str::plural('review', $ratingCount) }}</span>
            </a>
          @else
            <a class="mb-3 inline-flex items-center gap-2 text-[13px]" href="#reviews" data-review-open>
              <x-review-stars :rating="0" size="text-[15px]" />
              <span class="font-medium text-accent-dark underline underline-offset-2">Be the first to review</span>
            </a>
          @endif

          <span class="block text-[10px] font-bold leading-3 text-black">MRP</span>
          <div class="flex flex-wrap items-baseline gap-2.5">
            <span class="text-[24px] font-bold leading-8 text-black md:text-[30px] md:text-price">₹
              {{ number_format($product->price, 0) }}</span>
            @if($product->compare_at_price)
              <span class="text-[15px] font-bold text-muted line-through">₹
                {{ number_format($product->compare_at_price, 0) }}</span>
              <span class="text-[15px] font-bold text-accent-dark">{{ $discountPercent }}% off</span>
            @endif
          </div>
          <p class="mb-3 text-[10px] font-bold leading-3 text-[#777]">(Incl. of all taxes)</p>
          <div class="mb-5 flex flex-wrap gap-2.5">
            @if($product->stock_quantity > 0 && $product->stock_quantity <= 5)
              <span class="info-chip">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                  stroke-linecap="round">
                  <circle cx="13" cy="13" r="8" />
                  <path d="M13 9v4l2.5 2M2 9h4M1 13h4M3 17h3" />
                </svg>
                Hurry, Only <strong class="text-black/80">{{ $product->stock_quantity }}</strong> left
              </span>
            @endif
            <span class="info-chip">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                stroke-linejoin="round">
                <path d="M1 5h14v11H1zM15 9h4l4 4v3h-8z" />
                <circle cx="6" cy="18" r="2" />
                <circle cx="18" cy="18" r="2" />
              </svg>
              Free shipping available
            </span>
          </div>

          {{-- Trust badge row --}}
          <div class="mb-6 grid grid-cols-3 gap-2 rounded-xl bg-warmbeige/40 p-3.5 border border-line text-center">
            <div>
              <div class="mx-auto mb-1 grid h-8 w-8 place-items-center rounded-full bg-white text-accent shadow-sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                </svg>
              </div>
              <p class="text-[11px] font-semibold text-heading">Skin Friendly</p>
              <p class="text-[10px] text-muted">24K Gold Plated</p>
            </div>
            <div>
              <div class="mx-auto mb-1 grid h-8 w-8 place-items-center rounded-full bg-white text-accent shadow-sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 4v6h6" />
                  <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10" />
                </svg>
              </div>
              <p class="text-[11px] font-semibold text-heading">Easy Returns</p>
              <p class="text-[10px] text-muted">7-Day Guarantee</p>
            </div>
            <div>
              <div class="mx-auto mb-1 grid h-8 w-8 place-items-center rounded-full bg-white text-accent shadow-sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="1" y="3" width="15" height="13" rx="2" />
                  <polygon points="16 8 20 8 23 11 23 16 16 16 16 8" />
                </svg>
              </div>
              <p class="text-[11px] font-semibold text-heading">Free Shipping</p>
              <p class="text-[10px] text-muted">Orders > ₹1,499</p>
            </div>
          </div>

          @include('partials.offers-banner')

          <form class="mt-4" id="pdp-form" action="{{ route('cart.store', $product) }}" method="post" data-cart-form
            data-checkout-url="{{ route('checkout.index') }}">
            @csrf

            @if($product->variants->isNotEmpty())
              <div class="mb-5">
                <span class="mb-2 block text-[13px] font-medium uppercase tracking-[0.4px]">Select Option</span>
                <div class="flex flex-wrap gap-2.5">
                  @foreach($product->variants as $index => $variant)
                    <label
                      class="cursor-pointer rounded-md border-2 border-line-strong px-4 py-2 text-[13px] font-medium transition-colors has-[:checked]:border-accent has-[:checked]:bg-accent/5 has-[:checked]:text-accent text-heading hover:border-accent {{ $variant->stock_quantity <= 0 ? 'opacity-40' : '' }}">
                      <input class="sr-only" type="radio" name="product_variant_id" value="{{ $variant->id }}" {{ $index === 0 ? 'checked' : '' }} {{ $variant->stock_quantity <= 0 ? 'disabled' : '' }}>
                      {{ collect($variant->attributes ?? [])->map(fn($v, $k) => "{$k}: {$v}")->implode(', ') ?: $variant->sku }}
                    </label>
                  @endforeach
                </div>
              </div>
            @endif

            <div class="mb-4 flex items-center gap-3.5">
              <span class="text-[14px] font-bold text-heading">Quantity</span>
              <div class="inline-flex h-10 items-center rounded-md border border-line-strong bg-white px-1" data-qty>
                <button class="grid h-9 w-9 place-items-center rounded text-[18px] text-heading" type="button"
                  data-qty-minus aria-label="Decrease quantity">&minus;</button>
                <input class="w-10 border-0 text-center font-bold text-heading outline-none" type="number" name="quantity"
                  value="1" min="1" aria-label="Quantity">
                <button class="grid h-9 w-9 place-items-center rounded text-[18px] text-heading" type="button"
                  data-qty-plus aria-label="Increase quantity">+</button>
              </div>
            </div>
            <div class="mb-3 hidden gap-3 md:flex">
              <button class="btn-cta-outline flex-1" type="submit" name="express" value="1"
                formaction="{{ route('checkout.express.start', $product) }}" data-express-submit {{ $product->stock_quantity <= 0 ? 'disabled' : '' }}>Buy Now</button>
              <button class="btn-cta flex-1" type="submit" {{ $product->stock_quantity <= 0 ? 'disabled' : '' }}>{{ $product->stock_quantity > 0 ? 'Add to Bag' : 'Out of Stock' }}</button>
            </div>
            <p class="text-center text-[11px] text-muted">Fast &amp; secure · UPI, cards, net banking, COD
            </p>
          </form>

          <div class="mt-5 border-t border-line md:mt-7">
            @if($product->description)
              <details class="group border-b border-line" open>
                <summary
                  class="flex items-center justify-between py-4 text-[13px] font-semibold uppercase tracking-[0.4px] text-heading cursor-pointer">
                  <span>Description</span>
                  <span class="text-[16px] text-accent transition-transform group-open:rotate-180">&minus;</span>
                </summary>
                <div class="pb-4 text-[13.5px] leading-[1.8] text-muted">
                  <p>{{ $product->description }}</p>
                </div>
              </details>
            @endif
            <details class="group border-b border-line">
              <summary
                class="flex items-center justify-between py-4 text-[13px] font-semibold uppercase tracking-[0.4px] text-heading cursor-pointer">
                <span>Shipping &amp; 7-Day Returns</span>
                <span class="text-[16px] text-accent transition-transform group-open:rotate-180">+</span>
              </summary>
              <div class="pb-4 text-[13.5px] leading-[1.8] text-muted">
                <p>Free shipping on all prepaid orders across India. Orders are dispatched within 24-48 hours. Returns and
                  exchanges accepted within 7 days of delivery, provided the product is unused and in original packaging.
                </p>
              </div>
            </details>
            <details class="group border-b border-line">
              <summary
                class="flex items-center justify-between py-4 text-[13px] font-semibold uppercase tracking-[0.4px] text-heading cursor-pointer">
                <span>Manufacturing Details</span>
                <span class="text-[16px] text-accent transition-transform group-open:rotate-180">+</span>
              </summary>
              <div class="pb-4 text-[13.5px] leading-[1.8] text-muted">
                <p>Adorn yourself with the allure of anti-tarnish jewelry, exuding beauty and durability.
                  @if($product->sku) SKU: {{ $product->sku }}. @endif Every piece is quality-checked before dispatch.</p>
              </div>
            </details>
            <details class="group border-b border-line">
              <summary
                class="flex items-center justify-between py-4 text-[13px] font-semibold uppercase tracking-[0.4px] text-heading cursor-pointer">
                <span>Jewellery Care &amp; Maintenance</span>
                <span class="text-[16px] text-accent transition-transform group-open:rotate-180">+</span>
              </summary>
              <ul class="pb-4 space-y-2 text-[13px] text-muted">
                <li
                  class="relative pl-5 before:absolute before:left-0 before:top-[7px] before:h-2 before:w-2 before:rounded-full before:bg-accent">
                  Keep jewellery away from water &amp; humidity</li>
                <li
                  class="relative pl-5 before:absolute before:left-0 before:top-[7px] before:h-2 before:w-2 before:rounded-full before:bg-accent">
                  Remove jewellery before sleeping or physical activities</li>
                <li
                  class="relative pl-5 before:absolute before:left-0 before:top-[7px] before:h-2 before:w-2 before:rounded-full before:bg-accent">
                  Avoid direct contact with perfume, body lotions or chemicals</li>
                <li
                  class="relative pl-5 before:absolute before:left-0 before:top-[7px] before:h-2 before:w-2 before:rounded-full before:bg-accent">
                  Store separately in an air-tight jewellery box</li>
              </ul>
            </details>
          </div>
        </div>
      </article>

      @if($relatedProducts->isNotEmpty())
        <section class="py-4 md:py-8 bg-warmbeige/30 border-t border-line">
          <div class="mx-auto w-full max-w-wrapper px-4 md:px-8">
            <x-section-header title="You May Also Like" />
            <div
              class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-4 md:grid-cols-4 md:gap-5 lg:gap-6 xl:grid-cols-5 xl:gap-7 2xl:grid-cols-6">
              @foreach($relatedProducts as $related)
                <x-product-card :product="$related" />
              @endforeach
            </div>
          </div>
        </section>
      @endif

      {{--
      "Our Promise to You" trust strip. Uses a scoped grid (not the
      sm:grid-cols-3/md:grid-cols-5 Tailwind utilities) because in this
      backend's static-copy theme CSS (see the pdp-gallery note above),
      .sm\:grid-cols-3 happens to be emitted after .md\:grid-cols-5 in
      source order — so at desktop widths the sm: rule was winning the
      cascade and the grid never reached 5 equal columns, leaving an
      unbalanced 3+2 layout. Plain scoped CSS sidesteps that ordering
      landmine entirely.
      --}}
      <style>
        .promise-grid {
          display: grid;
          grid-template-columns: repeat(2, minmax(0, 1fr));
          gap: 16px;
        }

        @media (min-width: 640px) {
          .promise-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
          }
        }

        @media (min-width: 768px) {
          .promise-grid {
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 24px;
          }
        }

        /* Odd card out on the 2-column phone grid spans the row instead of
           sitting alone in a half-width cell. */
        @media (max-width: 639px) {
          .promise-grid > :last-child:nth-child(odd) {
            grid-column: 1 / -1;
          }
        }

        .promise-card-icon {
          margin: 0 auto 10px;
          color: var(--color-accent);
        }
      </style>
      <section class="py-4 md:py-8 bg-ivory border-t border-line">
        <div class="mx-auto w-full max-w-wrapper px-4 md:px-8">
          <x-section-header title="Our Promise to You" />
          <div class="promise-grid">
            @foreach([
                ['title' => '24K Gold Plated', 'subtitle' => 'Precious long-lasting shine', 'icon' => 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z'],
                ['title' => 'Skin Friendly', 'subtitle' => 'Nickel & lead free formula', 'icon' => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z'],
                ['title' => '35+ Years Legacy', 'subtitle' => 'Trusted by 5M+ happy women', 'icon' => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z'],
                ['title' => '7-Day Easy Returns', 'subtitle' => '100% exchange guarantee', 'icon' => 'M1 4v6h6M3.51 15a9 9 0 1 0 2.13-9.36L1 10'],
                ['title' => 'Free Shipping', 'subtitle' => 'Express Pan-India delivery', 'icon' => 'M1 3h15v13H1zM16 8h4l3 3v5h-7z'],
              ] as $promise)
              <div class="rounded-xl border border-line bg-white p-4 text-center shadow-sm">
                <svg class="promise-card-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                  stroke-width="1.6">
                  <path d="{{ $promise['icon'] }}" />
                </svg>
                <h3 class="font-serif text-[13px] font-semibold text-heading">{{ $promise['title'] }}</h3>
                <p class="mt-1 text-[11px] text-muted">{{ $promise['subtitle'] }}</p>
              </div>
            @endforeach
          </div>
        </div>
      </section>

      {{-- Ratings & reviews — Amazon/Flipkart-style summary (average, star
           breakdown bars that double as filters), customer photos, the review
           list and an inline "write a review" form with a tap-to-rate star
           picker. Always shown, so the first shopper can review too. --}}
      @php
        $reviewTotal = (int) $ratingBreakdown->sum();
        $reviewFormOpen = $errors->hasAny(['customer_name', 'customer_email', 'rating', 'title', 'body', 'photos', 'photos.*']);
        $ratingWords = [1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Very good', 5 => 'Excellent'];
      @endphp
      <section class="scroll-mt-20 border-t border-line bg-white py-5 md:py-12" id="reviews">
        <div class="mx-auto w-full max-w-[980px] px-4">
          <div class="mb-3 flex items-end justify-between gap-3 md:mb-6">
            <div>
              <p class="section-head__eyebrow">What customers say</p>
              <h2 class="text-[18px] font-bold leading-tight text-heading md:text-[26px]">Ratings &amp; Reviews</h2>
            </div>
            @if($reviewTotal)
              <button class="btn-cta-outline h-9 w-auto shrink-0 px-3.5 text-[12.5px] md:hidden" type="button" data-review-open>Write a review</button>
            @endif
          </div>

          @if(session('review_success'))
            <p class="mb-4 flex items-center gap-2 rounded-xl border border-[#b7e4c7] bg-[#ecfbf1] px-4 py-3 text-[13px] font-medium text-[#1a7d3f]">
              <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
              {{ session('review_success') }}
            </p>
          @endif

          <div class="grid gap-4 md:gap-10 {{ $reviewTotal ? 'md:grid-cols-[320px_1fr]' : '' }}">
            {{-- Summary — only once customers have reviewed; a product with no
                 reviews gets a single compact prompt instead of 0.0 and empty bars. --}}
            @if($reviewTotal)
            {{-- Phones: one compact rating line; the full summary card is md+ only. --}}
            {{-- Phones: one compact rating line that opens/closes the full
                 breakdown below it; md+ always shows the summary card. --}}
            <button class="-mt-1 flex w-full items-center gap-2 text-left text-[12.5px] text-muted md:hidden" type="button" data-review-summary-toggle aria-expanded="false">
              <span class="inline-flex items-center gap-0.5 rounded bg-[#1f9d55] px-1.5 py-0.5 text-[12px] font-bold leading-none text-white">{{ number_format($ratingAverage, 1) }}&#9733;</span>
              <x-review-stars :rating="$ratingAverage" size="text-[14px]" />
              {{ number_format($reviewTotal) }} {{ \Illuminate\Support\Str::plural('review', $reviewTotal) }}
              <svg class="ml-auto h-4 w-4 shrink-0 text-heading transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-review-summary-chevron><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div class="{{ $reviewRating ? '' : 'hidden' }} md:sticky md:top-28 md:block md:self-start" data-review-summary>
              <div class="rounded-2xl border border-line bg-gradient-to-br from-pinksoft to-white p-3 md:p-5">
                <div class="grid grid-cols-[auto_1fr] items-center gap-4 md:block">
                <div class="flex flex-col items-center gap-1 md:flex-row md:gap-4">
                  <div class="text-center">
                    <p class="text-[34px] font-bold leading-none text-heading md:text-[46px]">{{ $reviewTotal ? number_format($ratingAverage, 1) : '0.0' }}</p>
                    <p class="mt-1 text-[11px] uppercase tracking-[0.1em] text-muted">out of 5</p>
                  </div>
                  <div class="min-w-0 text-center md:text-left">
                    <x-review-stars :rating="$ratingAverage ?? 0" size="text-[14px] md:text-[20px]" />
                    <p class="mt-0.5 whitespace-nowrap text-[11px] text-muted md:mt-1 md:text-[12.5px]">
                      @if($reviewTotal)
                        {{ number_format($reviewTotal) }} {{ \Illuminate\Support\Str::plural('review', $reviewTotal) }}
                      @else
                        No reviews yet
                      @endif
                    </p>
                  </div>
                </div>

                {{-- Breakdown bars; each row filters the list to that rating. --}}
                <ul class="space-y-0.5 md:mt-4 md:space-y-1.5">
                  @for($star = 5; $star >= 1; $star--)
                    @php
                      $count = (int) ($ratingBreakdown[$star] ?? 0);
                      $pct = $reviewTotal ? round($count / $reviewTotal * 100) : 0;
                      $isActive = $reviewRating === $star;
                    @endphp
                    <li>
                      <a class="group flex items-center gap-2 rounded-md px-1 py-px text-[11.5px] md:gap-2.5 md:py-0.5 md:text-[12.5px] transition-colors {{ $isActive ? 'bg-white shadow-sm' : 'hover:bg-white/70' }} {{ $count ? '' : 'pointer-events-none opacity-60' }}"
                         href="{{ $isActive ? request()->fullUrlWithQuery(['review_rating' => null, 'reviews_page' => null]) : request()->fullUrlWithQuery(['review_rating' => $star, 'reviews_page' => null]) }}#reviews">
                        <span class="w-6 shrink-0 font-semibold text-heading md:w-7">{{ $star }}&#9733;</span>
                        <span class="relative h-1.5 flex-1 md:h-2 overflow-hidden rounded-full bg-line">
                          <span class="absolute inset-y-0 left-0 rounded-full {{ $star >= 3 ? 'bg-[#1f9d55]' : ($star === 2 ? 'bg-[#f0a020]' : 'bg-[#e0483e]') }}" style="width: {{ $pct }}%"></span>
                        </span>
                        <span class="w-6 shrink-0 text-right text-muted md:w-8">{{ $count }}</span>
                      </a>
                    </li>
                  @endfor
                </ul>
                </div>

                <button class="btn-cta mt-4 hidden h-11 w-full text-[14px] md:inline-flex" type="button" data-review-open>
                  <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                  Write a review
                </button>
                <p class="mt-2 hidden text-center text-[11.5px] text-muted md:block">Share your experience to help other shoppers</p>
              </div>
            </div>
            @else
              <div class="flex items-center justify-between gap-3 rounded-2xl border border-line bg-paper px-4 py-3 md:col-span-2">
                <p class="text-[13px] text-muted">No reviews yet. Be the first to share your experience.</p>
                <button class="btn-cta h-9 w-auto shrink-0 px-4 text-[13px]" type="button" data-review-open>Write a review</button>
              </div>
            @endif

            <div class="min-w-0">
              {{-- Write-a-review panel --}}
              {{-- Phones: the form opens as a bottom sheet over the page, so the
                   reviews section itself never grows. md+: an inline panel. --}}
              <div class="fixed inset-0 z-[205] bg-black/45 md:hidden {{ $reviewFormOpen ? '' : 'hidden' }}" data-review-backdrop></div>
              <div class="mb-5 rounded-2xl border border-line bg-paper p-4 max-md:fixed max-md:inset-x-0 max-md:bottom-0 max-md:z-[210] max-md:mb-0 max-md:max-h-[88vh] max-md:overflow-y-auto max-md:rounded-b-none max-md:pb-[calc(16px+env(safe-area-inset-bottom))] md:p-5 {{ $reviewFormOpen ? '' : 'hidden' }}" data-review-form-panel>
                <div class="mb-3 flex items-center justify-between">
                  <h3 class="text-[16px] font-bold text-heading">Write a review</h3>
                  <button class="grid h-8 w-8 place-items-center rounded-full text-heading hover:bg-white" type="button" data-review-close aria-label="Close">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 5l14 14M19 5L5 19"/></svg>
                  </button>
                </div>
                <form action="{{ route('products.reviews.store', $product) }}" method="post" enctype="multipart/form-data">
                  @csrf
                  <input class="hidden" type="text" name="website" tabindex="-1" autocomplete="off">

                  <fieldset class="mb-4">
                    <legend class="mb-1.5 text-[13px] font-semibold text-heading">Your rating <span class="text-salebadge">*</span></legend>
                    <div class="flex items-center gap-3">
                      <div class="star-input" data-star-input>
                        @for($i = 5; $i >= 1; $i--)
                          <input class="sr-only-custom" type="radio" id="rate-{{ $i }}" name="rating" value="{{ $i }}" @checked(old('rating') == $i) required>
                          <label for="rate-{{ $i }}" title="{{ $ratingWords[$i] }}" aria-label="{{ $i }} star{{ $i === 1 ? '' : 's' }}">&#9733;</label>
                        @endfor
                      </div>
                      <span class="text-[13px] font-medium text-muted" data-star-word>{{ old('rating') ? $ratingWords[(int) old('rating')] : 'Tap to rate' }}</span>
                    </div>
                    @error('rating') <p class="mt-1 text-[12px] text-salebadge">{{ $message }}</p> @enderror
                  </fieldset>

                  <div class="mb-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                      <label class="mb-1 block text-[13px] font-semibold text-heading" for="customer_name">Name <span class="text-salebadge">*</span></label>
                      <input class="w-full rounded-lg border border-line-strong bg-white px-3.5 py-2.5 text-[14px] outline-none focus:border-heading" id="customer_name" name="customer_name" type="text" value="{{ old('customer_name', auth()->user()?->name) }}" required maxlength="100">
                      @error('customer_name') <p class="mt-1 text-[12px] text-salebadge">{{ $message }}</p> @enderror
                    </div>
                    @guest
                      <div>
                        <label class="mb-1 block text-[13px] font-semibold text-heading" for="customer_email">Email <span class="text-salebadge">*</span></label>
                        <input class="w-full rounded-lg border border-line-strong bg-white px-3.5 py-2.5 text-[14px] outline-none focus:border-heading" id="customer_email" name="customer_email" type="email" value="{{ old('customer_email') }}" required>
                        <p class="mt-1 text-[11px] text-muted">Never shown publicly.</p>
                        @error('customer_email') <p class="mt-1 text-[12px] text-salebadge">{{ $message }}</p> @enderror
                      </div>
                    @endguest
                  </div>

                  <div class="mb-3">
                    <label class="mb-1 block text-[13px] font-semibold text-heading" for="title">Review title</label>
                    <input class="w-full rounded-lg border border-line-strong bg-white px-3.5 py-2.5 text-[14px] outline-none focus:border-heading" id="title" name="title" type="text" value="{{ old('title') }}" maxlength="150" placeholder="Sum it up in a few words">
                    @error('title') <p class="mt-1 text-[12px] text-salebadge">{{ $message }}</p> @enderror
                  </div>

                  <div class="mb-3">
                    <label class="mb-1 block text-[13px] font-semibold text-heading" for="body">Your review <span class="text-salebadge">*</span></label>
                    <textarea class="w-full rounded-lg border border-line-strong bg-white px-3.5 py-2.5 text-[14px] outline-none focus:border-heading" id="body" name="body" rows="4" required maxlength="3000" placeholder="How does it look, feel and fit? Would you recommend it?">{{ old('body') }}</textarea>
                    @error('body') <p class="mt-1 text-[12px] text-salebadge">{{ $message }}</p> @enderror
                  </div>

                  <div class="mb-4">
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-dashed border-line-strong bg-white px-3.5 py-3 text-[13px] text-muted hover:border-heading" for="photos">
                      <svg class="h-6 w-6 shrink-0 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><path d="M3 7h3l2-3h8l2 3h3v13H3z"/><circle cx="12" cy="13" r="4"/></svg>
                      <span data-photo-label>Add photos <span class="text-[11.5px]">(optional, up to 3)</span></span>
                    </label>
                    <input class="sr-only-custom" id="photos" name="photos[]" type="file" accept="image/*" multiple data-photo-input>
                    @error('photos') <p class="mt-1 text-[12px] text-salebadge">{{ $message }}</p> @enderror
                    @error('photos.*') <p class="mt-1 text-[12px] text-salebadge">{{ $message }}</p> @enderror
                  </div>

                  <button class="btn-cta h-11 w-full text-[14px] sm:w-auto sm:px-10" type="submit">Submit review</button>
                  <p class="mt-2 text-[11.5px] text-muted">Your review appears on this product as soon as you submit it.</p>
                </form>
              </div>

              {{-- Customer photos --}}
              @if($reviewPhotos->isNotEmpty())
                <div class="mb-3 md:mb-5">
                  <p class="mb-2 text-[13px] font-semibold text-heading">Customer photos</p>
                  <div class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 md:mx-0 md:flex-wrap md:px-0">
                    @foreach($reviewPhotos as $photo)
                      <img class="h-16 w-16 shrink-0 md:h-20 md:w-20 rounded-lg object-cover" src="{{ $photo->getUrl('thumb') }}" alt="Customer photo" loading="lazy" width="80" height="80">
                    @endforeach
                  </div>
                </div>
              @endif

              @if($reviewRating)
                <p class="mb-3 flex flex-wrap items-center gap-2 text-[13px] text-muted">
                  Showing {{ $reviewRating }}&#9733; reviews
                  <a class="font-semibold text-accent-dark underline" href="{{ request()->fullUrlWithQuery(['review_rating' => null, 'reviews_page' => null]) }}#reviews">Show all</a>
                </p>
              @endif

              @if($reviews->isNotEmpty())
                {{-- Phones: one swipeable row of review cards (Flipkart-style
                     "top reviews") instead of a tall stack; md+: a list. --}}
                <ul class="no-scrollbar -mx-4 flex snap-x snap-mandatory scroll-px-4 gap-2.5 overflow-x-auto px-4 pb-1 md:mx-0 md:block md:space-y-3 md:overflow-visible md:px-0 md:pb-0" data-review-list>
                  @foreach($reviews as $review)
                    <li class="w-[84%] shrink-0 snap-start rounded-xl border border-line bg-white p-3 md:w-auto md:rounded-2xl md:p-4">
                      <div class="flex items-start gap-2.5 md:gap-3">
                        <span class="grid h-8 w-8 shrink-0 md:h-10 md:w-10 place-items-center rounded-full bg-pinksoft text-[15px] font-bold uppercase text-accent-dark" aria-hidden="true">{{ \Illuminate\Support\Str::substr(trim($review->customer_name), 0, 1) }}</span>
                        <div class="min-w-0 flex-1">
                          <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <p class="text-[14px] font-semibold text-heading">{{ $review->customer_name }}</p>
                            @if($review->is_verified_purchase)
                              <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#1a7d3f]">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 4 5v6c0 5.2 3.4 9.9 8 11 4.6-1.1 8-5.8 8-11V5l-8-3zm-1.2 14.2-3.5-3.5 1.4-1.4 2.1 2.1 4.9-4.9 1.4 1.4-6.3 6.3z"/></svg>
                                Verified Purchase
                              </span>
                            @endif
                          </div>
                          <div class="mt-1 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-[11.5px] font-bold leading-none text-white {{ $review->rating >= 3 ? 'bg-[#1f9d55]' : ($review->rating === 2 ? 'bg-[#f0a020]' : 'bg-[#e0483e]') }}">{{ $review->rating }}&#9733;</span>
                            <span class="text-[11.5px] text-muted">{{ $review->displayDate()->format('d M Y') }}</span>
                          </div>
                        </div>
                      </div>
                      @if($review->title)
                        <p class="mt-2 text-[13.5px] font-semibold text-heading md:mt-3 md:text-[14px]">{{ $review->title }}</p>
                      @endif
                      <p class="mt-1 line-clamp-2 text-[13px] leading-[1.55] text-[#4a4a4a] md:mt-1.5 md:line-clamp-none md:text-[13.5px]" data-review-body>{{ $review->body }}</p>
                      <button class="mt-1 hidden text-[12px] font-semibold text-accent-dark" type="button" data-review-more>Read more</button>
                      @if($review->hasMedia('photos'))
                        <div class="mt-2 flex flex-wrap gap-2 md:mt-3">
                          @foreach($review->getMedia('photos') as $photo)
                            <img class="h-16 w-16 rounded-lg object-cover md:h-20 md:w-20" src="{{ $photo->getUrl('thumb') }}"
                              alt="Photo from {{ $review->customer_name }}'s review" loading="lazy" width="80" height="80">
                          @endforeach
                        </div>
                      @endif
                    </li>
                  @endforeach
                </ul>

                @if($reviews->count() > 1)
                  <p class="mt-2 text-center text-[11.5px] text-muted md:hidden">Swipe to see more reviews &rarr;</p>
                @endif
                @if($reviews->hasPages())
                  <div class="mt-4 md:mt-5">{{ $reviews->links() }}</div>
                @endif
              @elseif($reviewRating)
                <div class="rounded-2xl border border-dashed border-line-strong bg-paper px-5 py-5 text-center md:py-8">
                  <p class="text-[26px] tracking-[0.15em] text-line-strong">&#9733;&#9733;&#9733;&#9733;&#9733;</p>
                  <p class="mt-2 text-[15px] font-semibold text-heading">{{ $reviewRating ? 'No '.$reviewRating.'-star reviews yet' : 'No reviews yet' }}</p>
                  <p class="mt-1 text-[13px] text-muted">Be the first to share how this piece looks and feels.</p>
                  <button class="btn-cta-outline mx-auto mt-4 h-10 w-auto px-6 text-[13px]" type="button" data-review-open>Write the first review</button>
                </div>
              @endif
            </div>
          </div>
        </div>
      </section>

      @push('scripts')
        <script>
          (function () {
            var panel = document.querySelector('[data-review-form-panel]');
            if (!panel) return;
            var backdrop = document.querySelector('[data-review-backdrop]');
            var isPhone = function () { return window.matchMedia('(max-width: 767px)').matches; };
            function closePanel() { panel.classList.add('hidden'); if (backdrop) backdrop.classList.add('hidden'); }
            if (backdrop) backdrop.addEventListener('click', closePanel);
            document.querySelectorAll('[data-review-open]').forEach(function (btn) {
              btn.addEventListener('click', function (e) {
                e.preventDefault();
                panel.classList.remove('hidden');
                if (isPhone()) { if (backdrop) backdrop.classList.remove('hidden'); return; }
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var first = panel.querySelector('.star-input label');
                if (first) setTimeout(function () { first.focus && first.focus(); }, 300);
              });
            });
            var close = panel.querySelector('[data-review-close]');
            if (close) close.addEventListener('click', closePanel);

            var words = @json($ratingWords);
            var wordEl = panel.querySelector('[data-star-word]');
            panel.querySelectorAll('[data-star-input] input').forEach(function (input) {
              input.addEventListener('change', function () { if (wordEl) wordEl.textContent = words[input.value]; });
            });

            var photoInput = panel.querySelector('[data-photo-input]');
            var photoLabel = panel.querySelector('[data-photo-label]');
            if (photoInput && photoLabel) photoInput.addEventListener('change', function () {
              var n = photoInput.files.length;
              if (n > 3) { alert('Please choose up to 3 photos.'); photoInput.value = ''; n = 0; }
              photoLabel.textContent = n ? n + ' photo' + (n === 1 ? '' : 's') + ' selected' : 'Add photos (optional, up to 3)';
            });

            // Phones: the rating line opens/closes the breakdown card.
            var sumToggle = document.querySelector('[data-review-summary-toggle]');
            var summary = document.querySelector('[data-review-summary]');
            if (sumToggle && summary) {
              var chevron = sumToggle.querySelector('[data-review-summary-chevron]');
              var sync = function () {
                var open = !summary.classList.contains('hidden');
                sumToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (chevron) chevron.classList.toggle('rotate-180', open);
              };
              sumToggle.addEventListener('click', function () { summary.classList.toggle('hidden'); sync(); });
              sync();
            }

            // "Read more" only on reviews actually cut off by the 3-line clamp.
            document.querySelectorAll('[data-review-body]').forEach(function (body) {
              var more = body.nextElementSibling;
              if (!more || !more.hasAttribute('data-review-more')) return;
              if (body.scrollHeight > body.clientHeight + 2) more.classList.remove('hidden');
              more.addEventListener('click', function () {
                var open = body.classList.toggle('line-clamp-2');
                more.textContent = open ? 'Read more' : 'Show less';
              });
            });

          })();
        </script>
      @endpush
@endsection

    @push('scripts')
      <script>
        (function () {
          // Hover-to-zoom: a lens follows the cursor over the main image, and a
          // magnified crop renders in a side panel — same interaction as estele.co's
          // PDP gallery. Desktop-only (min-width 1024px + hover-capable pointer);
          // the CSS media query above is a second guard in case JS resolves this
          // before layout settles.
          var frame = document.getElementById('pdp-zoom-frame');
          var mainImg = document.getElementById('pdp-main-img');
          var lens = document.getElementById('pdp-zoom-lens');
          var pane = document.getElementById('pdp-zoom-pane');
          var canZoom = frame && mainImg && lens && pane;
          var hoverCapable = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
          var ZOOM = 2.4;

          // Slider: track which slide is centred so the dots, the lightbox and
          // the zoom pane all act on the image the shopper is actually looking
          // at rather than always on the first one.
          var slider = document.getElementById('pdp-slider');
          var dots = [].slice.call(document.querySelectorAll('[data-pdp-dot]'));
          var slides = slider ? [].slice.call(slider.querySelectorAll('.pdp-slide img')) : [];
          var activeIndex = 0;

          function currentImage() {
            return slides[activeIndex] || mainImg;
          }

          if (slider && slides.length) {
            slider.addEventListener('scroll', function () {
              var index = Math.round(slider.scrollLeft / slider.clientWidth);
              if (index === activeIndex || !slides[index]) return;
              activeIndex = index;
              dots.forEach(function (dot, i) { dot.classList.toggle('is-active', i === index); });
            }, { passive: true });

            dots.forEach(function (dot, i) {
              dot.addEventListener('click', function () {
                slider.scrollTo({ left: slider.clientWidth * i, behavior: 'smooth' });
              });
            });
          }

          function syncPaneImage() {
            pane.style.backgroundImage = 'url("' + (mainImg.currentSrc || mainImg.src) + '")';
          }

          if (canZoom) {
            syncPaneImage();
            mainImg.addEventListener('load', syncPaneImage);

            frame.addEventListener('mousemove', function (e) {
              if (!hoverCapable || window.innerWidth < 1024) return;
              var rect = frame.getBoundingClientRect();
              var x = e.clientX - rect.left;
              var y = e.clientY - rect.top;
              if (x < 0 || y < 0 || x > rect.width || y > rect.height) return;

              var lensW = rect.width / ZOOM;
              var lensH = rect.height / ZOOM;
              var lx = Math.min(Math.max(x - lensW / 2, 0), rect.width - lensW);
              var ly = Math.min(Math.max(y - lensH / 2, 0), rect.height - lensH);

              lens.style.width = lensW + 'px';
              lens.style.height = lensH + 'px';
              lens.style.left = lx + 'px';
              lens.style.top = ly + 'px';
              lens.style.display = 'block';
              pane.style.display = 'block';

              var bgX = rect.width - lensW > 0 ? (lx / (rect.width - lensW)) * 100 : 0;
              var bgY = rect.height - lensH > 0 ? (ly / (rect.height - lensH)) * 100 : 0;
              pane.style.backgroundSize = (ZOOM * 100) + '%';
              pane.style.backgroundPosition = bgX + '% ' + bgY + '%';
            });

            frame.addEventListener('mouseleave', function () {
              lens.style.display = 'none';
              pane.style.display = 'none';
            });
          }

          // Expand icon -> the site-wide swipeable viewer (app.js), opened on
          // the image currently showing in the slider.
          var expandBtn = document.getElementById('pdp-expand-btn');
          if (expandBtn) {
            expandBtn.addEventListener('click', function () {
              var img = currentImage();
              if (img && window.esteleLightbox) window.esteleLightbox.openFrom(img);
            });
          }

          // Share icon -> native share sheet where available, else copy the link.
          var shareBtn = document.getElementById('pdp-share-btn');
          if (shareBtn) {
            shareBtn.addEventListener('click', function () {
              var shareData = { title: document.title, url: window.location.href };
              if (navigator.share) {
                navigator.share(shareData).catch(function () { });
                return;
              }
              if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(shareData.url).then(function () {
                  var tip = document.createElement('span');
                  tip.textContent = 'Link copied';
                  tip.className = 'pdp-share-tip';
                  shareBtn.style.position = 'relative';
                  shareBtn.appendChild(tip);
                  setTimeout(function () { tip.remove(); }, 1600);
                }).catch(function () { });
              }
            });
          }
        })();
      </script>
    @endpush