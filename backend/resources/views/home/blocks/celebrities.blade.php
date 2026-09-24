@props(['block'])

@if($block->items->isNotEmpty())
  {{-- The one dark section on the page. Portraits of the pieces being worn
       carry more weight against a jeweller's-case ground than against ivory,
       so the palette inverts here and nowhere else. --}}
  <section class="bg-deepwine py-5 md:py-11">
    <div class="mx-auto w-full max-w-wrapper px-3 md:px-4">
      <div class="mb-5 text-center md:mb-7">
        <h2 class="flex flex-wrap items-baseline justify-center gap-x-3 gap-y-1">
          <span class="font-display text-[19px] font-semibold uppercase tracking-[0.16em] text-white md:text-[24px]">As Seen On</span>
          <span class="font-script text-[32px] leading-none text-gold md:text-[42px]">Celebrities</span>
        </h2>
        @if($block->subtitle)
          <p class="mx-auto mt-4 max-w-[42ch] text-[13.5px] leading-relaxed text-white/70 md:text-[15px]">{{ $block->subtitle }}</p>
        @endif
        @if($block->cta_label)
          <a class="mt-6 inline-flex items-center justify-center border border-gold px-8 py-3 text-[12px] font-medium uppercase tracking-[0.14em] text-gold transition-colors hover:bg-gold hover:text-deepwine" href="{{ $block->cta_url ?? '#' }}">{{ $block->cta_label }}</a>
        @endif
      </div>
      <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-4 md:grid-cols-4 md:gap-5 lg:gap-6 xl:gap-7 2xl:grid-cols-5">
        @foreach($block->items as $item)
          <a class="cat-tile block" href="{{ $item->link_url ?? '#' }}">
            <span class="relative block overflow-hidden bg-white/5" style="aspect-ratio: 1/1.3;">
              @if($item->hasMedia('image'))
                <img class="cat-tile__img"
                     src="{{ $item->getFirstMediaUrl('image', 'card') }}"
                     alt="{{ $item->title }}" loading="lazy" width="500" height="650">
              @endif
            </span>
            @if($item->title)
              <p class="mt-3 text-center text-[12px] uppercase tracking-[0.1em] text-white/80 lg:text-[13px] xl:text-[13.5px]">{{ $item->title }}</p>
            @endif
          </a>
        @endforeach
      </div>
    </div>
  </section>
@endif
