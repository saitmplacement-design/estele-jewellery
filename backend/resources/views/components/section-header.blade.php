@props(['title', 'subtitle' => null, 'eyebrow' => null, 'ctaLabel' => null, 'ctaUrl' => null, 'tone' => 'light', 'align' => 'center'])

@if($align === 'left')
  <div class="mb-4 flex items-end justify-between gap-4 md:mb-7">
    <div>
      @if($eyebrow)
        <p class="section-head__eyebrow">{{ $eyebrow }}</p>
      @endif
      <h2 class="section-head__title section-head__title--plain {{ $tone === 'dark' ? 'text-white' : '' }}">{{ $title }}</h2>
      @if($subtitle)
        <p class="section-head__sub mx-0 {{ $tone === 'dark' ? 'text-white/70' : '' }}">{{ $subtitle }}</p>
      @endif
    </div>
    @if($ctaLabel)
      <a class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap text-[14px] font-bold underline underline-offset-4 {{ $tone === 'dark' ? 'text-white' : 'text-heading' }}" href="{{ $ctaUrl ?? '#' }}">{{ $ctaLabel }}<svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
    @endif
  </div>
@else
  <div class="section-head {{ $tone === 'dark' ? 'mb-5 md:mb-9' : 'section-head--strip md:block' }}">
    @if($eyebrow)
      <p class="section-head__eyebrow">{{ $eyebrow }}</p>
    @endif
    <h2 class="section-head__title {{ $tone === 'dark' ? 'text-white' : '' }}">{{ $title }}</h2>
    <span class="section-head__rule"></span>
    @if($subtitle)
      <p class="section-head__sub {{ $tone === 'dark' ? 'text-white/70' : '' }}">{{ $subtitle }}</p>
    @endif
    @if($ctaLabel)
      <a class="mt-3 inline-flex items-center border-b border-gold pb-1 text-[11px] font-medium uppercase tracking-[0.14em] transition-colors hover:text-gold md:mt-4 md:text-[12px] {{ $tone === 'dark' ? 'text-white' : 'text-heading' }}" href="{{ $ctaUrl ?? '#' }}">{{ $ctaLabel }}</a>
    @endif
  </div>
@endif
