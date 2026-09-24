@props(['block'])

<section class="bg-pinksoft py-4 md:py-9">
  <div class="mx-auto w-full max-w-wrapper px-3 md:px-4">
    @if($block->title)
      <x-section-header :title="$block->title" :subtitle="$block->subtitle" />
    @endif
    <div class="relative" data-carousel>
      <button class="absolute top-1/2 z-[3] hidden h-10 w-10 -translate-y-1/2 place-items-center rounded-full border border-line bg-white text-heading shadow-sm transition-colors hover:border-rose hover:bg-rose hover:text-white disabled:pointer-events-none disabled:opacity-0 md:grid -left-2 xl:-left-[18px]" type="button" data-carousel-prev aria-label="Previous">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
      <div class="carousel-track gap-3.5 lg:gap-5 xl:gap-6" data-carousel-track>
        @foreach($block->items as $item)
          <div class="flex-[0_0_88%] sm:flex-[0_0_calc((100%-14px)/2)] md:flex-[0_0_calc((100%-2*14px)/3)] lg:flex-[0_0_calc((100%-3*20px)/4)] xl:flex-[0_0_calc((100%-4*24px)/5)] 2xl:flex-[0_0_calc((100%-5*24px)/6)]">
            <blockquote class="flex h-full flex-col overflow-hidden border border-line bg-paper">
              @if($item->hasMedia('image'))
                <span class="block aspect-square overflow-hidden bg-placeholder">
                  <img class="h-full w-full object-cover" src="{{ $item->getFirstMediaUrl('image', 'card') }}" alt="{{ $item->title ? 'Photo shared by '.$item->title : '' }}" loading="lazy" width="500" height="500">
                </span>
              @endif
              <div class="flex flex-1 flex-col p-4">
                <div class="mb-2.5 text-[13px] tracking-wider">
                  @for($i = 0; $i < 5; $i++)
                    <span class="{{ $i < ($item->rating ?? 5) ? 'text-star' : 'text-line-strong' }}">&#9733;</span>
                  @endfor
                </div>
                <p class="mb-3.5 flex-1 text-[12.5px] leading-relaxed text-ink lg:text-[13.5px]">{{ $item->body }}</p>
                <cite class="text-[12px] not-italic text-muted">- {{ $item->title }}</cite>
              </div>
            </blockquote>
          </div>
        @endforeach
      </div>
      <button class="absolute top-1/2 z-[3] hidden h-10 w-10 -translate-y-1/2 place-items-center rounded-full border border-line bg-white text-heading shadow-sm transition-colors hover:border-rose hover:bg-rose hover:text-white disabled:pointer-events-none disabled:opacity-0 md:grid -right-2 xl:-right-[18px]" type="button" data-carousel-next aria-label="Next">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
      </button>
    </div>
    {{-- Auto-populated by the shared carousel JS ([data-carousel-dots] inside
         a [data-carousel] root) — same mechanism already used by every other
         carousel block on the site, so no new JS needed. --}}
    <div class="mt-4 flex justify-center gap-2" data-carousel-dots></div>
  </div>
</section>
