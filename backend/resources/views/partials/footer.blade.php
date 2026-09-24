@php
  $icons = [
    'shield' => 'M12 2 3 7v6c0 5 4 8.5 9 9 5-.5 9-4 9-9V7l-9-5Zm-1 13-3-3 1.4-1.4L11 12.2l4.6-4.6L17 9l-6 6Z',
    'return' => 'M4 12a8 8 0 1 0 2.3-5.7L4 8.5M4 4v4.5h4.5',
    'truck' => 'M3 7h11v9H3zM14 10h4l3 3v3h-7zM6.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm11 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z',
    'lock' => 'M6 10V8a6 6 0 1 1 12 0v2m-13 0h14v11H5z',
  ];
  $decode = fn (string $key, array $fallback) => json_decode($siteSettings[$key] ?? '[]', true) ?: $fallback;

  $footerUsps = $decode('footer_usps', [
    ['title' => '100% Anti-Tarnish', 'body' => 'Plating that stays bright', 'icon' => 'shield'],
    ['title' => '7-Day Easy Returns', 'body' => 'Hassle-free exchange', 'icon' => 'return'],
    ['title' => 'Free Shipping ₹1,499+', 'body' => 'Pan-India delivery', 'icon' => 'truck'],
    ['title' => 'Secure Payments', 'body' => 'UPI, cards & COD', 'icon' => 'lock'],
  ]);
  $popularSearches = $decode('footer_popular_searches', ['Earrings', 'Necklace Sets', 'Bangles', 'Mangalsutra', 'Maang Tikka', 'Rose Gold', 'Pearl Jewellery', 'Bridal Sets', 'Office Wear', 'Gifts Under ₹999']);
  $paymentBadges = $decode('footer_payment_badges', ['UPI', 'Visa', 'Mastercard', 'RuPay', 'Net Banking', 'COD']);
  $footerAbout = $siteSettings['footer_about'] ?? "India's leading fashion jewellery destination. Over 100,000 anti-tarnish 24K gold plated designs, 45+ stores and 5M+ happy customers across the country.";
  $footerCompany = $siteSettings['footer_company_name'] ?? 'Estele Accessories Pvt. Ltd.';
  $footerAddress = $siteSettings['contact_address'] ?? '9-47, Keshav Nagar, Boduppal, Hyderabad, Telangana 500092';
  $footerHours = $siteSettings['contact_hours'] ?? 'Mon–Sat, 10am–7pm IST';
@endphp

<footer class="bg-deepwine text-white/90">
  <div class="border-b border-white/10 bg-black/15">
    <div class="mx-auto grid w-full max-w-wrapper grid-cols-2 gap-x-4 gap-y-4 px-4 py-4 md:grid-cols-4 md:px-8 md:py-6">
      @foreach($footerUsps as $usp)
        <div class="flex items-center gap-3">
          <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-gold/50 text-gold">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><path d="{{ $icons[$usp['icon'] ?? 'shield'] ?? $icons['shield'] }}"/></svg>
          </span>
          <span>
            <span class="block text-[12.5px] font-semibold text-white">{{ $usp['title'] }}</span>
            <span class="block text-[11.5px] text-white/60">{{ $usp['body'] }}</span>
          </span>
        </div>
      @endforeach
    </div>
  </div>

  <div class="mx-auto w-full max-w-wrapper px-4 pb-4 pt-4 md:px-8 md:pb-5 md:pt-6">
    {{-- Link groups are <details> accordions on phones (one long column of
         ~30 links otherwise) and plain always-open columns from md up —
         app.js keeps them open there and closes them on phones. Rendered
         open so the links are all reachable without JavaScript. --}}
    <div class="grid grid-cols-2 gap-x-6 border-b border-white/10 pb-4 md:grid-cols-4 md:gap-y-4 lg:grid-cols-12">

      <div class="col-span-2 mb-3 md:col-span-4 md:mb-0 lg:col-span-4">
        <p class="wordmark text-[22px] text-white">{{ $siteSettings['site_name'] ?? 'Estele' }}</p>
        <p class="mt-1 text-[10.5px] uppercase tracking-[0.22em] text-gold">Trusted since 1989</p>
        <p class="mt-4 max-w-[46ch] text-[13px] leading-relaxed text-white/70">{{ $footerAbout }}</p>
        <div class="mt-5 flex items-center gap-2.5">
          <a class="grid h-9 w-9 place-items-center rounded-full border border-white/20 text-white transition-all hover:border-gold hover:bg-gold hover:text-deepwine" href="{{ $siteSettings['social_facebook'] ?? 'https://www.facebook.com/estelejewelery/' }}" target="_blank" rel="noopener" aria-label="Facebook">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 22v-8.2h2.75l.41-3.2h-3.16V8.4c0-.93.26-1.56 1.6-1.56h1.7V3.98A22.7 22.7 0 0 0 14.2 3.8c-2.44 0-4.11 1.49-4.11 4.22v2.36H7.3v3.2h2.79V22h3.4Z"/></svg>
          </a>
          <a class="grid h-9 w-9 place-items-center rounded-full border border-white/20 text-white transition-all hover:border-gold hover:bg-gold hover:text-deepwine" href="{{ $siteSettings['social_instagram'] ?? 'https://www.instagram.com/estele.co/' }}" target="_blank" rel="noopener" aria-label="Instagram">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="3.5" width="17" height="17" rx="4.5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/></svg>
          </a>
          <a class="grid h-9 w-9 place-items-center rounded-full border border-white/20 text-white transition-all hover:border-gold hover:bg-gold hover:text-deepwine" href="{{ $siteSettings['social_youtube'] ?? 'https://www.youtube.com/@estelejewellery' }}" target="_blank" rel="noopener" aria-label="YouTube">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8ZM10 15V9l5.2 3L10 15Z"/></svg>
          </a>
        </div>
        <div class="mt-5">
          <p class="mb-2 text-[10.5px] font-semibold uppercase tracking-[0.18em] text-white/55">Download the app</p>
          <div class="flex gap-2.5">
            <img src="{{ asset('assets/images/badges/app-store.svg') }}" class="h-8" alt="App Store" width="100" height="32">
            <img src="{{ asset('assets/images/badges/google-play.svg') }}" class="h-8" alt="Google Play" width="100" height="32">
          </div>
        </div>
      </div>

      <details class="footer-acc group/acc col-span-2 md:col-span-1 lg:col-span-2 border-b border-white/10 md:border-0" data-footer-acc open>
        <summary class="flex cursor-pointer list-none items-center justify-between py-3.5 md:pointer-events-none md:mb-3.5 md:py-0 [&::-webkit-details-marker]:hidden">
          <h3 class="font-display text-[12px] font-semibold uppercase tracking-[0.14em] text-gold">Shop by Category</h3>
          <svg class="h-4 w-4 text-gold transition-transform duration-200 group-open/acc:rotate-45 md:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        </summary>
        <ul class="space-y-2.5 pb-4 text-[13px] text-white/75 md:space-y-2 md:pb-0">
          @foreach($navCategories as $category)
            <li><a class="transition-colors hover:text-gold" href="{{ route('categories.show', $category['slug']) }}">{{ $category['name'] }}</a></li>
          @endforeach
        </ul>
      </details>

      <details class="footer-acc group/acc col-span-2 md:col-span-1 lg:col-span-2 border-b border-white/10 md:border-0" data-footer-acc open>
        <summary class="flex cursor-pointer list-none items-center justify-between py-3.5 md:pointer-events-none md:mb-3.5 md:py-0 [&::-webkit-details-marker]:hidden">
          <h3 class="font-display text-[12px] font-semibold uppercase tracking-[0.14em] text-gold">Collections</h3>
          <svg class="h-4 w-4 text-gold transition-transform duration-200 group-open/acc:rotate-45 md:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        </summary>
        <ul class="space-y-2.5 pb-4 text-[13px] text-white/75 md:space-y-2 md:pb-0">
          @foreach($navCollections as $collection)
            <li><a class="transition-colors hover:text-gold" href="{{ route('collections.show', $collection['slug']) }}">{{ $collection['name'] }}</a></li>
          @endforeach
        </ul>
      </details>

      <details class="footer-acc group/acc col-span-2 md:col-span-1 lg:col-span-2 border-b border-white/10 md:border-0" data-footer-acc open>
        <summary class="flex cursor-pointer list-none items-center justify-between py-3.5 md:pointer-events-none md:mb-3.5 md:py-0 [&::-webkit-details-marker]:hidden">
          <h3 class="font-display text-[12px] font-semibold uppercase tracking-[0.14em] text-gold">Customer Care</h3>
          <svg class="h-4 w-4 text-gold transition-transform duration-200 group-open/acc:rotate-45 md:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        </summary>
        <ul class="space-y-2.5 pb-4 text-[13px] text-white/75 md:space-y-2 md:pb-0">
          <li><a class="transition-colors hover:text-gold" href="{{ auth()->check() ? route('account.index') : route('login') }}">Track Order</a></li>
          <li><a class="transition-colors hover:text-gold" href="{{ route('wishlist') }}">My Wishlist</a></li>
          <li><a class="transition-colors hover:text-gold" href="{{ route('cart.index') }}">Shopping Bag</a></li>
          <li><a class="transition-colors hover:text-gold" href="{{ route('pages.show', 'return-policy') }}">Return &amp; Exchange Policy</a></li>
          <li><a class="transition-colors hover:text-gold" href="{{ route('pages.show', 'shipping-policy') }}">Shipping &amp; Delivery</a></li>
          <li><a class="transition-colors hover:text-gold" href="{{ route('faq.index') }}">Help &amp; FAQ</a></li>
          <li><a class="transition-colors hover:text-gold" href="{{ route('blogs.index') }}">Journal</a></li>
          <li><a class="transition-colors hover:text-gold" href="{{ route('pages.show', 'privacy-policy') }}">Privacy Policy</a></li>
        </ul>
      </details>

      <details class="footer-acc group/acc col-span-2 md:col-span-4 lg:col-span-2 border-b border-white/10 md:border-0" data-footer-acc open>
        <summary class="flex cursor-pointer list-none items-center justify-between py-3.5 md:pointer-events-none md:mb-3.5 md:py-0 [&::-webkit-details-marker]:hidden">
          <h3 class="font-display text-[12px] font-semibold uppercase tracking-[0.14em] text-gold">Get in Touch</h3>
          <svg class="h-4 w-4 text-gold transition-transform duration-200 group-open/acc:rotate-45 md:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        </summary>
        <div class="space-y-1.5 pb-4 text-[12.5px] text-white/75 md:pb-0">
          <p class="font-semibold text-white">{{ $footerCompany }}</p>
          <p class="leading-normal">{{ $footerAddress }}</p>
          <p><a class="hover:text-gold" href="tel:{{ preg_replace('/\s+/', '', $siteSettings['contact_phone'] ?? '+918247476318') }}">{{ $siteSettings['contact_phone'] ?? '+91 82474 76318' }}</a></p>
          <p><a class="hover:text-gold" href="mailto:{{ $siteSettings['contact_email'] ?? 'info@estele.co' }}">{{ $siteSettings['contact_email'] ?? 'info@estele.co' }}</a></p>
          <p class="text-white/55">{{ $footerHours }}</p>
        </div>
      </details>
    </div>

    <div class="flex flex-wrap items-center gap-x-2 gap-y-2 py-4 text-[11.5px]">
      <span class="mr-1 w-full font-semibold uppercase tracking-[0.14em] text-white/55 md:w-auto">Popular searches</span>
      @foreach($popularSearches as $term)
        <a class="rounded-full border border-white/15 px-3 py-1 text-white/75 transition-colors hover:border-gold hover:text-gold" href="{{ route('search', ['q' => $term]) }}">{{ $term }}</a>
      @endforeach
    </div>
  </div>

  <div class="bg-black/25 pb-4 pt-3 text-[12px] text-white/55 md:pb-4">
    <div class="mx-auto flex w-full max-w-wrapper flex-col items-center justify-between gap-3 px-4 md:px-8 lg:flex-row">
      <p>{{ $siteSettings['footer_copyright'] ?? 'Copyright © 2026 ESTELE Accessories Pvt. Ltd. All rights reserved.' }}</p>
      <div class="flex flex-wrap items-center justify-center gap-1.5">
        @foreach($paymentBadges as $badge)
          <span class="rounded border border-white/15 bg-white/5 px-2 py-0.5 text-[10.5px] font-semibold uppercase tracking-[0.08em] text-white/70">{{ $badge }}</span>
        @endforeach
      </div>
      <div class="flex gap-4">
        <a class="transition-colors hover:text-gold" href="{{ route('pages.show', 'privacy-policy') }}">Privacy Policy</a>
        <a class="transition-colors hover:text-gold" href="{{ route('pages.show', 'return-policy') }}">Terms &amp; Conditions</a>
        <a class="transition-colors hover:text-gold" href="{{ route('pages.show', 'shipping-policy') }}">Shipping Policy</a>
      </div>
    </div>
  </div>
</footer>
