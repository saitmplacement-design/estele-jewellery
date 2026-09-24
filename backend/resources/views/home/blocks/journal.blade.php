@props(['block'])

@php
  $blogs = $block->items->pluck('itemable')->filter();
  $promo = $block->items->firstWhere(fn ($item) => blank($item->itemable_type) && filled($item->title));
@endphp

@if($blogs->isNotEmpty() || $promo)
  <section class="bg-white py-5 md:py-10">
    <div class="mx-auto w-full max-w-wrapper px-3 md:px-4">
      <x-section-header align="left" :eyebrow="$block->subtitle ?: 'Style Notes'" :title="$block->title ?: 'From the Journal'" :cta-label="$block->cta_label ?: 'All stories'" :cta-url="$block->cta_url ?: route('blogs.index')" />
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6">
        @foreach($blogs->take(2) as $blog)
          <x-blog-card :blog="$blog" />
        @endforeach
        @if($promo)
          <a class="group relative flex min-h-[220px] flex-col justify-end overflow-hidden rounded-lg bg-deepwine p-6 text-white sm:col-span-2 lg:col-span-1" href="{{ $promo->link_url ?: route('faq.index') }}">
            <span class="absolute -right-8 -top-8 h-40 w-40 rounded-full bg-gold/15 transition-transform duration-500 group-hover:scale-125"></span>
            <span class="section-head__eyebrow !text-gold">Care Guide</span>
            <span class="font-serif text-[20px] leading-snug md:text-[22px]">{{ $promo->title }}</span>
            @if($promo->body)
              <span class="mt-3 text-[12.5px] leading-relaxed text-white/70">{{ $promo->body }}</span>
            @endif
            <span class="section-head__cta mt-4 text-gold">Read the guide<svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
          </a>
        @endif
      </div>
    </div>
  </section>
@endif
