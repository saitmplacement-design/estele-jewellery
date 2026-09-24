@props(['product'])

@php
  $rating = $product->reviewsAverageRating();
  $reviewCount = $product->reviewsCount();
  $discount = $product->compare_at_price
    ? (int) round((($product->compare_at_price - $product->price) / $product->compare_at_price) * 100)
    : 0;
  $cardImages = $product->getMedia('gallery');
@endphp

<article class="product-card group">
  <a class="product-card__frame skeleton block" href="{{ route('products.show', $product) }}" aria-label="{{ $product->title }}">
    {{-- Swipeable image scroller with dots, so a shopper can flip through a
         product's photos from the listing grid instead of only seeing the
         second one on desktop hover. --}}
    @if($cardImages->count() > 1)
      <span class="card-scroller" data-card-scroller>
        @foreach($cardImages as $index => $media)
          <img class="product-card__img card-scroller__img" src="{{ $media->getUrl('card') }}" alt="{{ $index === 0 ? $product->title : '' }}" loading="lazy" width="600" height="600">
        @endforeach
      </span>
      <span class="card-dots" data-card-dots aria-hidden="true">
        @foreach($cardImages as $index => $media)
          <span class="card-dot{{ $index === 0 ? ' is-active' : '' }}"></span>
        @endforeach
      </span>
    @elseif($product->hasMedia('gallery'))
      <img class="product-card__img" src="{{ $product->getFirstMediaUrl('gallery', 'card') }}" alt="{{ $product->title }}" loading="lazy" width="600" height="600">
    @endif

    @if($product->is_featured)
      <span class="product-card__ribbon">Bestseller</span>
    @elseif($discount > 0)
      <span class="product-card__ribbon product-card__ribbon--sale">{{ $discount }}% off</span>
    @endif

    <button class="absolute right-1.5 top-1.5 z-[2] grid h-9 w-9 place-items-center text-white drop-shadow-[0_1px_2px_rgba(0,0,0,0.55)] transition-transform active:scale-90" type="button" aria-label="Save {{ $product->title }} to wishlist" data-wishlist-toggle data-product-id="{{ $product->id }}">
      <svg class="h-6 w-6" viewBox="0 0 24 24" fill="rgba(255,255,255,0.25)" stroke="currentColor" stroke-width="1.8"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21.2l7.7-7.7 1.1-1.1a5.5 5.5 0 0 0 0-7.8z"/></svg>
    </button>

  </a>

  <div class="flex flex-1 flex-col pt-2">
    <h3 class="mb-1.5 line-clamp-2 text-[13px] font-normal leading-snug text-heading md:text-[14.5px]">
      <a class="transition-colors hover:text-rose" href="{{ route('products.show', $product) }}">{{ $product->title }}</a>
    </h3>
    @if($reviewCount > 0)
      {{-- Rating under the name, Flipkart-style. --}}
      <div class="mb-1 flex items-center gap-1.5">
        <span class="inline-flex items-center gap-0.5 rounded bg-[#1f9d55] px-1.5 py-[3px] text-[11px] font-bold leading-none text-white">{{ number_format($rating, 1) }}&#9733;</span>
        <span class="text-[11.5px] text-muted">({{ number_format($reviewCount) }})</span>
      </div>
    @endif
    <div class="mb-2.5 flex flex-wrap items-baseline gap-x-1.5">
      <span class="text-[14px] font-bold text-price md:text-[16px]">₹ {{ number_format($product->price, 0) }}</span>
      @if($product->compare_at_price)
        <span class="text-[12px] text-muted line-through">₹ {{ number_format($product->compare_at_price, 0) }}</span>
        @if($discount > 0)
          <span class="text-[12px] font-bold text-accent-dark">{{ $discount }}% off</span>
        @endif
      @endif
    </div>
    <form class="mt-auto flex gap-1.5" action="{{ route('cart.store', $product) }}" method="post" data-cart-form data-checkout-url="{{ route('checkout.index') }}">
      @csrf
      <input type="hidden" name="quantity" value="1">
      <button class="btn-cta-outline h-9 min-w-0 flex-1 whitespace-nowrap rounded-md px-1 text-[12px] md:h-10 md:text-[13px]" type="submit" name="express" value="1" formaction="{{ route('checkout.express.start', $product) }}" data-express-submit>Buy Now</button>
      <button class="btn-cta h-9 min-w-0 flex-1 whitespace-nowrap rounded-md px-1 text-[12px] md:h-10 md:text-[13px]" type="submit">Add to Bag</button>
    </form>
  </div>
</article>
