@if($items->isEmpty())
  <div class="flex flex-1 flex-col items-center justify-center gap-4 px-6 py-16 text-center">
    <p class="text-[15px] text-muted">Your bag is empty.</p>
    <a class="btn-cta w-auto px-8" href="{{ route('home') }}" data-cart-close>
      Continue Shopping
    </a>
  </div>
@else
  @php
    $netSubtotal = max(0, $subtotal - ($discount ?? 0));
    $threshold = $freeShippingThreshold ?? null;
    $unlocked = $threshold !== null && $netSubtotal >= $threshold;
    $progressPercent = $threshold !== null && $threshold > 0 ? min(100, ($netSubtotal / $threshold) * 100) : 0;
    $remaining = $threshold !== null ? max(0, $threshold - $netSubtotal) : 0;
  @endphp

  {{-- No threshold configured means shipping is free on every order (the site
       runs "Free delivery pan India"), so the bar shows the unlocked state
       outright rather than disappearing and leaving the drawer with no
       shipping reassurance at all. --}}
  @php $alwaysFree = $threshold === null; @endphp
  <div class="flex items-center gap-3 bg-white px-4 pb-3.5 pt-3" data-free-shipping-bar @if(! $alwaysFree) data-threshold="{{ $threshold }}" data-net-subtotal="{{ $netSubtotal }}" @endif>
    <svg class="h-6 w-6 shrink-0 text-heading" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 6h11v9H3zM14 9h4l3 3v3h-7zM7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM17.5 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/></svg>
    <div class="min-w-0 flex-1">
      <p class="mb-1.5 text-[13px] font-bold text-[#454545]" data-free-shipping-message>
        @if($alwaysFree || $unlocked)
          Free shipping unlocked for this order
        @else
          Add <strong>₹{{ number_format($remaining, 0) }}</strong> more for free shipping
        @endif
      </p>
      <span class="block h-1.5 w-full overflow-hidden rounded-full bg-line">
        <span class="block h-full rounded-full bg-gradient-to-r from-[#00B65E] to-success transition-[width] duration-700 ease-out" data-free-shipping-fill style="width: {{ ($alwaysFree || $unlocked) ? 100 : $progressPercent }}%"></span>
      </span>
    </div>
  </div>

  <div class="space-y-2.5 px-2.5 py-2.5">
    @foreach($items as $item)
      <div class="bag-card flex gap-3 p-3" data-cart-drawer-item="{{ $item->id }}">
        <a class="skeleton block aspect-square w-[84px] shrink-0 overflow-hidden rounded-md" href="{{ route('products.show', $item->product) }}">
          @if($item->product->hasMedia('gallery'))
            <img class="h-full w-full object-cover" src="{{ $item->product->getFirstMediaUrl('gallery', 'card') }}" alt="{{ $item->product->title }}" width="128" height="128">
          @endif
        </a>
        <div class="flex flex-1 flex-col justify-between">
          <div>
            <a class="text-[14px] font-bold leading-snug tracking-[0.04em] text-[#454545]" href="{{ route('products.show', $item->product) }}">{{ $item->product->title }}</a>
            @if($item->variant)
              <p class="mt-0.5 text-[11.5px] text-muted">{{ collect($item->variant->attributes ?? [])->map(fn($v, $k) => "{$k}: {$v}")->implode(', ') }}</p>
            @endif
            <p class="mt-1 flex items-center gap-1.5 text-[13px]">
              <span class="text-[15px] font-bold text-black">₹ {{ number_format($item->unitPrice(), 0) }}</span>
              @if($item->product->compare_at_price)
                <span class="text-[11.5px] text-muted line-through">₹{{ number_format($item->product->compare_at_price, 0) }}</span>
              @endif
            </p>
          </div>
          <div class="flex items-center justify-between">
            <div class="inline-flex items-center gap-1.5" data-cart-qty-stepper data-item-id="{{ $item->id }}" data-max="{{ $item->availableStock() }}">
              <button class="grid h-7 w-7 place-items-center rounded-sm bg-[#ECECEC] text-[16px] leading-none text-heading" type="button" data-cart-qty-decrement aria-label="Decrease quantity">&minus;</button>
              <span class="grid h-7 w-9 place-items-center rounded border border-line-strong bg-white text-[13px]" data-cart-qty-value>{{ $item->quantity }}</span>
              <button class="grid h-7 w-7 place-items-center rounded-sm bg-[#ECECEC] text-[16px] leading-none text-heading" type="button" data-cart-qty-increment aria-label="Increase quantity">&plus;</button>
            </div>
            <button class="text-[12px] font-bold text-accent-dark" type="button" data-cart-remove data-item-id="{{ $item->id }}">
              Remove
            </button>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="bg-white px-4 py-4 shadow-[0_-2px_9px_rgba(0,0,0,0.08)]">
    @if(($activeOffers ?? []) !== [])
      <div class="mb-3 rounded border border-dashed border-line-strong p-2.5">
        <p class="mb-1.5 text-[10.5px] font-medium uppercase tracking-[0.5px] text-heading">Available Offers</p>
        <ul class="space-y-1 text-[11px] text-muted">
          @foreach($activeOffers as $offer)
            <li class="flex gap-1"><span>&bull;</span><span>{{ $offer }}</span></li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="mb-3" data-cart-coupon-box>
      <div class="mb-1 flex items-center justify-between">
        <span class="text-[11.5px] font-medium text-heading">Coupon code</span>
        <button class="text-[11px] text-muted underline transition-colors hover:text-accent" type="button" data-coupons-modal-open>View all coupons</button>
      </div>
      @if($couponCode ?? null)
        <div class="mb-3 flex items-center justify-between border border-line-strong bg-white px-3 py-2 text-[12.5px]">
          <span>Applied: <strong>{{ $couponCode }}</strong></span>
          <button class="text-muted underline transition-colors hover:text-[#eb001b]" type="button" data-coupon-remove>Remove</button>
        </div>
      @else
        <div class="mb-1 flex gap-1.5">
          <input class="w-full flex-1 border border-line-strong bg-white px-3 py-2.5 text-[12.5px] outline-none transition-colors placeholder:text-muted focus:border-heading" type="text" placeholder="Coupon code" data-coupon-input>
          <button class="h-auto w-[68px] shrink-0 rounded-lg bg-success text-[12px] font-bold text-white" type="button" data-coupon-apply>Apply</button>
        </div>
        <p class="hidden text-[11.5px] text-salebadge" data-coupon-error></p>
      @endif
    </div>
    @if(($discount ?? 0) > 0)
      <div class="mb-2 flex items-center justify-between text-[13px] text-[#1a7d3f]">
        <span>Discount ({{ $couponCode }})</span>
        <span data-cart-discount>&minus;₹{{ number_format($discount, 0) }}</span>
      </div>
    @endif
    <div class="mb-4 flex items-center justify-between text-[15px]">
      <span class="font-bold text-[#454545]">Subtotal</span>
      <span class="font-bold text-black" data-cart-subtotal>₹{{ number_format($subtotal - ($discount ?? 0), 0) }}</span>
    </div>
    <a class="btn-cta h-[49px]" href="{{ route('checkout.index') }}">Go To Checkout</a>
  </div>
@endif
