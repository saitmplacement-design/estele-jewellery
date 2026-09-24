@extends('layouts.app')

@section('meta_title', 'Saved Items | '.($siteSettings['site_name'] ?? 'Estele'))
@section('meta_description', 'The pieces you have saved to come back to.')

@section('content')

<section class="bg-white pb-6 md:bg-ivory md:py-14">
  <div class="mx-auto w-full max-w-wrapper px-3 md:px-4">
    <x-section-header title="Saved Items" subtitle="Pieces you kept aside. They stay here on this device until you remove them." />

    {{-- Both states start hidden and app.js reveals the right one once it has
         read the saved list, so neither flashes on load. --}}
    <div class="grid grid-cols-2 gap-x-2.5 gap-y-5 sm:grid-cols-3 sm:gap-4 md:grid-cols-4 md:gap-5 lg:gap-6 xl:grid-cols-5 xl:gap-7 2xl:grid-cols-6" data-wishlist-grid hidden>
      @foreach($products as $product)
        <x-product-card :product="$product" />
      @endforeach
    </div>

    <div class="mx-auto max-w-[46ch] py-4 text-center" data-wishlist-empty hidden>
      <p class="text-[18px] font-bold text-heading">Nothing saved yet</p>
      <p class="mt-2.5 text-[13.5px] leading-relaxed text-muted">Tap the heart on any piece to keep it here while you decide.</p>
      <a class="btn-cta mx-auto mt-6 w-auto px-8" href="{{ route('home') }}">Browse the collection</a>
    </div>
  </div>
</section>

@endsection
