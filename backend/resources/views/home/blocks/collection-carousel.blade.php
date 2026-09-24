@props(['block'])

@php $collections = $block->items->pluck('itemable')->filter(); @endphp

@if($collections->isNotEmpty())
  <section class="bg-white pb-6 md:bg-warmbeige md:py-9">
    <div class="mx-auto w-full max-w-wrapper px-3 md:px-4">
      <x-section-header eyebrow="Signature Edits" :title="$block->title" :subtitle="$block->subtitle" :cta-label="$block->cta_label" :cta-url="$block->cta_url" />
      <div @if($collections->count() > 8) data-explore @endif>
        <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-4 md:grid-cols-4 md:gap-5 lg:grid-cols-5 lg:gap-6 {{ $collections->count() > 8 ? 'explore-grid-4row' : '' }}" @if($collections->count() > 8) data-explore-grid @endif>
        @foreach($collections as $collection)
          <a class="cat-tile block" href="{{ route('collections.show', $collection) }}">
            <span class="skeleton relative block aspect-[2/3] overflow-hidden rounded-lg">
              @if($collection->hasMedia('image'))
                <img class="cat-tile__img"
                     src="{{ $collection->getFirstMediaUrl('image', 'tile') }}"
                     alt="{{ $collection->name }}" loading="lazy">
              @endif
              <span class="cat-tile__label">{{ $collection->name }}</span>
            </span>
          </a>
        @endforeach
        </div>
        @if($collections->count() > 8)
          <div class="mt-5 text-center sm:hidden">
            <button type="button" class="inline-flex items-center border-b border-gold pb-1 text-[12px] font-medium uppercase tracking-[0.14em] text-heading transition-colors hover:text-gold" data-explore-toggle data-more-label="Explore more" data-less-label="Show less" aria-expanded="false">Explore more</button>
          </div>
        @endif
      </div>
    </div>
  </section>
@endif
