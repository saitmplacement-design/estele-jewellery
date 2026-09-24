@props(['block'])

@php $products = $block->items->pluck('itemable')->filter(); @endphp

@if($products->isNotEmpty())
  <section class="py-4 md:py-9">
    <div class="mx-auto w-full max-w-wrapper px-3 md:px-10 xl:px-14">
      <x-section-header align="left" :eyebrow="$block->subtitle ?: 'Handpicked for you'" :title="$block->title ?: 'Bestsellers'" :cta-label="$block->cta_label ?: 'View all'" :cta-url="$block->cta_url ?: route('categories.index')" />
      <div @if($products->count() > 8) data-explore @endif>
        <div class="grid grid-cols-2 gap-x-2.5 gap-y-5 sm:grid-cols-3 md:grid-cols-4 md:gap-4 lg:grid-cols-5 xl:gap-5 {{ $products->count() > 8 ? 'explore-grid-4row' : '' }}" @if($products->count() > 8) data-explore-grid @endif>
          @foreach($products->take(20) as $product)
            <x-product-card :product="$product" />
          @endforeach
        </div>
        @if($products->count() > 8)
          <div class="mt-5 text-center sm:hidden">
            <button type="button" class="btn-cta-outline mx-auto h-11 w-auto px-8 text-[14px]" data-explore-toggle data-more-label="Explore more" data-less-label="Show less" aria-expanded="false">Explore more</button>
          </div>
        @endif
      </div>
    </div>
  </section>
@endif
