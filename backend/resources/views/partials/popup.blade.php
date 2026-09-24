@if($activePopup)
  <div data-popup data-popup-id="{{ $activePopup['id'] }}" data-popup-trigger="{{ $activePopup['trigger'] }}" data-popup-delay="{{ $activePopup['delay_seconds'] }}" hidden>
    <div class="sheet-backdrop" data-popup-close></div>
    <div class="sheet text-left md:text-center">
      <button class="absolute right-3 top-3 z-[2] grid h-8 w-8 place-items-center rounded-full bg-white/80 text-heading" type="button" data-popup-close aria-label="Close">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 5l14 14M19 5L5 19"/></svg>
      </button>

      @if($activePopup['image_url'])
        <img class="h-[138px] w-full rounded-t-[20px] object-cover md:h-40" src="{{ $activePopup['image_url'] }}" alt="{{ $activePopup['image_alt'] }}">
      @endif

      <div class="px-5 pb-6 pt-7 md:p-6">
        <h2 class="mb-1 pr-8 text-[16px] font-bold text-heading md:pr-0 md:text-[18px]">{{ $activePopup['title'] }}</h2>
        @if($activePopup['body'])
          <p class="mb-5 text-[14px] leading-5 text-[#454545]">{{ $activePopup['body'] }}</p>
        @endif
        @if($activePopup['discount_code'])
          <p class="mb-4 inline-block rounded-[4px] border border-dashed border-accent-dark bg-pinksoft px-3.5 py-2 text-[14px] font-bold tracking-[0.06em] text-accent-dark">{{ $activePopup['discount_code'] }}</p>
        @endif

        @if($activePopup['show_email_field'])
          <form class="flex flex-col gap-2.5" data-popup-newsletter-form action="{{ route('newsletter.subscribe') }}" method="post">
            @csrf
            <input type="hidden" name="popup_id" value="{{ $activePopup['id'] }}">
            <label class="sr-only-custom" for="popup-email">Email address</label>
            <input class="h-14 w-full rounded-[4px] border border-line-strong bg-white px-4 text-[14px] outline-none transition-colors placeholder:text-muted focus:border-heading" id="popup-email" type="email" name="email" placeholder="Enter your email" required>
            <button class="btn-cta text-[14px]" type="submit">{{ $activePopup['cta_label'] ?: 'Subscribe' }}</button>
          </form>
          <p class="mt-3 text-[13px] text-[#428445]" data-popup-newsletter-msg hidden>Thanks for subscribing.</p>
        @elseif($activePopup['cta_url'])
          <a class="btn-cta text-[14px]" href="{{ $activePopup['cta_url'] }}">{{ $activePopup['cta_label'] ?: 'Shop Now' }}</a>
        @endif
      </div>
    </div>
  </div>

  <script>
    (function () {
      var root = document.querySelector('[data-popup]');
      if (!root) return;

      var popupId = root.getAttribute('data-popup-id');
      var dismissKey = 'popup_dismissed_' + popupId;
      // Remembered for a week, not just the tab session: on phones every
      // link opened from social/WhatsApp is a fresh session, so a
      // sessionStorage flag re-showed the popup on almost every visit.
      var SNOOZE_MS = 7 * 24 * 60 * 60 * 1000;
      function isDismissed() {
        try {
          if (sessionStorage.getItem(dismissKey)) return true;
          var at = parseInt(localStorage.getItem(dismissKey), 10);
          return at > 0 && Date.now() - at < SNOOZE_MS;
        } catch (e) { return false; }
      }
      function remember() {
        try {
          sessionStorage.setItem(dismissKey, '1');
          localStorage.setItem(dismissKey, String(Date.now()));
        } catch (e) {}
      }
      if (isDismissed()) return;

      function show() {
        if (isDismissed()) return;
        root.hidden = false;
      }

      function dismiss() {
        root.hidden = true;
        remember();
      }

      root.querySelectorAll('[data-popup-close]').forEach(function (el) {
        el.addEventListener('click', dismiss);
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !root.hidden) dismiss();
      });

      var trigger = root.getAttribute('data-popup-trigger');
      if (trigger === 'exit_intent') {
        function onMouseLeave(e) {
          if (e.clientY <= 0) {
            show();
            document.removeEventListener('mouseleave', onMouseLeave);
          }
        }
        document.addEventListener('mouseleave', onMouseLeave);

        // A phone never fires mouseleave, so an exit-intent popup was simply
        // dead on the site's primary traffic. Approximate the same "about to
        // leave" moment with a decisive upward scroll toward the browser
        // chrome, after the visitor has actually engaged with the page.
        if (!window.matchMedia('(hover: hover)').matches) {
          var lastY = window.scrollY;
          var armed = false;
          function onTouchScroll() {
            var y = window.scrollY;
            if (!armed && y > 400) armed = true;
            if (armed && y < lastY - 60 && y < 200) {
              show();
              window.removeEventListener('scroll', onTouchScroll);
            }
            lastY = y;
          }
          window.addEventListener('scroll', onTouchScroll, { passive: true });
        }
      } else {
        var delaySeconds = parseInt(root.getAttribute('data-popup-delay'), 10) || 4;
        setTimeout(show, delaySeconds * 1000);
      }

      var form = root.querySelector('[data-popup-newsletter-form]');
      if (form) {
        form.addEventListener('submit', function (e) {
          e.preventDefault();
          var csrf = document.querySelector('meta[name="csrf-token"]');
          fetch(form.getAttribute('action'), {
            method: 'POST',
            headers: {
              'Accept': 'application/json',
              'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '',
              'X-Requested-With': 'XMLHttpRequest',
            },
            body: new FormData(form),
          }).then(function () {
            var msg = root.querySelector('[data-popup-newsletter-msg]');
            if (msg) msg.hidden = false;
            form.hidden = true;
            remember();
          });
        });
      }
    })();
  </script>
@endif
