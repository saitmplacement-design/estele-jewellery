@props(['block'])

@php $faqs = $block->items->pluck('itemable')->filter(); @endphp

@if($faqs->isNotEmpty())
  <section class="border-t border-line bg-paper py-5 md:py-10">
    <div class="mx-auto w-full max-w-wrapper px-3 md:px-4">
      <x-section-header align="left" :eyebrow="$block->subtitle ?: 'Help Centre'" :title="$block->title ?: 'Questions? Answered.'" :cta-label="$block->cta_label ?: 'All FAQs'" :cta-url="$block->cta_url ?: route('faq.index')" />
      <div class="grid grid-cols-1 gap-2.5 md:grid-cols-2 md:gap-3">
        @foreach($faqs as $faq)
          <details class="marker-pm group rounded-lg border border-line bg-white px-4 py-3 transition-colors open:border-gold">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-[13.5px] font-medium text-heading md:text-[14px]">{{ $faq->question }}</summary>
            <div class="mt-2 text-[12.5px] leading-relaxed text-muted md:text-[13px]">{!! nl2br(e(strip_tags($faq->answer))) !!}</div>
          </details>
        @endforeach
      </div>
    </div>
  </section>
@endif
