@extends('layouts.app')

@section('meta_title', 'Cart | '.($siteSettings['site_name'] ?? 'Estele'))
@section('meta_description', 'Your shopping bag.')

@php $bagTotal = $subtotal - $discount + $shipping['fee']; @endphp

@if($items->isNotEmpty())
  @section('sticky_bar')
    <div class="buybar md:hidden">
      <a class="btn-cta h-[49px] justify-between px-[18px]" href="{{ route('checkout.index') }}">
        <span>Go To Checkout</span>
        <span>₹ {{ number_format($bagTotal, 0) }}</span>
      </a>
    </div>
  @endsection
@endif

@section('content')

  <div class="bg-bagsurface pb-6 md:pb-8">
    <div class="flex h-[53px] items-center justify-between bg-white px-3 shadow-[0_1px_4px_rgba(0,0,0,0.1)] md:hidden">
      <div class="flex items-center gap-1">
        <a class="grid h-10 w-9 place-items-center text-heading" href="{{ url()->previous() === url()->current() ? route('home') : url()->previous() }}" aria-label="Back">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
        </a>
        <h1 class="text-[14px] font-bold text-[#454545]">Your Bag <span>({{ $items->sum('quantity') }})</span></h1>
      </div>
      <span class="flex items-center gap-1.5 pr-2 text-[14px] text-[#454545]">
        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6z"/><path d="M9 12l2 2 4-4"/></svg>
        Secure
      </span>
    </div>

    <nav class="mx-auto hidden w-full max-w-wrapper flex-wrap items-center gap-1.5 px-4 py-4 text-[13px] text-muted md:flex" aria-label="Breadcrumb">
      <x-breadcrumb :items="[['label' => 'Cart']]" />
    </nav>

    <div class="mx-auto w-full max-w-wrapper md:px-4">
      <h1 class="mb-5 hidden text-[26px] md:block">Your Bag</h1>

      @if($items->isEmpty())
        <div class="px-4 py-20 text-center">
          <svg class="mx-auto mb-4 h-14 w-14 text-line-strong" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"><path d="M5 8h14l1 13H4z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
          <p class="mb-5 text-[15px] text-muted">Your bag is empty.</p>
          <a class="btn-cta mx-auto w-auto px-8" href="{{ route('home') }}">Continue Shopping</a>
        </div>
      @else
        @if($shipping['fee'] <= 0)
          <div class="mb-2.5 flex items-center gap-3.5 rounded-b-xl bg-white px-4 py-4 md:rounded-xl">
            <svg class="h-7 w-7 shrink-0 text-heading" viewBox="0 0 24 24" fill="currentColor"><path d="M3 6h11v9H3zM14 9h4l3 3v3h-7zM7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM17.5 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/></svg>
            <div class="flex-1">
              <p class="mb-2 text-[14px] font-bold text-[#454545]">Free shipping unlocked for this order</p>
              <span class="block h-1.5 w-full rounded-full bg-gradient-to-r from-[#00B65E] to-success"></span>
            </div>
          </div>
        @endif

        @php
          // Prices are stored tax-inclusive, so "Item Total" already carries
          // GST and the summary states that rather than adding a line that
          // would double-count it.
          $youSave = $discount + max(0, $items->sum(fn ($i) => (($i->product->compare_at_price ?? 0) > $i->unitPrice() ? $i->product->compare_at_price - $i->unitPrice() : 0) * $i->quantity));
        @endphp

        <div class="grid grid-cols-1 gap-2.5 px-2.5 md:grid-cols-[1fr_340px] md:gap-[34px] md:px-0">
          <div class="space-y-2.5">
            @foreach($items as $item)
              <div class="bag-card relative flex gap-3.5">
                <a class="skeleton relative aspect-square w-[108px] shrink-0 overflow-hidden rounded-md" href="{{ route('products.show', $item->product) }}">
                  @if($item->product->hasMedia('gallery'))
                    <img class="h-full w-full object-cover" src="{{ $item->product->getFirstMediaUrl('gallery', 'card') }}" alt="{{ $item->product->title }}" loading="lazy" width="120" height="120">
                  @endif
                  @if($item->availableStock() <= 3)
                    <span class="absolute inset-x-0 bottom-1.5 mx-auto w-fit rounded bg-white px-1.5 py-0.5 text-[11px] font-bold text-heading shadow">Only {{ $item->availableStock() }} Left</span>
                  @endif
                </a>
                <div class="flex min-w-0 flex-1 flex-col">
                  <a class="mb-1 block pr-7 text-[14px] font-bold leading-snug tracking-[0.04em] text-[#454545]" href="{{ route('products.show', $item->product) }}">{{ $item->product->title }}</a>
                  @if($item->variant)
                    <p class="mb-1 text-[12px] tracking-[0.04em] text-[#454545]/60">{{ collect($item->variant->attributes ?? [])->map(fn($v, $k) => "{$k}: {$v}")->implode('   ') }}</p>
                  @endif
                  <p class="mb-2 mt-auto text-[16px] font-bold text-black">₹ {{ number_format($item->unitPrice(), 0) }}</p>
                  <form action="{{ route('cart.update', $item) }}" method="post" data-cart-qty-form>
                    @csrf
                    @method('patch')
                    <div class="inline-flex items-center gap-2" data-qty>
                      <button class="grid h-11 w-11 place-items-center rounded-sm bg-[#ECECEC] text-[18px] leading-none text-heading md:h-7 md:w-7" type="button" data-qty-minus aria-label="Decrease quantity">&minus;</button>
                      <input class="h-11 w-11 rounded border border-line-strong bg-white text-center !text-[14px] text-heading md:h-7 md:w-9" type="number" name="quantity" value="{{ $item->quantity }}" min="1" max="{{ $item->availableStock() }}" aria-label="Quantity">
                      <button class="grid h-11 w-11 place-items-center rounded-sm bg-[#ECECEC] text-[18px] leading-none text-heading md:h-7 md:w-7" type="button" data-qty-plus aria-label="Increase quantity">+</button>
                    </div>
                  </form>
                </div>
                <form class="absolute right-2 top-2" action="{{ route('cart.destroy', $item) }}" method="post">
                  @csrf
                  @method('delete')
                  <button class="grid h-8 w-8 place-items-center text-[#454545]" type="submit" aria-label="Remove {{ $item->product->title }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M5 5l14 14M19 5L5 19"/></svg>
                  </button>
                </form>
              </div>
            @endforeach

            <div class="bag-card">
              <label class="mb-1.5 block text-[13px] font-bold text-[#454545]" for="order-note">Order note</label>
              <textarea class="w-full rounded border border-line-strong bg-white px-3 py-2.5 text-[14px] outline-none transition-colors placeholder:text-muted focus:border-heading" id="order-note" rows="2" placeholder="Add a note to your order" data-order-note></textarea>
            </div>
          </div>

          <aside class="space-y-2.5 md:sticky md:top-[100px] md:self-start">
            <div class="bag-card border border-gold/60">
              @if($couponCode)
                <div class="flex items-center justify-between gap-3">
                  <div class="flex items-center gap-3">
                    <svg class="h-6 w-6 shrink-0 text-heading" viewBox="0 0 24 24" fill="currentColor"><path d="M2 3h9.6l10 10-8.6 8.6-10-10zm5 3.5A1.5 1.5 0 1 0 7 9.5a1.5 1.5 0 0 0 0-3z"/></svg>
                    <p class="text-[13px] text-[#454545]">Applied <strong>'{{ $couponCode }}'</strong></p>
                  </div>
                  <form action="{{ route('cart.coupon.remove') }}" method="post">
                    @csrf
                    @method('delete')
                    <button class="text-[12px] font-bold text-accent-dark" type="submit">Remove</button>
                  </form>
                </div>
              @else
                <form action="{{ route('cart.coupon.apply') }}" method="post" class="flex items-center gap-2.5">
                  @csrf
                  <svg class="h-6 w-6 shrink-0 text-heading" viewBox="0 0 24 24" fill="currentColor"><path d="M2 3h9.6l10 10-8.6 8.6-10-10zm5 3.5A1.5 1.5 0 1 0 7 9.5a1.5 1.5 0 0 0 0-3z"/></svg>
                  <input class="h-9 min-w-0 flex-1 rounded border border-line-strong bg-white px-3 uppercase outline-none placeholder:normal-case placeholder:text-muted focus:border-heading" id="coupon" name="code" type="text" placeholder="Enter coupon code" aria-label="Coupon code" required>
                  <button class="h-[30px] w-[68px] shrink-0 rounded-lg bg-success text-[12px] font-bold text-white" type="submit">Apply</button>
                </form>
              @endif
              <button class="mt-2 flex items-center gap-1 pl-[34px] text-[12px] text-muted" type="button" data-coupons-modal-open>View all coupons <span aria-hidden="true">&rsaquo;</span></button>
            </div>

            @include('partials.offers-banner')

            <div class="bag-card px-[18px] py-5">
              <h2 class="mb-4 text-[16px] font-bold tracking-[0.04em] text-[#454545]">Order Summary</h2>
              <dl class="space-y-2.5 text-[14px] tracking-[0.04em] text-[#454545]">
                <div class="flex justify-between"><dt>Item Total (inclusive of Taxes)</dt><dd class="font-bold">₹ {{ number_format($subtotal, 0) }}</dd></div>
                @if($discount > 0)
                  <div class="flex justify-between"><dt>Discount</dt><dd class="font-bold text-salebadge">&minus;₹ {{ number_format($discount, 0) }}</dd></div>
                @endif
                <div class="flex justify-between"><dt>Shipping{{ ($shipping['estimated'] ?? false) ? ' (estimated)' : '' }}</dt><dd class="font-bold {{ $shipping['fee'] > 0 ? '' : 'text-salebadge' }}">{{ $shipping['fee'] > 0 ? '₹ '.number_format($shipping['fee'], 0) : 'FREE' }}</dd></div>
                <div class="flex justify-between"><dt>GST</dt><dd class="text-muted">Included</dd></div>
                <div class="flex justify-between border-t border-line-strong pt-3 text-[16px] font-bold"><dt>Total Payable</dt><dd>₹ {{ number_format($bagTotal, 0) }}</dd></div>
              </dl>
              @if($youSave > 0)
                <p class="mt-4 rounded bg-[#D9F2E3] py-2.5 text-center text-[14px] font-bold text-[#1a7d3f]">You Save ₹ {{ number_format($youSave, 0) }} In This Order</p>
              @endif
              <p class="mt-3 text-center text-[13px] text-[#454545]">UPI, Cards | Secure Checkout</p>
              <a class="btn-cta mt-4 hidden md:inline-flex" href="{{ route('checkout.index') }}">Go To Checkout</a>
              <a class="mx-auto mt-3.5 block w-fit border-b border-current text-[13px] text-muted" href="{{ route('home') }}">Continue Shopping</a>
            </div>

            @include('partials.trust-badges')
          </aside>
        </div>
      @endif
    </div>
  </div>

@endsection
