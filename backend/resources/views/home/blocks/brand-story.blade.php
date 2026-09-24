@props(['block'])

@php $stats = $block->items; @endphp

<section class="bg-ivory py-5 md:py-12">
  <div class="mx-auto w-full max-w-wrapper px-3 md:px-4">
    <div class="grid items-center gap-8 lg:grid-cols-2 lg:gap-14">
      <div class="text-center lg:text-left">
        <h2 class="wordmark gold-leaf text-[26px] md:text-[36px]">{{ $siteSettings['site_name'] ?? 'Estele' }}</h2>
        @if($block->title)
          <p class="mt-2 font-script text-[26px] leading-none text-rose md:text-[32px]">{{ $block->title }}</p>
        @endif
        @if($block->subtitle)
          <p class="mx-auto mt-5 max-w-[60ch] text-[13.5px] leading-[1.9] text-muted md:text-[14.5px] lg:mx-0">{{ $block->subtitle }}</p>
        @endif
        @if($block->cta_label)
          <div class="mt-5 flex flex-wrap justify-center gap-3 lg:justify-start">
            <a class="inline-flex items-center gap-2 rounded-md bg-heading px-5 py-2.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-white transition-colors hover:bg-rose" href="{{ $block->cta_url ?: route('collections.index') }}">{{ $block->cta_label }}</a>
          </div>
        @endif
      </div>
      @if($stats->isNotEmpty())
        <div class="grid grid-cols-2 gap-3 md:gap-4">
          @foreach($stats as $stat)
            <div class="rounded-xl border border-line bg-white px-4 py-6 text-center transition-colors hover:border-gold md:py-8">
              <p class="font-display text-[26px] font-semibold leading-none text-heading md:text-[32px]">{{ $stat->title }}</p>
              <p class="mt-2 text-[11px] font-medium uppercase tracking-[0.14em] text-muted">{{ $stat->body }}</p>
            </div>
          @endforeach
        </div>
      @endif
    </div>
  </div>
</section>
