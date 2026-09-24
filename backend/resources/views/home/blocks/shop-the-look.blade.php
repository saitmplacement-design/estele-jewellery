@props(['block'])

@php $products = $block->items->pluck('itemable')->filter(); @endphp

@if($products->isNotEmpty())
  <section class="bg-ivory py-5 md:py-10">
    <div class="mx-auto w-full max-w-wrapper px-3 md:px-10 xl:px-14">
      <x-section-header :eyebrow="$block->subtitle ?: '@estele.co'" :title="$block->title ?: 'Styled by You #EsteleQueens'" />
      <div class="grid grid-cols-4 gap-1.5 md:grid-cols-8 md:gap-2.5">
        @foreach($products as $product)
          <a class="group relative block aspect-square overflow-hidden rounded-lg bg-placeholder" href="{{ route('products.show', $product) }}">
            @if($product->hasMedia('gallery'))
              <img class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-110" src="{{ $product->getFirstMediaUrl('gallery', 'card') }}" alt="{{ $product->title }}" loading="lazy" width="300" height="300">
            @endif
            <span class="absolute inset-0 flex flex-col justify-end bg-gradient-to-t from-black/70 to-transparent p-2 opacity-0 transition-opacity duration-300 group-hover:opacity-100">
              <span class="line-clamp-1 text-[11px] font-medium text-white">{{ $product->title }}</span>
              <span class="text-[11px] font-semibold text-gold">₹{{ number_format($product->price, 0) }}</span>
            </span>
            <span class="absolute right-1.5 top-1.5 grid h-5 w-5 place-items-center rounded-full bg-white/90 text-heading">
              <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3.5" y="3.5" width="17" height="17" rx="4.5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/></svg>
            </span>
          </a>
        @endforeach
      </div>
    </div>
  </section>
@endif
