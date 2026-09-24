@props(['block'])

@php $items = $block->items; @endphp

@if($items->isNotEmpty())
  <section class="border-y border-line bg-white py-4 md:py-8">
    <div class="mx-auto w-full max-w-wrapper px-3 md:px-4">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:gap-8">
        <div class="shrink-0 md:w-[220px] lg:w-[260px]">
          @if($block->subtitle)
            <p class="section-head__eyebrow">{{ $block->subtitle }}</p>
          @endif
          <h2 class="font-serif text-[22px] leading-tight text-heading md:text-[26px] lg:text-[30px]">{!! nl2br(e($block->title ?: "Your Budget,\nYour Bling")) !!}</h2>
        </div>
        <div class="grid flex-1 grid-cols-2 gap-2.5 sm:grid-cols-3 md:grid-cols-5 md:gap-3.5">
          @foreach($items as $item)
            @php $isPremium = $loop->last; @endphp
            <a class="group relative flex flex-col items-center justify-center rounded-xl border border-line px-3 py-5 text-center {{ $loop->last && $loop->count % 2 === 1 ? 'col-span-2 sm:col-span-1' : '' }} transition-all hover:-translate-y-0.5 hover:border-gold hover:shadow-md md:py-6 {{ $isPremium ? 'bg-deepwine text-white' : 'bg-gradient-to-br from-pinksoft to-white' }}" href="{{ $item->link_url ?: route('search') }}">
              <span class="text-[10.5px] font-semibold uppercase tracking-[0.18em] {{ $isPremium ? 'text-gold' : 'text-muted' }}">{{ $item->title }}</span>
              <span class="mt-1 font-serif text-[24px] font-semibold leading-none md:text-[28px] lg:text-[32px]">{{ $item->body }}</span>
              <span class="mt-3 grid h-6 w-6 place-items-center rounded-full transition-colors {{ $isPremium ? 'bg-white/20 text-white group-hover:bg-gold group-hover:text-deepwine' : 'bg-gold text-white group-hover:bg-rose' }}">
                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
              </span>
            </a>
          @endforeach
        </div>
      </div>
    </div>
  </section>
@endif
