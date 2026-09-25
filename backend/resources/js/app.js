import '../css/app.css';

/* ==========================================================================
   ESTELE — main.js
   Vanilla JS, no dependencies. Every widget is opt-in via a data- attribute,
   so the same file works on every page and does nothing where it isn't needed.
   ========================================================================== */
(function () {
  'use strict';

  var $  = function (sel, ctx) { return (ctx || document).querySelector(sel); };
  var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

  // Shared by the cart drawer and the available-coupons modal — both call
  // the same JSON cart/coupon endpoints, so the fetch+CSRF plumbing lives
  // once at this outer scope instead of being duplicated per widget.
  // Count badges are hidden three different ways across the markup: a `hidden`
  // class (header icons), the `hidden` attribute (bottom-nav tab bar) and an
  // inline display:none (cart). Toggling only the class left the tab bar's
  // wishlist badge permanently invisible, so clear all three here.
  function setBadge(el, count) {
    el.textContent = count;
    var empty = !count;
    el.classList.toggle('hidden', empty);
    if (empty) {
      el.setAttribute('hidden', '');
      el.style.display = 'none';
    } else {
      el.removeAttribute('hidden');
      el.style.display = '';
    }
  }

  // Four overlays (mobile nav, cart drawer, search, coupons modal) all used to
  // write document.body.style.overflow directly. The coupons modal opens from
  // inside the cart drawer, so closing it unlocked scrolling while the drawer
  // was still covering the screen. Keyed locks: the page only scrolls again
  // once every holder has released.
  var scrollLocks = [];
  function lockScroll(key) {
    if (scrollLocks.indexOf(key) === -1) scrollLocks.push(key);
    document.body.style.overflow = 'hidden';
  }
  function unlockScroll(key) {
    var i = scrollLocks.indexOf(key);
    if (i !== -1) scrollLocks.splice(i, 1);
    if (!scrollLocks.length) document.body.style.overflow = '';
  }

  function csrfToken() {
    var meta = $('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function request(url, options) {
    options = options || {};
    options.headers = Object.assign({
      'Accept': 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    }, options.headers || {});
    return fetch(url, options).then(function (res) { return res.json(); });
  }

  /* ------------------------------------------------------------------------
     ZOOM DISABLE
     The <meta viewport maximum-scale=1,user-scalable=no> in layouts/app.blade.php
     covers pinch-zoom on most mobile browsers, but iOS Safari has ignored that
     meta flag since iOS 10 (accessibility), and neither meta tag touches desktop
     ctrl+wheel / ctrl+plus/minus/0 zoom. These three listeners close those gaps;
     always-on, no opt-in data attribute needed since this applies sitewide.
     ---------------------------------------------------------------------- */
  document.addEventListener('wheel', function (e) {
    if (e.ctrlKey) e.preventDefault();
  }, { passive: false });

  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && ['=', '+', '-', '_', '0'].indexOf(e.key) !== -1) {
      e.preventDefault();
    }
  });

  // Safari-only pinch-zoom gesture events (non-standard, but the only hook
  // Safari exposes for this — feature-detected so other browsers no-op here).
  document.addEventListener('gesturestart', function (e) { e.preventDefault(); });
  document.addEventListener('gesturechange', function (e) { e.preventDefault(); });

  // Fallback for touch pinch-zoom on browsers that honor neither the meta
  // tag nor gesture events: block multi-touch moves (pinch is 2+ fingers),
  // single-finger scroll/swipe is left untouched.
  document.addEventListener('touchmove', function (e) {
    if (e.touches.length > 1) e.preventDefault();
  }, { passive: false });

  /* ------------------------------------------------------------------------
     CAROUSEL
     Scroll-snap based: CSS does the layout, JS only moves scrollLeft and
     keeps the arrows/dots in sync. Works with touch swipe for free.
     ---------------------------------------------------------------------- */
  function initCarousel(root) {
    var track = $('[data-carousel-track]', root);
    if (!track) return;

    var prev    = $('[data-carousel-prev]', root);
    var next    = $('[data-carousel-next]', root);
    var dotsBox = $('[data-carousel-dots]', root);
    var slides  = Array.prototype.slice.call(track.children);
    if (!slides.length) return;

    function step() {
      // width of one slide + the gap between slides
      var gap = parseFloat(getComputedStyle(track).columnGap || getComputedStyle(track).gap) || 0;
      return slides[0].getBoundingClientRect().width + gap;
    }

    function maxScroll() {
      return track.scrollWidth - track.clientWidth;
    }

    /* Slides visible at once, so dots represent pages, not individual slides. */
    function slidesPerView() {
      return Math.max(1, Math.round(track.clientWidth / step()));
    }

    function pageIndex() {
      var perView = slidesPerView();
      var pageCount = Math.max(1, Math.ceil(slides.length / perView));
      return Math.min(pageCount - 1, Math.round(track.scrollLeft / (perView * step())));
    }

    /* Loop the tail: if the last page would be partly empty, pad it by
       cloning leading slides onto the end, so every page stays full and
       wraps back to the start instead of trailing off. */
    if (dotsBox) {
      var perViewInit = slidesPerView();
      var remainder = slides.length % perViewInit;
      if (remainder > 0 && slides.length > perViewInit) {
        var needed = perViewInit - remainder;
        for (var c = 0; c < needed; c++) {
          var clone = slides[c].cloneNode(true);
          clone.setAttribute('aria-hidden', 'true');
          Array.prototype.forEach.call(clone.querySelectorAll('a, button, input'), function (el) {
            el.setAttribute('tabindex', '-1');
          });
          track.appendChild(clone);
          slides.push(clone);
        }
      }
    }

    /* data-loop: clone a full extra set of slides onto BOTH ends so prev/next
       can always just move one step in the clicked direction (never jump to
       the opposite end and scroll back across everything). The visible
       scroll range covers: [head clones] [real slides] [tail clones]. Once
       the user scrolls past a full clone set into the far clone zone, snap
       (no animation) back by exactly one real-track-width, landing on the
       equivalent real slide with no visible jump. */
    var loop = root.hasAttribute('data-loop');
    var loopWidth = 0; // width of the real (non-cloned) slides, in px
    var realStart = 0; // scrollLeft of the first real slide
    var realEnd   = 0; // scrollLeft of the first real slide, shifted one loop early
    if (loop) {
      var headClones = slides.map(function (s) {
        var hc = s.cloneNode(true);
        hc.setAttribute('aria-hidden', 'true');
        Array.prototype.forEach.call(hc.querySelectorAll('a, button, input'), function (el) {
          el.setAttribute('tabindex', '-1');
        });
        return hc;
      });
      headClones.forEach(function (hc) { track.insertBefore(hc, track.firstChild); });

      slides.forEach(function (s) {
        var tc = s.cloneNode(true);
        tc.setAttribute('aria-hidden', 'true');
        Array.prototype.forEach.call(tc.querySelectorAll('a, button, input'), function (el) {
          el.setAttribute('tabindex', '-1');
        });
        track.appendChild(tc);
      });

      loopWidth = slides.length * step();
      realStart = loopWidth;
      realEnd   = realStart + loopWidth;

      track.scrollLeft = realStart;
    }

    /* ---- dots (only when asked for), one per page of visible slides ---- */
    var dots = [];
    if (dotsBox) {
      dotsBox.innerHTML = '';
      var perView = slidesPerView();
      var pageCount = Math.max(1, Math.ceil(slides.length / perView));
      for (var p = 0; p < pageCount; p++) {
        (function (p) {
          var b = document.createElement('button');
          b.type = 'button';
          b.className = 'carousel__dot';
          b.setAttribute('aria-label', 'Go to page ' + (p + 1));
          b.addEventListener('click', function () {
            track.scrollTo({ left: p * slidesPerView() * step(), behavior: 'smooth' });
          });
          dotsBox.appendChild(b);
          dots.push(b);
        })(p);
      }
    }

    function sync() {
      var x   = track.scrollLeft;
      var max = maxScroll();

      if (prev) prev.disabled = !loop && x <= 1;
      if (next) next.disabled = !loop && x >= max - 1;

      if (dots.length) {
        var active = pageIndex();
        dots.forEach(function (d, i) {
          d.classList.toggle('is-active', i === active);
        });
      }
    }

    /* Both arrows always just move one step in their own direction. Looping
       is handled separately by snapping the scroll position once it drifts
       into a clone zone (see the scroll listener below) — the button click
       itself never jumps across the track. */
    if (prev) prev.addEventListener('click', function () {
      track.scrollBy({ left: -step(), behavior: 'smooth' });
    });
    if (next) next.addEventListener('click', function () {
      track.scrollBy({ left: step(), behavior: 'smooth' });
    });

    var ticking = false;
    track.addEventListener('scroll', function () {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(function () {
        if (loop) {
          var x = track.scrollLeft;
          /* A full loop-width past the start (into the tail clones) or
             before it (into the head clones): re-anchor by exactly one
             loop-width, landing on the same real slide with no visible
             jump — direction of travel never reverses. */
          if (x >= realEnd) {
            track.scrollLeft = x - loopWidth;
          } else if (x < realStart) {
            track.scrollLeft = x + loopWidth;
          }
        }
        sync();
        ticking = false;
      });
    });
    window.addEventListener('resize', sync);

    /* ---- autoplay (hero only) ---- */
    var delay = parseInt(root.getAttribute('data-autoplay'), 10);
    if (delay > 0) {
      var timer = setInterval(function () {
        if (document.hidden) return;
        if (track.scrollLeft >= maxScroll() - 1) {
          track.scrollTo({ left: 0, behavior: 'smooth' });
        } else {
          track.scrollBy({ left: step(), behavior: 'smooth' });
        }
      }, delay);

      // pause while the user is interacting
      ['mouseenter', 'touchstart', 'focusin'].forEach(function (ev) {
        root.addEventListener(ev, function () { clearInterval(timer); }, { once: true, passive: true });
      });
    }

    sync();
  }

  $$('[data-carousel]:not([data-fade])').forEach(initCarousel);

  /* ------------------------------------------------------------------------
     HERO — cross-fade carousel (matches the live theme's slide-eff-fade;
     scroll-snap doesn't apply here since slides are stacked, not side by side)
     ---------------------------------------------------------------------- */
  (function () {
    var root = $('[data-carousel][data-fade]');
    if (!root) return;

    var slides = $$('[data-carousel-slide]', root);
    var dots   = $$('[data-hero-dot]');
    var prev   = $('[data-hero-prev]', root);
    var next   = $('[data-hero-next]', root);
    if (!slides.length) return;

    var i = 0;

    function show(n) {
      i = (n + slides.length) % slides.length;
      slides.forEach(function (s, idx) { s.classList.toggle('is-active', idx === i); });
      /* Dots sit over the slide artwork, so the active state is a white
         lozenge that widens rather than a dark dot (see home/index). */
      dots.forEach(function (d, idx) {
        d.classList.toggle('w-6', idx === i);
        d.classList.toggle('bg-white', idx === i);
        d.classList.toggle('w-1.5', idx !== i);
        d.classList.toggle('bg-white/55', idx !== i);
      });
    }

    if (prev) prev.addEventListener('click', function () { show(i - 1); });
    if (next) next.addEventListener('click', function () { show(i + 1); });
    dots.forEach(function (d, idx) { d.addEventListener('click', function () { show(idx); }); });

    /* Swipe on touch screens (the arrows are md+ only). A mostly-horizontal
       drag past 40px changes slide; the click that follows a swipe is
       swallowed so it doesn't also open the banner's link. */
    var sx = 0, sy = 0, swiped = false;
    root.addEventListener('touchstart', function (e) {
      sx = e.touches[0].clientX;
      sy = e.touches[0].clientY;
      swiped = false;
    }, { passive: true });
    root.addEventListener('touchend', function (e) {
      var dx = e.changedTouches[0].clientX - sx;
      var dy = e.changedTouches[0].clientY - sy;
      if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy) * 1.5) {
        swiped = true;
        show(dx < 0 ? i + 1 : i - 1);
      }
    }, { passive: true });
    root.addEventListener('click', function (e) {
      if (swiped) { e.preventDefault(); swiped = false; }
    }, true);

    var delay = parseInt(root.getAttribute('data-autoplay'), 10);
    if (delay > 0) {
      /* Wrapped in its own IIFE deliberately, not just an `if` block: this
         whole file runs in non-strict/"sloppy" mode (plain <script src>, no
         "use strict", not an ES module), where a `function` declared inside
         an `if{}` block gets hoisted Annex-B-style as a `var` all the way up
         to the top of the *enclosing function* — not just the block. `root`,
         `show`, `i` etc. above are declared with the outer hero-IIFE's own
         `e`/`t`-named helpers in scope; naming a block-local helper the same
         as something already used earlier in that same outer function body
         (which is exactly what happened here after minification collapsed
         `start`/`stop` down to short, reused single-letter names) shadows it
         for the *entire* outer function, including lines above the
         declaration — silently breaking that earlier code with a
         "such-and-such is not a function" throw. A fresh nested IIFE caps
         `var` hoisting at its own boundary, so nothing here can leak out and
         shadow anything in the hero carousel's outer scope. Confirmed this
         exact failure mode by reproducing it directly in Node before
         settling on this fix. */
      (function () {
        var timer = null;
        var start = function () {
          if (timer) return;
          timer = setInterval(function () {
            if (!document.hidden) show(i + 1);
          }, delay);
        };
        var stop = function () {
          clearInterval(timer);
          timer = null;
        };
        start();
        /* Pause while the user is actively touching/hovering/focusing the
           banner, resume once they let go — NOT a permanent one-time kill.
           An earlier version used {once:true} with no resume listener, so
           on any touch device (real phone or a mobile emulator, which maps
           clicks to synthetic touch events) the very first tap anywhere on
           the banner — even just scrolling past it — silently disabled
           autoplay for the rest of the page's life. */
        ['mouseenter', 'touchstart', 'focusin'].forEach(function (ev) {
          root.addEventListener(ev, stop, { passive: true });
        });
        ['mouseleave', 'touchend', 'touchcancel', 'focusout'].forEach(function (ev) {
          root.addEventListener(ev, start, { passive: true });
        });
      })();
    }
  })();

  /* ------------------------------------------------------------------------
     COLLECTION PAGE — swap title/breadcrumb based on ?cat= so every
     category link (tiles, drawer, footer) lands on a page that matches
     what was actually clicked, instead of always showing Necklace Sets.
     ---------------------------------------------------------------------- */
  (function () {
    var titleEl = $('[data-collection-title]');
    if (!titleEl) return;

    var CATEGORIES = {
      'necklace-sets':  { name: 'Necklace Sets',  desc: 'Handcrafted necklace sets in 24Kt gold plating, finished with anti-tarnish protection. 45 products.' },
      'pendant-sets':   { name: 'Pendant Sets',   desc: 'Shop elegant pendant sets — lightweight, stylish, and perfect for gifting. 38 products.' },
      'earrings':       { name: 'Earrings',       desc: 'Studs, drops and hoops in 24Kt gold plating with anti-tarnish protection. 52 products.' },
      'rings':          { name: 'Finger Rings',   desc: 'Everyday and statement rings finished with anti-tarnish plating. 29 products.' },
      'bracelets':      { name: 'Bracelets',      desc: 'Delicate and statement bracelets in gold, rose gold and silver plating. 24 products.' },
      'bangles':        { name: 'Bangles',        desc: 'Classic and contemporary bangles finished with anti-tarnish protection. 21 products.' },
      'brooch':         { name: 'Brooch Pin',     desc: 'Statement brooch pins to finish off ethnic and festive looks. 12 products.' },
      'chokers':        { name: 'Choker Sets',    desc: 'Bold choker sets in 24Kt gold plating with anti-tarnish protection. 18 products.' },
      'maang-tikka':    { name: 'Maang Tikka',    desc: 'Bridal and festive maang tikkas finished with anti-tarnish plating. 15 products.' },
      'mangalsutra':    { name: 'Mangalsutra',    desc: 'Everyday and statement mangalsutras in gold and rose gold plating. 19 products.' }
    };

    var CATEGORY_PRODUCTS = {"necklace-sets":[{"img":"https://estele.co/cdn/shop/files/01_09e3acd8-c775-4737-83af-365328aec27f.jpg?v=1764929382&width=600","alt":"Peacock CZ Pearl Necklace Set","href":"/products/peacock-cz-pearl-necklace-set","del":"₹2,999","ins":"₹1,500"},{"img":"https://estele.co/cdn/shop/files/11_86c3de45-a3ec-4713-a74b-d30d1695e4eb.jpg?v=1781712345&width=600","alt":"Halo Blossom Necklace Set","href":"/products/halo-blossom-necklace-set-1","del":"₹3,299","ins":"₹1,650"},{"img":"https://estele.co/cdn/shop/files/11_b8d1a7b5-942b-4bb8-b38c-fd2e1909ad66.jpg?v=1781712299&width=600","alt":"Aurora Bloom Necklace Set","href":"/products/aurora-bloom-necklace-set-1","del":"₹3,299","ins":"₹1,650"},{"img":"https://estele.co/cdn/shop/files/12_fe8d0f49-6289-4e61-8810-698f70d4449e.jpg?v=1781712242&width=600","alt":"Prism Petal Necklace Set","href":"/products/prism-petal-necklace-set-1","del":"₹4,799","ins":"₹2,400"},{"img":"https://estele.co/cdn/shop/files/12_e409d360-e584-4a5f-be87-69efe598cef5.jpg?v=1781712159&width=600","alt":"Crystal Aura Necklace Set","href":"/products/crystal-aura-necklace-set","del":"₹4,799","ins":"₹2,400"},{"img":"https://estele.co/cdn/shop/files/12_2256c593-ccc3-4781-877f-689e9dc58f7a.jpg?v=1781712045&width=600","alt":"Crystal Stardust Necklace Set","href":"/products/crystal-stardust-necklace-set","del":"₹4,799","ins":"₹2,400"},{"img":"https://estele.co/cdn/shop/files/12_eb0b0aa8-6d82-4626-a394-6a2907d669bb.jpg?v=1781711979&width=600","alt":"Radiant Blossom Necklace Set","href":"/products/radiant-blossom-necklace-set","del":"₹3,499","ins":"₹1,750"},{"img":"https://estele.co/cdn/shop/files/12_466959ba-b1da-424f-b5b9-e2c5f1297b21.jpg?v=1781711911&width=600","alt":"Crystal Charm Necklace Set","href":"/products/crystal-charm-necklace-set","del":"₹3,499","ins":"₹1,750"}],"pendant-sets":[{"img":"https://estele.co/cdn/shop/files/6116NKER_1.jpg?v=1754737693&width=600","alt":"Gold AD Pendant Set","href":"/products/gold-ad-pendant-set","del":"₹2,699","ins":"₹1,350"},{"img":"https://estele.co/cdn/shop/products/7B3A7001_dceee9e7-bbd0-4bee-bc50-ee5ebc9ab224.jpg?v=1754900982&width=600","alt":"Estele - 24 KT Gold plated square solitaire pendant Set for Women","href":"/products/gold-square-solitaire-pendant-set","del":"₹1,899","ins":"₹950"},{"img":"https://estele.co/cdn/shop/products/AD-002-NKER.jpg?v=1754476745&width=600","alt":"Estele Gold &amp; Rhodium Plated CZ Beautiful Pendant Set for Women","href":"/products/gold-rhodium-cz-pendant-set","del":"₹2,199","ins":"₹1,100"},{"img":"https://estele.co/cdn/shop/products/DSC_4090_de371b54-9a84-4b01-a8b4-64b99c3438d1.jpg?v=1738652598&width=600","alt":"Estele Gold &amp; Rhodium Plated Maple Leaf Designer Necklace Set with Pearl for Women","href":"/products/stylish-matt-gold-and-silver-plated-maple-leaf-pearl-necklace","del":"₹2,999","ins":"₹1,500"},{"img":"https://estele.co/cdn/shop/products/9330-NKER-1.jpg?v=1676528798&width=600","alt":"Estele Gold &amp; Rhodium Plated Trendy Drop Shaped Pendant Set with Emerald Stone for Women / Girls","href":"/products/emerald-drop-pendant-set","del":"₹2,499","ins":"₹1,250"},{"img":"https://estele.co/cdn/shop/products/9492_NKER_1.jpg?v=1694176130&width=600","alt":"Estele Gold Plated Classic Drop Designer Necklace Set with Crystals for Women","href":"/products/classic-drop-crystal-pendant-set","del":"₹3,799","ins":"₹1,900"},{"img":"https://estele.co/cdn/shop/products/7920_NKER_1.jpg?v=1694175840&width=600","alt":"Estele Gold Plated Oval Designer Necklace Set with Crystals for Women","href":"/products/oval-crystal-necklace-set","del":"₹3,299","ins":"₹1,650"},{"img":"https://estele.co/cdn/shop/files/11_acff4f30-c74f-45cb-bcf4-bca5d26bed9d.jpg?v=1704352782&width=600","alt":"Estele Rose Gold Plated CZ Floral Designer Pendant Set for Women","href":"/products/estele-rose-gold-plated-cz-floral-designer-pendant-set-for-women-ad-730-rg-we-pder","del":"₹2,499","ins":"₹1,250"}],"earrings":[{"img":"https://estele.co/cdn/shop/products/61Hs13XyXqL._UL1500.jpg?v=1661339317&width=600","alt":"Estele Rhodium Plated CZ Pearl Drop Hoop Earrings for Women","href":"/products/pearl-drop-cz-hoops","del":"₹649","ins":"₹519"},{"img":"https://estele.co/cdn/shop/files/10239-RGWEERER.jpg?v=1739861540&width=600","alt":"Rosegold Floral Rose Stud Earrings","href":"/products/rosegold-floral-rose-stud-earrings","del":"₹1,099","ins":"₹550"},{"img":"https://estele.co/cdn/shop/files/15_5a52dda9-46c3-4bb9-952a-3dd8502cb6a5.jpg?v=1754483514&width=600","alt":"Jewellery by Estele","href":"/products/estele-rosegold-plated-trendy-circular-stud-earrings-for-girls-and-women","del":"₹549","ins":"₹439"},{"img":"https://estele.co/cdn/shop/products/7B3A9891.jpg?v=1675932132&width=600","alt":"Estele Gold Plated Traditional Kundan Jhumka Earrings for Women","href":"/products/estele-gold-plated-traditional-kundan-jhumka-earrings-for-women","del":"₹899","ins":"₹674"},{"img":"https://estele.co/cdn/shop/files/01_74423695-9954-4084-8040-2932220718ca.jpg?v=1754474630&width=600","alt":"Estele Fancy hanging latest rose gold earrings for women","href":"/products/estele-fancy-hanging-latest-rose-gold-earrings-for-women","del":"₹1,599","ins":"₹800"},{"img":"https://estele.co/cdn/shop/files/609-704ER.jpg?v=1754733829&width=600","alt":"Floral Gold Stud Earrings","href":"/products/floral-gold-stud-earrings","del":"₹499","ins":"₹299"},{"img":"https://estele.co/cdn/shop/files/680-701RG-AQER_2.jpg?v=1761046911&width=600","alt":"Purple Rosegold Charm Earrings","href":"/products/gift-estele-purple-coloured-rose-gold-plated-charms-hanging-earrings-for-women","del":"₹899","ins":"₹539"},{"img":"https://estele.co/cdn/shop/products/Estele09631.jpg?v=1774501602&width=600","alt":"Estele Rose Gold Plated CZ Fascinating Earrings for Women","href":"/products/rosegold-cz-chandbali-earrings","del":"₹4,499","ins":"₹2,250"}],"rings":[{"img":"https://estele.co/cdn/shop/files/Untitled-6_751ef63a-d853-40a5-b2ff-e3a758807241.jpg?v=1776249142&width=600","alt":"Silver Halo Crystal Adjustable Ring","href":"/products/silver-halo-crystal-adjustable-ring","del":"₹999","ins":"₹500"},{"img":"https://estele.co/cdn/shop/files/Untitled-6_930d65be-d9f4-44d9-828f-7a513a45d6b9.jpg?v=1776248283&width=600","alt":"Rose Gold White Crystal Adjustable Ring","href":"/products/rose-gold-white-crystal-adjustable-ring","del":"₹999","ins":"₹500"},{"img":"https://estele.co/cdn/shop/files/Untitled-5_b4e9dcf9-44c3-4522-a31d-7812892e7c16.jpg?v=1776247867&width=600","alt":"Rose Gold Multicolour Baguette Adjustable Ring","href":"/products/rose-gold-multicolour-baguette-adjustable-ring","del":"₹1,199","ins":"₹600"},{"img":"https://estele.co/cdn/shop/files/Untitled-4_081ba177-ea8b-4991-98ab-6403b6ba82cb.jpg?v=1775131093&width=600","alt":"Silver Round Stone Adjustable Ring","href":"/products/silver-round-stone-adjustable-ring","del":"₹999","ins":"₹500"},{"img":"https://estele.co/cdn/shop/files/Untitled-6_94e1b4df-43eb-43c0-9ebb-5d0858970d3d.jpg?v=1776074347&width=600","alt":"Gold Round Stone Adjustable Ring","href":"/products/gold-round-stone-adjustable-ring","del":"₹799","ins":"₹599"},{"img":"https://estele.co/cdn/shop/files/Untitled-6_7fc42b06-1972-4b3f-9e03-3a83b40465eb.jpg?v=1776246763&width=600","alt":"Two-Tone Twisted Chain Adjustable Ring","href":"/products/two-tone-twisted-chain-adjustable-ring","del":"₹799","ins":"₹599"},{"img":"https://estele.co/cdn/shop/files/001_4a78242d-7e53-4cb5-88fa-ee525af7527b.jpg?v=1762943669&width=600","alt":"Contemporary Red Lotus Open Ring","href":"/products/contemporary-red-lotus-open-ring","del":"₹999","ins":"₹500"},{"img":"https://estele.co/cdn/shop/files/001_5933c941-63c6-445f-b605-148be240922b.jpg?v=1762943439&width=600","alt":"Minimal Red Lotus Open Band Ring","href":"/products/minimal-red-lotus-open-band-ring","del":"₹999","ins":"₹500"}],"bracelets":[{"img":"https://estele.co/cdn/shop/files/Untitled-2_f76eb1fe-1fa9-4095-a8e3-d2bfc2a2d08f.jpg?v=1781171856&width=600","alt":"Ziyaan Classic Center Stone Bracelet","href":"/products/ziyaan-classic-center-stone-bracelet","del":"₹1,999","ins":"₹1,000"},{"img":"https://estele.co/cdn/shop/files/Untitled-2_72f77697-6af0-47a1-9e46-66a45cd26a09.jpg?v=1781171740&width=600","alt":"Sira Rectangular Cut Crystal Bracelet","href":"/products/sira-rectangular-cut-crystal-bracelet","del":"₹2,299","ins":"₹1,150"},{"img":"https://estele.co/cdn/shop/files/Untitled-2_9267725e-be3a-4fbd-a8f0-561e055fa775.jpg?v=1781172177&width=600","alt":"Ziya Premium Crystal Cuff Bracelet","href":"/products/ziya-premium-crystal-cuff-bracelet","del":"₹1,999","ins":"₹1,000"},{"img":"https://estele.co/cdn/shop/files/10_e41e6906-8e1d-47c8-bd52-ccecfa47e8d8.jpg?v=1780046470&width=600","alt":"Naaz Lightweight Single Row Bracelet","href":"/products/naaz-lightweight-single-row-bracelet","del":"₹1,499","ins":"₹750"},{"img":"https://estele.co/cdn/shop/files/7_05fb1a1c-c58b-4db9-af34-232215f5df2c.jpg?v=1780046350&width=600","alt":"Inya Radiance Square Tennis Bracelet","href":"/products/inya-radiance-square-tennis-bracelet","del":"₹1,499","ins":"₹750"},{"img":"https://estele.co/cdn/shop/files/Untitled-1_571c42aa-25a7-4bb8-8558-e127950c46d1.jpg?v=1781348649&width=600","alt":"Hoor Prime Square Tennis Bracelet","href":"/products/hoor-prime-square-tennis-bracelet","del":"₹1,999","ins":"₹1,000"},{"img":"https://estele.co/cdn/shop/files/Untitled-1_a9da2b18-5f31-4b15-abd0-c6c2a7a8d32a.jpg?v=1781172358&width=600","alt":"Sitara Lightweight Single Row Bracelet","href":"/products/sitara-lightweight-single-row-bracelet","del":"₹2,499","ins":"₹1,250"},{"img":"https://estele.co/cdn/shop/files/Untitled-1_7528eac7-ad89-49fe-907a-032df6c015be.jpg?v=1781348266&width=600","alt":"Noor Imperial CZ Tennis Bracelet","href":"/products/noor-imperial-cz-tennis-bracelet","del":"₹2,299","ins":"₹1,150"}],"bangles":[{"img":"https://estele.co/cdn/shop/files/582A9037copy_33dce07f-0a67-4fc8-b644-9011ce2c0e1b.jpg?v=1752560819&width=600","alt":"Estele Rhodium Plated Resplendent Ruby American Diamond Floral Bangles |Available in 2:4, 2:6, &amp; 2:8 Sizes|Perfect Blend of Glamour &amp; Elegance for Women","href":"/products/estele-rhodium-plated-cz-floral-designer-bangles-with-ruby-stones-for-women","del":"₹3,999","ins":"₹2,000"},{"img":"https://estele.co/cdn/shop/files/AD-006-IRBANGLE_f2477763-3b9a-455d-8256-1f93b2850c16.jpg?v=1718899206&width=600","alt":"Estele Rhodium Plated Stunning Floral Designer 2:6 Size Bangles with Multi-Color American Diamonds for Women| A Blend of Tradition &amp; Vibrance","href":"/products/estele-rhodium-plated-cz-daisy-flower-shaped-bangles-with-ruby-green-stones-for-women","del":"₹3,899","ins":"₹1,950"},{"img":"https://estele.co/cdn/shop/files/Untitled-1_1271960b-df21-4d66-aee8-09a7e7922a50.jpg?v=1770388461&width=600","alt":"Swarnika – Openable Festive Bangle","href":"/products/swarnika-openable-festive-bangle","del":"₹3,299","ins":"₹1,650"},{"img":"https://estele.co/cdn/shop/files/582A9038copy_14a4bfe5-d016-4383-a5b3-518b0f9bde50.jpg?v=1752561058&width=600","alt":"Rosegold Multicolor Blossom Bangles","href":"/products/estele-rose-gold-plated-cz-flower-designer-bangles-with-green-ruby-stones-for-women","del":"₹3,499","ins":"₹1,750"},{"img":"https://estele.co/cdn/shop/products/5F7A0934.jpg?v=1656005588&width=600","alt":"Estele Gold Plated Alluring Bangle Set with Crystals for Women","href":"/products/estele-gold-plated-alluring-bangle-set-with-crystals-for-women","del":"₹2,499","ins":"₹1,250"},{"img":"https://estele.co/cdn/shop/products/582A4124.jpg?v=1663849545&width=600","alt":"Estele Gold Plated Ravishing Peacock &amp; Flower Designer Bangle with Crystals for Women","href":"/products/estele-gold-plated-ravishing-peacock-flower-designer-bangle-with-crystals-for-women","del":"₹2,499","ins":"₹1,250"},{"img":"https://estele.co/cdn/shop/products/582A4136.jpg?v=1697435358&width=600","alt":"Estele Gold Plated CZ Marvelous Designer Bangle with Green Stones for Women","href":"/products/estele-gold-plated-cz-marvelous-designer-bangle-for-women","del":"₹2,999","ins":"₹1,500"},{"img":"https://estele.co/cdn/shop/products/582A4107.jpg?v=1663850534&width=600","alt":"Estele Rhodium Plated CZ Gorgeous Flower Designer Bangle for Women","href":"/products/estele-rhodium-plated-cz-gorgeous-flower-designer-bangle-for-women","del":"₹2,999","ins":"₹1,500"}],"brooch":[{"img":"https://estele.co/cdn/shop/files/Untitled-1_7489766a-87ff-4a62-8aec-3e726e640d9a.jpg?v=1776504147&width=600","alt":"Mayura – Morbagh CZ Ruby Brooch","href":"/products/mayura-morbagh-ruby-green-brooch","del":"₹1,799","ins":"₹900"},{"img":"https://estele.co/cdn/shop/files/Untitled-1_476511c3-fcbb-4b59-b07a-bd23998fecdd.jpg?v=1776504265&width=600","alt":"Mayurika – Morbagh Green CZ Brooch","href":"/products/mayurika-morbagh-green-cz-brooch","del":"₹1,799","ins":"₹900"},{"img":"https://estele.co/cdn/shop/files/Untitled-1_d8a547c0-fda4-499c-a096-74e9172675f2.jpg?v=1776504376&width=600","alt":"Rajani – Morbagh Blue Green Brooch","href":"/products/rajani-morbagh-blue-green-brooch","del":"₹1,799","ins":"₹900"},{"img":"https://estele.co/cdn/shop/files/Untitled-1_1f66ca5f-9213-4861-a4e2-01009b574178.jpg?v=1776504499&width=600","alt":"Rajvika – Morbagh White CZ Brooch","href":"/products/rajvika-morbagh-white-cz-brooch","del":"₹1,799","ins":"₹900"},{"img":"https://estele.co/cdn/shop/files/Untitled-1_6393b806-92d2-452d-9564-e6e180934576.jpg?v=1776504663&width=600","alt":"Ranjika – Morbagh Mint Pink Brooch","href":"/products/ranjika-morbagh-mint-pink-brooch","del":"₹1,799","ins":"₹900"},{"img":"https://estele.co/cdn/shop/files/Untitled-1_8f404024-2e49-4729-b7d4-ec2ede0ef0dd.jpg?v=1776504762&width=600","alt":"Alankara – Morbagh CZ White Feather Brooch","href":"/products/alankara-morbagh-cz-white-feather-brooch","del":"₹1,999","ins":"₹1,000"},{"img":"https://estele.co/cdn/shop/files/Untitled-1_98bc92d6-0207-4d1c-a2a0-3312e78433a8.jpg?v=1776504858&width=600","alt":"Shringar – Morbagh Ruby Feather Brooch","href":"/products/shringar-morbagh-ruby-feather-brooch","del":"₹1,999","ins":"₹1,000"},{"img":"https://estele.co/cdn/shop/files/Untitled-1_7233314c-07da-46fe-a317-a05d81c19871.jpg?v=1776504982&width=600","alt":"Ratnika – Morbagh Green CZ Feather Brooch","href":"/products/ratnika-morbagh-green-cz-feather-brooch","del":"₹1,999","ins":"₹1,000"}],"chokers":[{"img":"https://estele.co/cdn/shop/products/AD-545-N_E_1.jpg?v=1645873364&width=600","alt":"Estele Rhodium Plated CZ Peacock Designer Bridal Choker Necklace Set with Colored Stones &amp; Pearls for Women","href":"/products/estele-rhodium-plated-cz-peacock-designer-bridal-choker-necklace-set-with-colored-stones-pearls-for-women","del":"₹4,649","ins":"₹3,022"},{"img":"https://estele.co/cdn/shop/files/1_f23360d3-aefb-4a75-8efd-3261450df84c.jpg?v=1742305117&width=600","alt":"Estele Rose Collection Premium Lightweight American Diamond Floral Rose Necklace Set with Luxurious Rosegold FinishA Dazzling Gift of Eternal Love","href":"/products/estele-valentine-special-premium-lightweight-american-diamond-floral-rose-necklace-set-with-luxurious-rosegold-finish-a-dazzling-gift-of-eternal-love","del":"₹4,999","ins":"₹2,500"},{"img":"https://estele.co/cdn/shop/files/Untitled-1_33ed2e2f-7ecd-4454-9a03-cf06faf863ee.jpg?v=1770443289&width=600","alt":"Alankara – Morbagh Green Beaded Peacock Necklace Set","href":"/products/alankara-morbagh-green-beaded-peacock-necklace-set","del":"₹5,999","ins":"₹3,000"},{"img":"https://estele.co/cdn/shop/files/1_c32d17f5-399b-4f3f-af00-79d43c392be4.jpg?v=1742304818&width=600","alt":"Estele Rose Collection Gorgeous Green American Diamond Rose Motif Necklace Set with Luxurious Rosegold Finish A Dazzling Gift of Eternal Love","href":"/products/estele-valentine-special-gorgeous-green-american-diamond-rose-motif-necklace-set-with-luxurious-rosegold-finish-a-dazzling-gift-of-eternal-love","del":"₹4,999","ins":"₹2,500"},{"img":"https://estele.co/cdn/shop/files/1_88986eee-8dda-4247-92fe-de43dde80987.jpg?v=1742305009&width=600","alt":"Estele Rose Collection Luxurious Lightweight White American Diamond Rose Motif Necklace Set with Rosegold FInish A Dazzling Gift of Eternal Love","href":"/products/estele-valentine-special-luxurious-lightweight-white-american-diamond-rose-motif-necklace-set-with-rosegold-finish-a-dazzling-gift-of-eternal-love","del":"₹4,799","ins":"₹2,400"},{"img":"https://estele.co/cdn/shop/files/Untitled-1_7d81eed7-c7fd-443d-9be7-15e8bc78ff0e.jpg?v=1770443168&width=600","alt":"Mayurika – Morbagh Green Beaded Peacock Choker Set","href":"/products/mayurika-morbagh-green-beaded-peacock-choker-set","del":"₹5,799","ins":"₹2,900"},{"img":"https://estele.co/cdn/shop/files/VOIL5338.jpg?v=1742304964&width=600","alt":"Estele Rose Collection Gorgeous American Diamond Classic Floral Rose Necklace Set with Luxurious Rosegold Finish |Perfect for Elegant Occasions","href":"/products/estele-valentine-special-gorgeous-american-diamond-classic-floral-rose-necklace-set-with-luxurious-rosegold-finish-perfect-for-elegant-occasions","del":"₹5,299","ins":"₹2,650"},{"img":"https://estele.co/cdn/shop/files/2_62fb8084-8bee-456d-a822-a66792bf8afd.jpg?v=1742305038&width=600","alt":"Estele Rose Collection Exclusive Rose Gold Finish Intricate Floral Rose Necklace Set with White Crystals-A Dazzling Gift of Eternal Love","href":"/products/estele-valentine-special-exclusive-rosegold-finish-intricate-floral-rose-necklace-set-with-white-crystals-a-dazzling-gift-of-eternal-love","del":"₹4,499","ins":"₹2,250"}],"maang-tikka":[{"img":"https://estele.co/cdn/shop/products/AD-MT-020-RGA_TIKAA_1.jpg?v=1696354345&width=600","alt":"Estele Rose Gold Plated CZ Fascinating Maang Tikka with Ruby Crystals for Women","href":"/products/estele-rose-gold-plated-cz-fascinating-maang-tikka-with-ruby-crystals-for-women-ad-mt-020-rga-tikaa","del":"₹999","ins":"₹500"},{"img":"https://estele.co/cdn/shop/files/030-IGTIKKA.jpg?v=1761040395&width=600","alt":"Gold Plated Traditional Kundan Maang Tikka for Women","href":"/products/gold-plated-traditional-kundan-maang-tikka-for-women","del":"₹1,299","ins":"₹650"},{"img":"https://estele.co/cdn/shop/files/Untitled-1_9eee3a42-d198-4db9-914a-044b3ed266a9.jpg?v=1780565856&width=600","alt":"Timeless Drop Kundan Matte Maang Tikka","href":"/products/timeless-drop-kundan-matte-maang-tikka","del":"₹1,299","ins":"₹650"},{"img":"https://estele.co/cdn/shop/files/003_a4d5899d-9e2f-43cd-97bf-d02fa968b790.jpg?v=1771998371&width=600","alt":"Red Lotus Pearl Maang Tikka","href":"/products/red-lotus-pearl-maang-tikka","del":"₹1,099","ins":"₹550"},{"img":"https://estele.co/cdn/shop/files/AD-MT-024-IRWETIKAA.jpg?v=1766137881&width=600","alt":"Estele Rhodium Plated CZ Dazzling Maang Tikka for Women","href":"/products/estele-rhodium-plated-cz-dazzling-maang-tikka-for-women-ad-mt-024-irwe-tikaa","del":"₹1,699","ins":"₹850"},{"img":"https://estele.co/cdn/shop/files/AD-018IRTIKKA.jpg?v=1765260894&width=600","alt":"Estele Rhodium Plated CZ Peacock Designer Maang Tikka for Women","href":"/products/estele-rhodium-plated-cz-peacock-designer-maang-tikka-for-women","del":"₹1,199","ins":"₹600"},{"img":"https://estele.co/cdn/shop/files/Untitled-1_ed5b8e54-dfd1-4460-a9ac-22a7e88fa2da.jpg?v=1780566021&width=600","alt":"Floral Pearl Drop Matte Maang Tikka","href":"/products/floral-pearl-drop-matte-maang-tikka","del":"₹1,499","ins":"₹750"},{"img":"https://estele.co/cdn/shop/files/Untitled-1_406b1f96-7318-48ed-a056-99bab9fc44cb.jpg?v=1780566167&width=600","alt":"Ruby White Kundan Matte Maang Tikka","href":"/products/ruby-white-kundan-matte-maang-tikka","del":"₹1,299","ins":"₹650"}],"mangalsutra":[{"img":"https://estele.co/cdn/shop/files/1_d20f4a0a-6b18-4b90-aedc-6a816c6a3523.jpg?v=1752570215&width=600","alt":"Heavenly Crystal Mangalsutra Set","href":"/products/heavenly-crystal-mangalsutra-set","del":"₹1,999","ins":"₹1,000"},{"img":"https://estele.co/cdn/shop/products/IMG_9493.jpg?v=1756204665&width=600","alt":"Estele 24 Kt Gold Plated Valley Mangalsutra Necklace Set","href":"/products/traditional-crystal-mangalsutra-set","del":"₹2,299","ins":"₹1,150"},{"img":"https://estele.co/cdn/shop/products/7_83a85db0-d78f-4977-a95b-5e78e2cde658.jpg?v=1649445060&width=600","alt":"Estele 24 Kt Gold Plated Flower Double Line Mangalsutra Necklace Set","href":"/products/double-line-mangalsutra-set","del":"₹2,399","ins":"₹1,200"},{"img":"https://estele.co/cdn/shop/files/Untitled-2_6fbbb758-7d8c-4db2-971c-0cafbf7f7368.jpg?v=1774936742&width=600","alt":"Crystal Mangalsutra Necklace Set","href":"/products/crystal-mangalsutra-necklace-set","del":"₹3,399","ins":"₹1,700"},{"img":"https://estele.co/cdn/shop/files/1_8e0218fa-b0fa-4137-a573-845b07744cf1.jpg?v=1763624728&width=600","alt":"Designer Crystal Mangalsutra Set","href":"/products/designer-crystal-mangalsutra-set","del":"₹2,299","ins":"₹1,150"},{"img":"https://estele.co/cdn/shop/products/1_ad53b092-0975-4338-9491-4b00e9331cd2.jpg?v=1649673655&width=600","alt":"Estele Gold Plated Flower Drop Mangalsutra Necklace Set for Women","href":"/products/flower-drop-mangalsutra-set","del":"₹1,999","ins":"₹1,000"},{"img":"https://estele.co/cdn/shop/files/1_d28dc6bb-ab1b-4d3e-973b-a6ba92cedd5a.jpg?v=1698380318&width=600","alt":"Estele Gold Plated Elegant Designer Mangalsutra Necklace Set with Austrian Crystals for Women","href":"/products/minimalistic-crystal-mangalsutra-necklace-set","del":"₹2,499","ins":"₹1,250"},{"img":"https://estele.co/cdn/shop/files/7B3A6989.jpg?v=1699518113&width=600","alt":"Estele 24 Kt Gold Plated Mangalsutra Necklace Set for Women","href":"/products/traditional-gold-plated-mangalsutra-set","del":"₹2,299","ins":"₹1,150"}]};

    /* Marketing collections (hero banners, "Shop by Collection") don't have
       their own product data yet, so they only swap title/breadcrumb — the
       product grid stays on the page's default set until real per-collection
       assets exist. This is enough to stop every hero slide from landing on
       an indistinguishable page. */
    var COLLECTIONS = {
      'grand-sale':     { name: 'Grand Sale — Flat 50% Off', desc: 'Site-wide savings across necklaces, earrings, bangles and more.' },
      /* Matches estele.co/collections/sitara — reuses the banner image
         already used on the homepage hero slide for this collection. */
      'sitara': {
        name: 'Sitara',
        desc: 'Bringing the brilliance of fashion jewellery to life with refined sparkle and modern elegance—every piece crafted for effortless, everyday luxury.',
        banner: { img: 'https://estele.co/cdn/shop/files/Banner.jpg_2.jpg?width=1800' },
        facetTotal: 54,
        facets: [
          { label: 'Necklace Set', count: 9 },
          { label: 'Choker Set', count: 2 },
          { label: 'Pendant Set', count: 5 },
          { label: 'Pendants', count: 9 },
          { label: 'Bracelets', count: 24 },
          { label: 'Earrings', count: 4 },
          { label: 'Rings', count: 1 }
        ]
      },
      /* Matches estele.co/collections/new-arrivals — no banner or
         description on the live page, straight from H1 to the filter bar. */
      'new-arrivals': {
        name: 'New Arrivals',
        facetTotal: 345,
        facets: [
          { label: 'Necklace Set', count: 141 },
          { label: 'Necklaces', count: 1 },
          { label: 'Choker Set', count: 26 },
          { label: 'Pendant Set', count: 10 },
          { label: 'Bracelets', count: 78 },
          { label: 'Bangles', count: 10 },
          { label: 'Earrings', count: 40 },
          { label: 'Brooch', count: 13 }
        ]
      },
      'featured':       { name: 'Featured Collection',       desc: 'This season’s hand-picked edit.' },
      /* Matches estele.co/collections/wedding-collection — reuses the
         banner image already used on the homepage hero slide. */
      'wedding-season': {
        name: 'Wedding Collection',
        banner: { img: 'https://estele.co/cdn/shop/files/WEDDING_jpg.jpg?width=1800' },
        facetTotal: 134,
        facets: [
          { label: 'Necklace Set', count: 43 },
          { label: 'Choker Set', count: 19 },
          { label: 'Pendant Set', count: 1 },
          { label: 'Bracelets', count: 2 },
          { label: 'Bangles', count: 7 },
          { label: 'Earrings', count: 33 },
          { label: 'Jewelry Sets', count: 16 },
          { label: 'Maang Tikka', count: 9 },
          { label: 'Rings', count: 4 }
        ]
      },
      /* Matches estele.co/collections/rose-collection: banner + tagline text
         and category facet counts taken straight from the live page. */
      'rose': {
        name: 'Rose Collection',
        desc: 'Romantic rose-plated jewellery crafted for timeless charm. Soft tones, feminine designs, effortless sophistication.',
        banner: { img: 'assets/images/rose-collection-banner.webp' },
        facetTotal: 62,
        facets: [
          { label: 'Necklace Set', count: 8 },
          { label: 'Choker Set', count: 8 },
          { label: 'Bracelets', count: 26 },
          { label: 'Earrings', count: 8 },
          { label: 'Rings', count: 12 }
        ]
      },
      /* Matches estele.co/collections/hasli-collection — reuses the banner
         image already used on the homepage hero slide for this collection. */
      'hasli': {
        name: 'Hasli Collection',
        desc: 'Discover the Hasli Collection—a timeless blend of royal heritage and contemporary elegance, crafted to make every celebration unforgettable.',
        banner: { img: 'https://estele.co/cdn/shop/files/Hasli_Collection_Banner-2_jpg.jpg?width=1800' },
        facetTotal: 35,
        facets: [
          { label: 'Necklace Set', count: 17 },
          { label: 'Earrings', count: 18 }
        ]
      },
      /* Matches estele.co/collections/crystal-blooms */
      'crystal-blooms': {
        name: 'Crystal Blooms',
        desc: 'Sparkling crystal jewellery inspired by blooming florals—perfect for special occasions and statement styling.',
        banner: { img: 'assets/images/crystal-blooms-banner.webp' },
        facetTotal: 201,
        facets: [
          { label: 'Necklace Set', count: 121 },
          { label: 'Bracelets', count: 59 },
          { label: 'Bangles', count: 14 },
          { label: 'Rings', count: 7 }
        ]
      },
      'maharani':     { name: 'Maharani Collection',    desc: 'Regal statement pieces with a heavier, bridal-ready finish.' },
      'diamante':     { name: 'Diamante Collection',    desc: 'Sparkling CZ and crystal work for a diamond-like shine.' },
      '9-to-5':       { name: '9 To 5 Collection',      desc: 'Lightweight, understated pieces made for everyday wear.' },
      'kundan-polki': { name: 'Kundan Polki Collection', desc: 'Traditional Kundan and Polki-inspired jewellery for festive occasions.' },
      'pearl':        { name: 'Pearl Collection',       desc: 'Classic pearl jewellery with a soft, timeless finish.' },
      'varya':        { name: 'Varya Collection',       desc: 'Delicate, minimal designs for everyday elegance.' },
      'lotus':        { name: 'Lotus Collection',       desc: 'Floral-inspired pieces drawing on the lotus motif.' },
      'temple':       { name: 'Temple Collection',      desc: 'South Indian temple-style jewellery with intricate detailing.' },
      'colour-pop':   { name: 'Colour Pop Collection',  desc: 'Vibrant coloured stones for a bold pop of colour.' },
      'ever-fine':    { name: 'Ever Fine Collection',   desc: 'Fine, everyday-wear pieces with a refined, subtle finish.' },
      'clover':       { name: 'Clover Collection',      desc: 'Clover and nature-inspired motifs for a playful, feminine look.' }
    };

    var params = new URLSearchParams(window.location.search);
    var slug = params.get('cat');
    var cat = CATEGORIES[slug];

    if (!cat) {
      var collectionSlug = params.get('collection');
      var collection = COLLECTIONS[collectionSlug];
      if (!collection) return;

      titleEl.textContent = collection.name;
      var cDescEl = $('[data-collection-desc]');
      var cCrumbEl = $('[data-collection-crumb]');
      if (cDescEl) {
        if (collection.desc) { cDescEl.textContent = collection.desc; cDescEl.hidden = false; }
        else { cDescEl.hidden = true; }
      }
      if (cCrumbEl) cCrumbEl.textContent = collection.name;
      document.title = collection.name + ' | Estele';

      if (collection.banner) {
        var bannerEl = $('[data-collection-banner]');
        if (bannerEl) {
          $('[data-collection-banner-img]', bannerEl).src = collection.banner.img;
          $('[data-collection-banner-img]', bannerEl).alt = collection.name;
          bannerEl.classList.remove('hidden');
        }
      }

      if (collection.facets) {
        var facetsEl = $('[data-collection-facets]');
        if (facetsEl) {
          $('[data-collection-facet-total]', facetsEl).textContent = collection.facetTotal;
          $('[data-collection-facet-pills]', facetsEl).innerHTML = collection.facets.map(function (f) {
            return '<span class="rounded-md border border-line-strong bg-warmbeige/60 px-3 py-1.5 text-[12px] text-accent-dark font-medium">' + f.label + ' (' + f.count + ')</span>';
          }).join('');
          /* Stays hidden until "Filter" is clicked — see the FILTER DRAWER
             block below, which reveals it alongside the filter sidebar. */
          facetsEl.dataset.filled = 'true';
        }
      }
      return;
    }

    titleEl.textContent = cat.name;
    var descEl  = $('[data-collection-desc]');
    var crumbEl = $('[data-collection-crumb]');
    if (descEl)  descEl.textContent  = cat.desc;
    if (crumbEl) crumbEl.textContent = cat.name;
    document.title = cat.name + ' | Estele';

    var products = CATEGORY_PRODUCTS[slug];
    var grid = $('[data-product-grid]');
    if (grid && products && products.length) {
      grid.innerHTML = products.map(function (p) {
        return '<article class="group relative text-center">' +
          '<a class="relative block aspect-square overflow-hidden bg-placeholder" href="product.html" aria-label="' + p.alt + '">' +
            '<img class="h-full w-full object-cover" src="' + p.img + '" alt="' + p.alt + '" loading="lazy" width="600" height="600">' +
            (p.del ? '<span class="absolute left-2.5 top-2.5 z-[2] flex flex-col gap-1.5"><span class="inline-block bg-salebadge px-2.5 py-1 text-[11px] font-medium uppercase leading-none tracking-[0.3px] text-white">Sale</span></span>' : '') +
            '<button class="absolute right-2.5 top-2.5 z-[2] grid h-[34px] w-[34px] place-items-center rounded-full bg-white opacity-100 md:opacity-0 transition-opacity md:group-hover:opacity-100" type="button" aria-label="Add to wishlist" data-wishlist-toggle>' +
              '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21.2l7.7-7.7 1.1-1.1a5.5 5.5 0 0 0 0-7.8z"/></svg>' +
            '</button>' +
          '</a>' +
          '<div class="pt-3">' +
            '<h3 class="mb-1.5 text-[14px] font-normal leading-normal text-heading"><a class="transition-colors hover:text-accent" href="product.html">' + p.alt + '</a></h3>' +
            '<div class="flex flex-wrap items-center justify-center gap-2">' +
              (p.ins ? '<span class="font-medium text-price">' + p.ins + '</span>' : '') +
              (p.del ? '<span class="text-muted line-through">' + p.del + '</span>' : '') +
            '</div>' +
          '</div>' +
        '</article>';
      }).join('');

      var countEl = $('[data-collection-count]');
      if (countEl) countEl.textContent = 'Showing 1–' + products.length + ' of ' + products.length;
    }
  })();

  /* ------------------------------------------------------------------------
     ANNOUNCEMENT BAR — rotate messages
     ---------------------------------------------------------------------- */
  (function () {
    var bar = $('[data-announcement]');
    if (!bar) return;
    var items = $$('[data-announce-item]', bar);
    if (items.length < 2) return;

    function setActive(el, on) {
      el.classList.toggle('opacity-100', on);
      el.classList.toggle('opacity-0', !on);
      el.classList.toggle('pointer-events-none', !on);
    }

    var i = 0;
    setInterval(function () {
      setActive(items[i], false);
      i = (i + 1) % items.length;
      setActive(items[i], true);
    }, 4000);
  })();

  /* ------------------------------------------------------------------------
     MOBILE DRAWER
     ---------------------------------------------------------------------- */
  (function () {
    var drawer  = $('[data-drawer]');
    if (!drawer) return;
    var backdrop = $('[data-drawer-backdrop]', drawer);
    var panel    = $('[data-drawer-panel]', drawer);
    var burger   = $('[data-menu-open]');
    var isOpen   = false;

    function open() {
      drawer.hidden = false;
      requestAnimationFrame(function () {
        if (backdrop) backdrop.classList.add('opacity-100');
        if (panel) panel.classList.remove('-translate-x-full');
      });
      lockScroll('nav');
      if (burger) burger.setAttribute('aria-expanded', 'true');
      isOpen = true;
    }

    function close() {
      if (backdrop) backdrop.classList.remove('opacity-100');
      if (panel) panel.classList.add('-translate-x-full');
      unlockScroll('nav');
      if (burger) burger.setAttribute('aria-expanded', 'false');
      isOpen = false;
      setTimeout(function () { drawer.hidden = true; }, 300);
    }

    if (burger) burger.addEventListener('click', open);
    $$('[data-menu-close]').forEach(function (el) { el.addEventListener('click', close); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && isOpen) close();
    });
  })();

  /* ------------------------------------------------------------------------
     CART DRAWER — mini-cart that slides in from the right on "Add to Cart"
     and via the header cart icon. The server renders the same Blade partial
     for both this drawer and the full /cart page, so every mutation (add,
     qty change, remove) just fetches fresh HTML for the drawer body instead
     of duplicating cart-item markup in JS.
     ---------------------------------------------------------------------- */
  (function () {
    var drawer = $('[data-cart-drawer]');
    if (!drawer) return;

    var backdrop = $('[data-cart-backdrop]', drawer);
    var panel    = $('[data-cart-panel]', drawer);
    var body     = $('[data-cart-body]', drawer);
    var isOpen   = false;

    function open() {
      drawer.hidden = false;
      requestAnimationFrame(function () {
        if (backdrop) backdrop.classList.add('opacity-100');
        if (panel) panel.classList.remove('translate-x-full');
      });
      lockScroll('cart');
      isOpen = true;
    }

    function close() {
      if (backdrop) backdrop.classList.remove('opacity-100');
      if (panel) panel.classList.add('translate-x-full');
      unlockScroll('cart');
      isOpen = false;
      setTimeout(function () { drawer.hidden = true; }, 300);
    }

    function updateCount(count) {
      $$('[data-cart-count-badge]').forEach(function (badge) {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'grid' : 'none';
      });

      /* --- Floating cart bubble: show when items exist, hide when empty --- */
      var floatingBtn   = document.getElementById('floating-cart-btn');
      var floatingCount = floatingBtn && floatingBtn.querySelector('[data-floating-cart-count]');

      if (floatingBtn) {
        if (count > 0) {
          floatingBtn.hidden = false;
          if (floatingCount) floatingCount.textContent = count;
          /* Slight delay so `hidden` removal causes a repaint before transition */
          requestAnimationFrame(function () {
            floatingBtn.style.transform = 'scale(1)';
            floatingBtn.style.opacity   = '1';
          });
        } else {
          floatingBtn.style.transform = 'scale(0)';
          floatingBtn.style.opacity   = '0';
          setTimeout(function () { floatingBtn.hidden = true; }, 300);
        }
      }
    }

    // Free-shipping progress bar: capture how full it was before the swap
    // so the fill visibly animates from old -> new width instead of just
    // popping to the final value (the CSS transition on width only plays
    // when the property actually changes after paint, not on first set).
    function animateFreeShippingBar() {
      var fill = body && $('[data-free-shipping-fill]', body);
      if (!fill) return;
      var target = fill.style.width;
      var previousFill = drawer.dataset.prevShippingFill;
      fill.style.transition = 'none';
      fill.style.width = previousFill || '0%';
      // Force layout so the browser commits that starting width before the
      // transition is re-enabled and the target width is applied.
      void fill.offsetWidth;
      requestAnimationFrame(function () {
        fill.style.transition = '';
        fill.style.width = target;
      });
      drawer.dataset.prevShippingFill = target;
    }

    function render(html, count) {
      if (body) body.innerHTML = html;
      if (typeof count === 'number') updateCount(count);
      animateFreeShippingBar();
    }

    // Lets other modules (the available-coupons modal, which lives in its
    // own closure with no reference to this drawer's `render`) patch the
    // drawer in place instead of reloading the page.
    document.addEventListener('cart:sync', function (e) {
      render(e.detail.html, e.detail.count);
    });

    $$('[data-cart-open]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        open();
      });
    });

    $$('[data-cart-close]').forEach(function (el) { el.addEventListener('click', close); });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && isOpen) close();
    });

    // Add-to-cart forms (product page) — submit over fetch so the drawer
    // opens with fresh contents instead of a full page reload. Uses its own
    // attribute (not [data-add-to-cart]) because that name is also used
    // below by the static-site button handler — sharing it made the click
    // handler below fire on this <form> and wipe out its contents.
    $$('[data-cart-form]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        var pageLoader = $('[data-page-loader]');
        if (pageLoader) pageLoader.classList.add('is-active');
        if (e.submitter && e.submitter.name === 'express') {
          return;
        }
        e.preventDefault();
        var buyNow = e.submitter && e.submitter.name === 'buy_now';
        // Buttons can belong to the form by the `form=` attribute instead of
        // by nesting — the PDP's mobile buy bar is rendered outside <form> by
        // @yield('sticky_bar'), and is the only way to buy at phone widths.
        // Scoping the re-enable to descendants left it disabled by the
        // sitewide capture guard below, dead until a reload.
        var submitButtons = $$('button[type="submit"]', form);
        if (form.id) {
          $$('button[type="submit"][form="' + form.id + '"]').forEach(function (btn) {
            if (submitButtons.indexOf(btn) === -1) submitButtons.push(btn);
          });
        }

        var navigating = false;

        submitButtons.forEach(function (btn) { btn.disabled = true; });

        request(form.getAttribute('action'), { method: 'POST', body: new FormData(form) })
          .then(function (data) {
            if (data.success === false) {
              if (pageLoader) pageLoader.classList.remove('is-active');
              alert(data.message || 'Could not add to cart.');
              return;
            }
            if (buyNow) {
              navigating = true;
              window.location.href = form.getAttribute('data-checkout-url') || '/checkout';
              return;
            }
            render(data.html, data.cartCount);
            open();
          })
          .finally(function () {
            submitButtons.forEach(function (btn) {
              btn.disabled = false;
              btn.removeAttribute('aria-busy');
            });
            if (pageLoader && !navigating) pageLoader.classList.remove('is-active');
          });
      });
    });

    window.addEventListener('pageshow', function () {
      var pageLoader = $('[data-page-loader]');
      if (pageLoader) pageLoader.classList.remove('is-active');
    });

    // Delegate qty +/- and remove — the drawer body's HTML is replaced
    // wholesale on every update, so per-element listeners would go stale.
    // `busy` blocks overlapping taps: render() below throws away and
    // rebuilds this whole subtree anyway, so a closure flag is enough —
    // no per-button disabled state to track or reset.
    var busy = false;
    var qtyTimers = {};
    var qtySeq = 0;
    if (body) {
      body.addEventListener('click', function (e) {
        var decrement = e.target.closest('[data-cart-qty-decrement]');
        var increment = e.target.closest('[data-cart-qty-increment]');
        var remove    = e.target.closest('[data-cart-remove]');

        if (decrement || increment) {
          var stepper = e.target.closest('[data-cart-qty-stepper]');
          var itemId  = stepper.getAttribute('data-item-id');
          var max     = parseInt(stepper.getAttribute('data-max'), 10) || 1;
          var valueEl = $('[data-cart-qty-value]', stepper);
          var current = parseInt(valueEl.textContent, 10) || 1;
          var next    = increment ? Math.min(max, current + 1) : Math.max(1, current - 1);

          if (next === current) return;

          // The number changes on the tap itself; the server hears about it
          // once the taps stop (one request for "+ + +", not three), and
          // only the newest response is rendered so a slow earlier reply
          // can't roll the number back.
          valueEl.textContent = next;
          clearTimeout(qtyTimers[itemId]);
          qtyTimers[itemId] = setTimeout(function () {
            var mine = ++qtySeq;
            request('/cart/items/' + itemId, {
              method: 'PATCH',
              body: new URLSearchParams({ quantity: next }),
            }).then(function (data) {
              if (data.success === false || mine !== qtySeq) return;
              render(data.html, data.cartCount);
            });
          }, 350);
          return;
        }

        if (busy) return;

        if (remove) {
          busy = true;
          request('/cart/items/' + remove.getAttribute('data-item-id'), { method: 'DELETE' })
            .then(function (data) {
              if (data.success === false) return;
              render(data.html, data.cartCount);
            })
            .finally(function () { busy = false; });
        }

        var couponApply  = e.target.closest('[data-coupon-apply]');
        var couponRemove = e.target.closest('[data-coupon-remove]');

        if (couponApply) {
          var box   = e.target.closest('[data-cart-coupon-box]');
          var input = $('[data-coupon-input]', box);
          var errEl = $('[data-coupon-error]', box);
          var code  = input ? input.value.trim() : '';
          if (!code) return;

          couponApply.disabled = true;
          request('/cart/coupon', {
            method: 'POST',
            body: new URLSearchParams({ code: code }),
          }).then(function (data) {
            if (data.success === false) {
              if (errEl) {
                errEl.textContent = data.message || 'Invalid coupon.';
                errEl.classList.remove('hidden');
              }
              return;
            }
            render(data.html, data.cartCount);
          }).finally(function () {
            couponApply.disabled = false;
          });
        }

        if (couponRemove) {
          busy = true;
          request('/cart/coupon', { method: 'DELETE' }).then(function (data) {
            if (data.success === false) return;
            render(data.html, data.cartCount);
          }).finally(function () { busy = false; });
        }
      });

      // Enter key in the coupon input submits without needing a <form>.
      body.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || !e.target.matches('[data-coupon-input]')) return;
        e.preventDefault();
        var box = e.target.closest('[data-cart-coupon-box]');
        var applyBtn = box && $('[data-coupon-apply]', box);
        if (applyBtn) applyBtn.click();
      });
    }
  })();

  /* Initialise floating-cart visibility on page load using the server-rendered
     count baked into [data-cart-count-badge] by Blade. */
  (function () {
    var badge = document.querySelector('[data-cart-count-badge]');
    var initialCount = badge ? (parseInt(badge.textContent, 10) || 0) : 0;
    if (initialCount > 0) {
      var floatingBtn   = document.getElementById('floating-cart-btn');
      var floatingCount = floatingBtn && floatingBtn.querySelector('[data-floating-cart-count]');
      if (floatingBtn) {
        floatingBtn.hidden = false;
        if (floatingCount) floatingCount.textContent = initialCount;
        requestAnimationFrame(function () {
          floatingBtn.style.transform = 'scale(1)';
          floatingBtn.style.opacity   = '1';
        });
      }
    }
  })();

  /* ------------------------------------------------------------------------
     FOOTER ACCORDIONS — link groups collapse on phones, stay open as plain
     columns from md up. Rendered open server-side so they work without JS.
     ---------------------------------------------------------------------- */
  (function () {
    var groups = $$('[data-footer-acc]');
    if (!groups.length || !window.matchMedia) return;
    var desktop = window.matchMedia('(min-width: 768px)');
    function sync() {
      groups.forEach(function (d) { d.open = desktop.matches; });
    }
    sync();
    if (desktop.addEventListener) desktop.addEventListener('change', sync);
  })();

  /* ------------------------------------------------------------------------
     SEARCH OVERLAY
     ---------------------------------------------------------------------- */
  (function () {
    var overlay = $('[data-search]');
    if (!overlay) return;

    function open() {
      overlay.hidden = false;
      var input = $('input', overlay);
      if (input) input.focus();
      lockScroll('search');
    }
    function close() {
      overlay.hidden = true;
      unlockScroll('search');
    }

    $$('[data-search-open]').forEach(function (el) { el.addEventListener('click', open); });
    $$('[data-search-close]').forEach(function (el) { el.addEventListener('click', close); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !overlay.hidden) close();
    });
  })();

  /* ------------------------------------------------------------------------
     AVAILABLE COUPONS MODAL — server-rendered once in the layout (not
     fetched), opened from any "View all coupons" link on the cart/checkout
     pages or the cart drawer. "Apply" applies the coupon immediately (only
     one can ever be applied at a time — applying a new one just replaces
     whatever was there). On the /cart and /checkout pages, their own
     server-rendered summary block (subtotal/discount/coupon box) also needs
     the new numbers, which only a reload gives us cheaply. Everywhere else
     (e.g. modal opened from the drawer while browsing) there's no such
     block to worry about, so we just sync the drawer in place via the
     `cart:sync` event and keep it open, instead of reloading the page out
     from under the user.
     ---------------------------------------------------------------------- */
  (function () {
    var modal = $('[data-coupons-modal]');
    if (!modal) return;

    function open() {
      modal.hidden = false;
      lockScroll('coupons');
    }
    function close() {
      modal.hidden = true;
      unlockScroll('coupons');
    }

    // Delegated on document, not attached directly to each opener element:
    // the cart drawer's "View all coupons" button lives inside HTML that gets
    // wholesale-replaced on every cart update (see the CART DRAWER block
    // above), so a directly-attached listener would go stale after the first
    // add-to-cart/qty-change/coupon action.
    document.addEventListener('click', function (e) {
      if (e.target.closest('[data-coupons-modal-open]')) open();
      if (e.target.closest('[data-coupons-modal-close]')) close();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !modal.hidden) close();
    });

    modal.addEventListener('click', function (e) {
      var applyBtn = e.target.closest('[data-coupons-modal-apply]');
      if (!applyBtn) return;

      var code = applyBtn.getAttribute('data-coupons-modal-apply');
      var original = applyBtn.textContent;
      applyBtn.disabled = true;
      applyBtn.textContent = 'Applying...';

      request('/cart/coupon', {
        method: 'POST',
        body: new URLSearchParams({ code: code }),
      }).then(function (data) {
        if (data.success === false) {
          applyBtn.disabled = false;
          applyBtn.textContent = original;
          alert(data.message || 'Could not apply this coupon.');
          return;
        }
        if (/^\/(cart|checkout)(\/|$)/.test(window.location.pathname)) {
          window.location.reload();
          return;
        }
        document.dispatchEvent(new CustomEvent('cart:sync', { detail: { html: data.html, count: data.cartCount } }));
        close();
      });
    });
  })();

  /* ------------------------------------------------------------------------
     STICKY HEADER SHADOW + BACK TO TOP
     ---------------------------------------------------------------------- */
  (function () {
    var header = $('[data-header]');
    var toTop  = $('[data-to-top]');
    if (!header && !toTop) return;

    var lastY = window.scrollY;

    function onScroll() {
      var y = window.scrollY;
      if (header) {
        header.classList.toggle('shadow-[0_2px_12px_rgba(0,0,0,0.06)]', y > 4);
        header.classList.toggle('is-stuck', y > 4);
        if (Math.abs(y - lastY) > 6) {
          header.classList.toggle('is-hidden', y > lastY && y > 160);
          lastY = y;
        }
      }
      if (toTop) {
        toTop.classList.toggle('opacity-100', y > 500);
        toTop.classList.toggle('opacity-0', y <= 500);
        toTop.classList.toggle('pointer-events-none', y <= 500);
        toTop.classList.toggle('translate-y-0', y > 500);
        toTop.classList.toggle('translate-y-2.5', y <= 500);
      }
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    if (toTop) toTop.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  })();

  /* ------------------------------------------------------------------------
     READ MORE / READ LESS (brand story, long descriptions)
     ---------------------------------------------------------------------- */
  $$('[data-clamp-toggle]').forEach(function (btn) {
    var body = $('[data-clamp]', btn.closest('section') || document);
    if (!body) return;

    body.classList.add('is-clamped');
    btn.addEventListener('click', function () {
      var clamped = body.classList.toggle('is-clamped');
      btn.textContent = clamped ? 'Read more' : 'Read less';
    });
  });

  /* ------------------------------------------------------------------------
     NEWSLETTER — front-end only, no backend wired up yet
     ---------------------------------------------------------------------- */
  $$('[data-newsletter]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var msg = $('[data-newsletter-msg]', form.parentNode);
      if (msg) msg.hidden = false;
      form.reset();
    });
  });

  /* ------------------------------------------------------------------------
     SEARCH AUTOCOMPLETE — every [data-search-autocomplete] form (desktop/
     mobile header, search overlay, /search page itself) gets a debounced
     dropdown of live matches from GET /search/suggest, keyed off the same
     Product::search() call the results page uses. Degrades to a plain GET
     form submit if JS fails or the endpoint errors — no behavior lost.
     ---------------------------------------------------------------------- */
  $$('[data-search-autocomplete]').forEach(function (form) {
    var input = $('input[name="q"]', form);
    var box   = $('[data-search-suggestions]', form);
    if (!input || !box) return;

    var debounceTimer  = null;
    var activeIndex     = -1;
    var currentResults  = [];
    var currentRequest   = 0;

    function hide() {
      box.hidden = true;
      box.classList.add('hidden');
      box.innerHTML = '';
      activeIndex = -1;
    }

    function render(results) {
      currentResults = results;
      activeIndex = -1;
      if (!results.length) { hide(); return; }

      box.innerHTML = results.map(function (item, i) {
        return '<a class="flex items-center gap-3 px-3 py-2.5 text-[13px] text-heading transition-colors hover:bg-warmbeige" href="' + item.url + '" data-suggestion-index="' + i + '">' +
          (item.thumbnail ? '<img class="h-9 w-9 shrink-0 rounded object-cover" src="' + item.thumbnail + '" alt="" loading="lazy">' : '') +
          '<span class="min-w-0 flex-1 truncate">' + item.title + '</span>' +
          '<span class="shrink-0 text-price">₹' + Math.round(item.price).toLocaleString('en-IN') + '</span>' +
        '</a>';
      }).join('');
      box.hidden = false;
      box.classList.remove('hidden');
    }

    function fetchSuggestions(query) {
      var requestId = ++currentRequest;
      fetch('/search/suggest?q=' + encodeURIComponent(query), { headers: { 'Accept': 'application/json' } })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (requestId !== currentRequest) return; // a newer keystroke's request already landed
          render(data.results || []);
        })
        .catch(function () { /* silent — plain form submit still works */ });
    }

    input.addEventListener('input', function () {
      var query = input.value.trim();
      clearTimeout(debounceTimer);
      if (query.length < 2) { hide(); return; }
      debounceTimer = setTimeout(function () { fetchSuggestions(query); }, 200);
    });

    input.addEventListener('keydown', function (e) {
      if (box.hidden) return;
      var links = $$('a', box);
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIndex = Math.min(activeIndex + 1, links.length - 1);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIndex = Math.max(activeIndex - 1, -1);
      } else if (e.key === 'Enter' && activeIndex >= 0) {
        e.preventDefault();
        window.location.href = currentResults[activeIndex].url;
        return;
      } else if (e.key === 'Escape') {
        hide();
        return;
      } else {
        return;
      }
      links.forEach(function (a, i) { a.classList.toggle('bg-warmbeige', i === activeIndex); });
    });

    document.addEventListener('click', function (e) {
      if (!form.contains(e.target)) hide();
    });
  });

  /* ------------------------------------------------------------------------
     QUANTITY STEPPER (product page, cart)
     ---------------------------------------------------------------------- */
  $$('[data-qty]').forEach(function (box) {
    var input = $('input[type="number"]', box);
    if (!input || box.closest('[data-cart-qty-form]')) return;

    function bump(by) {
      var min = parseInt(input.getAttribute('min'), 10) || 1;
      var maxAttr = input.getAttribute('max');
      var max = maxAttr ? parseInt(maxAttr, 10) : null;
      var current = parseInt(input.value, 10) || min;
      var val = current + by;
      val = Math.max(min, val);
      if (max !== null) val = Math.min(max, val);
      // Clamped to where it already was (minus at 1, plus at max): firing
      // change anyway made the cart page submit and do a full reload that
      // changed nothing.
      if (val === current) return;
      input.value = val;
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    var minus = $('[data-qty-minus]', box);
    var plus  = $('[data-qty-plus]', box);
    if (minus) minus.addEventListener('click', function () { bump(-1); });
    if (plus)  plus.addEventListener('click',  function () { bump(1); });
  });

  /* ------------------------------------------------------------------------
     ORDER NOTE — the bag page's note box sits outside any form, so what the
     shopper typed there used to be discarded on the way to checkout. Carry it
     across and prefill checkout's real order_note field (which does submit).
     ---------------------------------------------------------------------- */
  (function () {
    var KEY = 'estele:order-note';
    $$('[data-order-note]').forEach(function (el) {
      var submits = !!el.name;
      if (!submits) {
        try { if (!el.value) el.value = sessionStorage.getItem(KEY) || ''; } catch (err) {}
        el.addEventListener('input', function () {
          try { sessionStorage.setItem(KEY, el.value); } catch (err) {}
        });
        return;
      }
      try {
        if (!el.value) {
          var carried = sessionStorage.getItem(KEY);
          if (carried) el.value = carried;
        }
      } catch (err) {}
      var form = el.form;
      if (form) {
        form.addEventListener('submit', function () {
          try { sessionStorage.removeItem(KEY); } catch (err) {}
        });
      }
    });
  })();

  /* ------------------------------------------------------------------------
     CART PAGE QTY — the number changes on the tap itself. Once the taps
     stop, one PATCH saves the final quantity and the page's totals are
     refreshed in place from a fresh copy of /cart (no full reload, no
     loader). Delegated, because that refresh replaces the rows.
     ---------------------------------------------------------------------- */
  (function () {
    if (!$('[data-cart-qty-form]')) return;
    var timers = {};
    var seq = 0;

    function refreshCart(mine) {
      return fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (res) { return res.text(); })
        .then(function (html) {
          if (mine !== seq) return;
          var doc = new DOMParser().parseFromString(html, 'text/html');
          var freshMain = doc.querySelector('main');
          var main = document.querySelector('main');
          var note = $('[data-order-note]', main);
          var noteValue = note ? note.value : null;
          if (freshMain && main) main.innerHTML = freshMain.innerHTML;
          var freshNote = $('[data-order-note]', main);
          if (freshNote && noteValue !== null) freshNote.value = noteValue;
          var freshBar = doc.querySelector('.buybar');
          var bar = document.querySelector('.buybar');
          if (freshBar && bar) bar.outerHTML = freshBar.outerHTML;
          else if (bar && !freshBar) bar.remove();
          var badge = doc.querySelector('[data-cart-count-badge]');
          if (badge) {
            var count = parseInt(badge.textContent, 10) || 0;
            $$('[data-cart-count-badge]').forEach(function (b) {
              b.textContent = count;
              b.style.display = count > 0 ? 'grid' : 'none';
            });
          }
        });
    }

    function save(form, qty) {
      var key = form.getAttribute('action');
      clearTimeout(timers[key]);
      timers[key] = setTimeout(function () {
        var mine = ++seq;
        var body = new FormData(form);
        body.set('quantity', qty);
        fetch(key, {
          method: 'POST',
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: body,
        }).then(function () { return refreshCart(mine); })
          .catch(function () { form.submit(); });
      }, 350);
    }

    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-cart-qty-form] [data-qty-minus], [data-cart-qty-form] [data-qty-plus]');
      if (!btn) return;
      var form = btn.closest('[data-cart-qty-form]');
      var input = $('input[type="number"]', form);
      var min = parseInt(input.getAttribute('min'), 10) || 1;
      var max = parseInt(input.getAttribute('max'), 10) || 99;
      var current = parseInt(input.value, 10) || min;
      var next = Math.max(min, Math.min(max, current + (btn.hasAttribute('data-qty-plus') ? 1 : -1)));
      if (next === current) return;
      input.value = next;
      save(form, next);
    });

    document.addEventListener('change', function (e) {
      var input = e.target.closest('[data-cart-qty-form] input[type="number"]');
      if (!input) return;
      save(input.form, Math.max(1, parseInt(input.value, 10) || 1));
    });

    document.addEventListener('submit', function (e) {
      if (e.target.matches && e.target.matches('[data-cart-qty-form]')) e.preventDefault();
    }, true);

    // The bag's order note is re-rendered by the refresh above, so keep
    // carrying what's typed there to checkout (see ORDER NOTE).
    document.addEventListener('input', function (e) {
      var note = e.target.closest && e.target.closest('[data-order-note]:not([name])');
      if (!note) return;
      try { sessionStorage.setItem('estele:order-note', note.value); } catch (err) {}
    });
  })();

  /* ------------------------------------------------------------------------
     PRODUCT GALLERY — thumbnail swaps the main image
     ---------------------------------------------------------------------- */
  (function () {
    var main = $('#pdp-main-img');
    var thumbs = $$('[data-gallery-thumb]');
    if (!main || !thumbs.length) return;

    function setActive(el, on) {
      el.classList.toggle('border-accent', on);
      el.classList.toggle('border-transparent', !on);
    }

    thumbs.forEach(function (thumb) {
      thumb.addEventListener('click', function () {
        var full = thumb.getAttribute('data-full');
        if (!full) return;

        main.removeAttribute('srcset');   // srcset would override the new src
        main.src = full;

        thumbs.forEach(function (t) { setActive(t, false); });
        setActive(thumb, true);
      });
    });
  })();

  /* ------------------------------------------------------------------------
     FILTER PANEL (collection page) — hidden until "Filter" is clicked, then
     revealed inline above the category pills. Not a slide-in drawer.
     ---------------------------------------------------------------------- */
  (function () {
    var filters = $('[data-filters]');
    if (!filters) return;
    var facetsEl = $('[data-collection-facets]');

    $$('[data-filters-open]').forEach(function (el) {
      el.addEventListener('click', function () {
        filters.hidden = false;
        if (facetsEl && facetsEl.dataset.filled === 'true') facetsEl.hidden = false;
      });
    });
  })();

  /* ------------------------------------------------------------------------
     FACET FILTERS (collection page) — narrows the product grid to cards
     matching every checked facet group (price / plating / stone colour /
     occasion). Within a group, checked options are OR'd together; across
     groups, results are AND'd.
     ---------------------------------------------------------------------- */
  (function () {
    var grid = $('[data-product-grid]');
    var priceBoxes = $$('[data-filter-price]');
    var platingBoxes = $$('[data-filter-plating]');
    var stoneBoxes = $$('[data-filter-stone]');
    var occasionBoxes = $$('[data-filter-occasion]');
    var allBoxes = priceBoxes.concat(platingBoxes, stoneBoxes, occasionBoxes);
    if (!grid || !allBoxes.length) return;

    var countEl = $('[data-collection-count]');
    var clearBtn = $('[data-filters-clear]');
    var originalCountText = countEl ? countEl.textContent : '';

    function parsePrice(card) {
      var el = $('.text-price', card);
      if (!el) return null;
      var digits = el.textContent.replace(/[^0-9]/g, '');
      return digits ? parseInt(digits, 10) : null;
    }

    function checkedValues(boxes, attr) {
      return boxes.filter(function (b) { return b.checked; }).map(function (b) {
        return b.getAttribute(attr);
      });
    }

    function apply() {
      var priceRanges = checkedValues(priceBoxes, 'data-filter-price').map(function (v) {
        var parts = v.split('-');
        return { min: parseInt(parts[0], 10), max: parseInt(parts[1], 10) };
      });
      var platings = checkedValues(platingBoxes, 'data-filter-plating');
      var stones = checkedValues(stoneBoxes, 'data-filter-stone');
      var occasions = checkedValues(occasionBoxes, 'data-filter-occasion');

      var activeCount = priceRanges.length + platings.length + stones.length + occasions.length;
      var cards = Array.prototype.slice.call(grid.children);
      var visible = 0;

      cards.forEach(function (card) {
        var price = parsePrice(card);
        var matchesPrice = !priceRanges.length || (price != null && priceRanges.some(function (r) {
          return price >= r.min && price <= r.max;
        }));
        var matchesPlating = !platings.length || platings.indexOf(card.getAttribute('data-plating')) !== -1;
        var matchesStone = !stones.length || stones.indexOf(card.getAttribute('data-stone')) !== -1;
        var matchesOccasion = !occasions.length || occasions.indexOf(card.getAttribute('data-occasion')) !== -1;

        var show = matchesPrice && matchesPlating && matchesStone && matchesOccasion;
        card.classList.toggle('hidden', !show);
        if (show) visible++;
      });

      if (countEl) {
        countEl.textContent = activeCount ?
          visible + (visible === 1 ? ' result' : ' results') :
          originalCountText;
      }
    }

    allBoxes.forEach(function (b) { b.addEventListener('change', apply); });

    if (clearBtn) clearBtn.addEventListener('click', function () {
      allBoxes.forEach(function (b) { b.checked = false; });
      apply();
    });
  })();

  /* ------------------------------------------------------------------------
     SUPPORT CHAT — floating bubble + chat window (layouts/app.blade.php).
     Front-end only: scripted replies, no backend and no live agent. Every
     message shows its own time ("Estele Assistant • 12:13 PM" / "Sent •
     12:13 PM") under a day separator ("Today • 12:12 PM"). The conversation
     is kept in sessionStorage for this tab, so it survives page changes.
     ---------------------------------------------------------------------- */
  (function () {
    var tabBtn = document.getElementById('chat-tab-btn');
    var panel = document.getElementById('chat-full-panel');
    if (!tabBtn || !panel) return;

    var closeBtn = document.getElementById('chat-close-btn');
    var scroller = $('[data-chat-scroll]', panel);
    var log = $('[data-chat-log]', panel);
    var form = $('[data-chat-form]', panel);
    var input = document.getElementById('chat-input');
    var sendBtn = $('.chatw__send', panel);
    var menuBtn = $('[data-chat-menu-btn]', panel);
    var menu = $('[data-chat-menu]', panel);

    var cfg = panel.dataset;
    var AGENT = cfg.agent;
    var STORE_KEY = 'estele-chat';
    var MAX_MESSAGES = 80;
    var CHIPS = ['Track my order', 'Returns & exchange', 'Size guide', 'Talk to our team'];

    var state = load();
    var queue = [];
    var busy = false;
    var replyTimer = null;
    var lastDay = null;
    var chipsEl = null;

    function load() {
      try {
        var saved = JSON.parse(sessionStorage.getItem(STORE_KEY) || 'null');
        if (saved && Array.isArray(saved.messages) && saved.messages.length) return saved;
      } catch (e) { /* private mode / blocked storage: start fresh */ }
      return null;
    }

    function save() {
      try { sessionStorage.setItem(STORE_KEY, JSON.stringify(state)); } catch (e) { /* ignore */ }
    }

    function forget() {
      try { sessionStorage.removeItem(STORE_KEY); } catch (e) { /* ignore */ }
    }

    /* ---- Time labels ---------------------------------------------------- */
    function timeLabel(ts) {
      return new Date(ts).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    }

    function dayKey(ts) {
      var d = new Date(ts);
      return d.getFullYear() + '-' + d.getMonth() + '-' + d.getDate();
    }

    function dayLabel(ts) {
      var now = new Date();
      if (dayKey(ts) === dayKey(now)) return 'Today';
      var yesterday = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1);
      if (dayKey(ts) === dayKey(yesterday)) return 'Yesterday';
      return new Date(ts).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    /* ---- Rendering -------------------------------------------------------- */
    function el(tag, cls, text) {
      var node = document.createElement(tag);
      if (cls) node.className = cls;
      if (text != null) node.textContent = text;
      return node;
    }

    function avatar(extraClass) {
      var img = el('img', 'chatw__avatar' + (extraClass ? ' ' + extraClass : ''));
      img.src = cfg.avatar;
      img.alt = '';
      return img;
    }

    var typingRow = el('div', 'chatw__row chatw__row--bot chatw__row--typing');
    typingRow.setAttribute('aria-hidden', 'true');
    typingRow.appendChild(avatar());
    var dots = el('div', 'chatw__typing');
    dots.appendChild(el('span'));
    dots.appendChild(el('span'));
    dots.appendChild(el('span'));
    typingRow.appendChild(dots);

    function scrollToEnd() {
      scroller.scrollTop = scroller.scrollHeight;
    }

    /* Bot text is only ever written by this script. Links are real anchors
       built from {label, href}, and all text goes in via textContent. */
    function fillBubble(bubble, msg) {
      bubble.textContent = msg.text;
      (msg.links || []).forEach(function (link) {
        bubble.appendChild(document.createElement('br'));
        var a = el('a', null, link.label);
        a.href = link.href;
        bubble.appendChild(a);
      });
    }

    function renderMsg(msg, isLast) {
      if (msg.type === 'join') {
        var join = el('p', 'chatw__join');
        join.appendChild(avatar('chatw__avatar--xs'));
        join.appendChild(document.createTextNode(AGENT + ' joined • ' + timeLabel(msg.ts)));
        return join;
      }

      var isBot = msg.type === 'bot';
      var row = el('div', 'chatw__row ' + (isBot ? 'chatw__row--bot' : 'chatw__row--user'));
      if (isBot) row.appendChild(avatar());

      var stack = el('div', 'chatw__stack');
      var bubble = el('div', 'chatw__bubble');
      fillBubble(bubble, msg);
      stack.appendChild(bubble);
      stack.appendChild(el('p', 'chatw__meta', (isBot ? AGENT : 'Sent') + ' • ' + timeLabel(msg.ts)));

      row.appendChild(stack);

      /* Quick replies only under the newest message; they go once the
         conversation moves on. Kept outside the row so the avatar stays
         level with the bubble. */
      if (!isBot || !isLast || !msg.chips) return row;

      chipsEl = el('div', 'chatw__chips');
      msg.chips.forEach(function (label) {
        var chip = el('button', 'chatw__chip', label);
        chip.type = 'button';
        chip.addEventListener('click', function () { handleUserText(label); });
        chipsEl.appendChild(chip);
      });
      var group = document.createDocumentFragment();
      group.appendChild(row);
      group.appendChild(chipsEl);
      return group;
    }

    function append(msg, isLast) {
      if (chipsEl) {
        chipsEl.remove();
        chipsEl = null;
      }
      var day = dayKey(msg.ts);
      if (day !== lastDay) {
        log.appendChild(el('p', 'chatw__sep', dayLabel(msg.ts) + ' • ' + timeLabel(msg.ts)));
        lastDay = day;
      }
      log.appendChild(renderMsg(msg, isLast));
    }

    function renderAll() {
      log.innerHTML = '';
      lastDay = null;
      chipsEl = null;
      state.messages.forEach(function (msg, i) {
        append(msg, i === state.messages.length - 1);
      });
      if (busy) log.appendChild(typingRow);
      scrollToEnd();
    }

    function push(type, text, extra) {
      var msg = { type: type, text: text, ts: Date.now() };
      if (extra && extra.links) msg.links = extra.links;
      if (extra && extra.chips) msg.chips = extra.chips;
      state.messages.push(msg);

      if (state.messages.length > MAX_MESSAGES) {
        state.messages.splice(0, state.messages.length - MAX_MESSAGES);
        save();
        renderAll();
        return;
      }

      save();
      append(msg, true);
      if (busy) log.appendChild(typingRow);
      scrollToEnd();
    }

    /* Replies queue up so two quick messages each get their answer, in
       order, each after a short "typing…" pause. */
    function botSay(text, extra) {
      queue.push({ text: text, extra: extra });
      if (!busy) nextReply();
    }

    function nextReply() {
      var item = queue.shift();
      if (!item) {
        busy = false;
        typingRow.remove();
        return;
      }
      busy = true;
      log.appendChild(typingRow);
      scrollToEnd();
      replyTimer = setTimeout(function () {
        push('bot', item.text, item.extra);
        nextReply();
      }, 700 + Math.min(item.text.length * 6, 900));
    }

    function stopReplies() {
      clearTimeout(replyTimer);
      queue = [];
      busy = false;
      typingRow.remove();
    }

    /* ---- Conversation ----------------------------------------------------- */
    var GREETING = /^(hi+|hey+|hello+|helo|hlo|namaste|namaskar|hola|yo|good\s+(morning|afternoon|evening))[\s!.,]*$/i;
    var LEADING_GREETING = /^(hi+|hey+|hello+|helo|hlo|namaste|namaskar)\b[\s!.,]*/i;
    var NAME_PREFIX = /^(my\s+name\s+is|my\s+name's|name\s+is|i\s+am|i'm|im|this\s+is|it's|its|call\s+me)\s+/i;
    var THANKS = /^(ok(ay)?|thanks?|thank\s*you|thanku|thx|ty|great|cool|nice|bye|goodbye)\b/i;

    function contactLinks() {
      return [
        { label: '📞 ' + cfg.phone, href: cfg.phoneHref },
        { label: '✉️ ' + cfg.email, href: 'mailto:' + cfg.email }
      ];
    }

    /* First match wins: returns come before orders so "cancel my order"
       gets the returns answer, not the order-status one. */
    var INTENTS = [
      {
        test: /\b(returns?|exchanges?|refunds?|replace(ment)?|cancel(lation)?)\b/i,
        reply: function () {
          return ['We accept returns and exchanges within 7 days of delivery, as long as the item is unused and in its original packaging. You can raise a request from the order in My Account.', { links: [{ label: 'View my orders', href: cfg.ordersUrl }] }];
        }
      },
      {
        test: /\b(track|tracking|orders?|deliver(y|ed)?|shipping|shipped|dispatch(ed)?|courier|parcel)\b/i,
        reply: function () {
          return ['You can see the status of every order in My Account → My Orders.', { links: [{ label: 'View my orders', href: cfg.ordersUrl }] }];
        }
      },
      {
        test: /\b(sizes?|length|adjustable|fit|measure(ment)?s?)\b/i,
        reply: function () {
          return ['Most of our necklaces are adjustable. Tell us the piece you are looking at and we will share exact measurements.'];
        }
      },
      {
        test: /\b(talk|call|contact|support|human|agent|person|team|phone|number|email|mail|whatsapp)\b/i,
        reply: function () {
          return ['You can reach our team (' + cfg.hours + '):', { links: contactLinks() }];
        }
      }
    ];

    function matchIntent(text) {
      for (var i = 0; i < INTENTS.length; i++) {
        if (INTENTS[i].test.test(text)) return INTENTS[i].reply();
      }
      return null;
    }

    function extractName(text) {
      var t = text.trim().replace(LEADING_GREETING, '').replace(NAME_PREFIX, '').replace(/[.!,]+$/, '').trim();
      if (!t || /[0-9@#$%^&*()_+=<>?\/\\|{}\[\]~`"]/.test(t)) return null;
      var words = t.split(/\s+/);
      if (words.length > 3 || t.length > 40) return null;
      return words.map(function (w) {
        return w.charAt(0).toUpperCase() + w.slice(1).toLowerCase();
      }).join(' ');
    }

    function withName(prefix) {
      return state.name ? prefix + ', ' + state.name.split(' ')[0] : prefix;
    }

    function answer(reply) {
      botSay(reply[0], reply[1]);
    }

    function fallback() {
      botSay('Thanks for your message! I can help with orders, returns and sizing. For anything else, our team is happy to help (' + cfg.hours + '):', { links: contactLinks(), chips: CHIPS });
    }

    function handleUserText(text) {
      text = text.trim();
      if (!text) return;
      push('user', text);

      var intent = matchIntent(text);

      if (!state.name && !state.nameSkipped) {
        if (intent) {
          state.nameSkipped = true;
          save();
          answer(intent);
          return;
        }
        if (GREETING.test(text) || THANKS.test(text)) {
          botSay('Hi there! May I know your name, please?');
          return;
        }
        var name = extractName(text);
        if (name) {
          state.name = name;
          save();
          botSay('Nice to meet you, ' + name + '! 😊\nHow can I help you today?', { chips: CHIPS });
          return;
        }
        if (text.split(/\s+/).length <= 3) {
          botSay('Sorry, I didn\'t catch that. May I know your name, please?');
          return;
        }
        state.nameSkipped = true;
        save();
        fallback();
        return;
      }

      if (intent) {
        answer(intent);
      } else if (GREETING.test(text)) {
        botSay(withName('Hello again') + '! How can I help you today?', { chips: CHIPS });
      } else if (THANKS.test(text) && text.split(/\s+/).length <= 4) {
        botSay(withName('You\'re welcome') + '! Is there anything else I can help you with?', { chips: CHIPS });
      } else {
        fallback();
      }
    }

    function start() {
      stopReplies();
      state = { name: null, nameSkipped: false, messages: [] };
      log.innerHTML = '';
      lastDay = null;
      chipsEl = null;
      push('join', '');
      botSay('Hello! Greetings from ' + cfg.site + ' 👋\nMay I know your name please?');
    }

    /* ---- Window ------------------------------------------------------------ */
    /* Phones: keep the window above the on-screen keyboard, whose height
       is the part of the layout viewport the visual viewport no longer
       covers. */
    function fitKeyboard() {
      var vv = window.visualViewport;
      if (!vv) return;
      var keyboard = Math.max(0, Math.round(window.innerHeight - vv.height - vv.offsetTop));
      panel.style.setProperty('--chat-kb', keyboard + 'px');
      if (keyboard) scrollToEnd();
    }

    if (window.visualViewport) {
      window.visualViewport.addEventListener('resize', fitKeyboard);
      window.visualViewport.addEventListener('scroll', fitKeyboard);
    }

    function isOpen() {
      return panel.classList.contains('is-open');
    }

    function openChat() {
      closeMenu();
      panel.hidden = false;
      requestAnimationFrame(function () { panel.classList.add('is-open'); });
      tabBtn.setAttribute('aria-expanded', 'true');
      if (!state) start(); else renderAll();
      fitKeyboard();
      /* Don't pop the keyboard up on a phone just for opening the window. */
      if (window.matchMedia('(min-width: 768px)').matches) {
        setTimeout(function () { input.focus({ preventScroll: true }); }, 250);
      }
    }

    function closeChat() {
      closeMenu();
      panel.classList.remove('is-open');
      tabBtn.setAttribute('aria-expanded', 'false');
      input.blur();
      setTimeout(function () {
        if (!isOpen()) panel.hidden = true;
      }, 250);
    }

    function closeMenu() {
      menu.hidden = true;
      menuBtn.setAttribute('aria-expanded', 'false');
    }

    tabBtn.addEventListener('click', function () {
      if (isOpen()) closeChat(); else openChat();
    });
    closeBtn.addEventListener('click', closeChat);

    menuBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var willOpen = menu.hidden;
      menu.hidden = !willOpen;
      menuBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });

    $('[data-chat-restart]', panel).addEventListener('click', function () {
      closeMenu();
      forget();
      start();
    });

    $('[data-chat-end]', panel).addEventListener('click', function () {
      stopReplies();
      forget();
      state = null;
      closeChat();
    });

    document.addEventListener('click', function (e) {
      if (!menu.hidden && !menu.contains(e.target)) closeMenu();
    });

    input.addEventListener('input', function () {
      sendBtn.disabled = !input.value.trim();
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var text = input.value;
      input.value = '';
      sendBtn.disabled = true;
      handleUserText(text);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape' || !isOpen()) return;
      if (!menu.hidden) closeMenu(); else closeChat();
    });
  })();

  /* ------------------------------------------------------------------------
     STICKY PURCHASE BAR — shows floating purchase bar on mobile when scrolled past main CTA
     ---------------------------------------------------------------------- */
  (function () {
    var stickyBar = $('[data-sticky-buy]');
    var mainCta = $('[data-add-to-cart]');
    if (!stickyBar || !mainCta) return;

    function updateStickyBar() {
      if (window.innerWidth >= 768) {
        stickyBar.classList.add('hidden');
        return;
      }
      var ctaRect = mainCta.getBoundingClientRect();
      // Show sticky bar when main CTA moves towards/above the top of viewport
      if (ctaRect.bottom < 120) {
        stickyBar.classList.remove('hidden');
      } else {
        stickyBar.classList.add('hidden');
      }
    }

    window.addEventListener('scroll', updateStickyBar, { passive: true });
    window.addEventListener('resize', updateStickyBar);
    updateStickyBar();
  })();

  /* ------------------------------------------------------------------------
     SWATCHES — visual selection only (no cart logic on the front end)
     ---------------------------------------------------------------------- */
  var SWATCH_ON  = ['border-heading', 'font-medium', 'text-heading'];
  var SWATCH_OFF = ['border-line-strong', 'text-muted'];

  $$('[data-swatch-group]').forEach(function (group) {
    var swatches = $$('[data-swatch]', group);

    function setActive(el, on) {
      var add = on ? SWATCH_ON : SWATCH_OFF;
      var rem = on ? SWATCH_OFF : SWATCH_ON;
      rem.forEach(function (c) { el.classList.remove(c); });
      add.forEach(function (c) { el.classList.add(c); });
    }

    swatches.forEach(function (sw) {
      sw.addEventListener('click', function () {
        swatches.forEach(function (s) { setActive(s, false); });
        setActive(sw, true);
      });
    });
  });

  /* ------------------------------------------------------------------------
     PRODUCT CARD IMAGE SCROLLER — true infinite loop via CSS transform track.
     Clones the last image before position-0 and the first image at the end.
     Track slides with translateX. When we land on a clone we instantly
     teleport (no transition) to the matching real slide — zero visible jump,
     no stopping at edges. Works on touch and mouse drag.
     --------------------------------------------------------------------- */
  function initCardScroller(scroller) {
    var dotsBox = scroller.parentElement && $('[data-card-dots]', scroller.parentElement);
    if (!dotsBox) return;

    var origImgs  = [].slice.call(scroller.querySelectorAll('.card-scroller__img'));
    var realCount = origImgs.length;
    if (realCount < 2) return;

    /* Build a flex track inside the scroller. */
    scroller.style.overflow = 'hidden';
    scroller.style.position = 'relative';

    var track = document.createElement('div');
    track.style.cssText =
      'display:flex;width:100%;height:100%;' +
      'transition:transform .38s cubic-bezier(.25,.46,.45,.94);' +
      'will-change:transform;';

    /* Move real images into the track with full-size flex styles. */
    function styleSlide(img) {
      img.style.flex       = '0 0 100%';
      img.style.width      = '100%';
      img.style.height     = '100%';
      img.style.objectFit  = 'cover';
      img.style.flexShrink = '0';
    }
    origImgs.forEach(function (img) { styleSlide(img); track.appendChild(img); });

    /* Sentinel clones: last-image clone at head, first-image clone at tail. */
    var cloneHead = origImgs[realCount - 1].cloneNode(true);
    var cloneTail = origImgs[0].cloneNode(true);
    cloneHead.setAttribute('aria-hidden', 'true');
    cloneTail.setAttribute('aria-hidden', 'true');
    styleSlide(cloneHead); styleSlide(cloneTail);
    track.insertBefore(cloneHead, track.firstChild);
    track.appendChild(cloneTail);

    scroller.appendChild(track);

    /* current is 1-based: 0 = cloneHead, 1..realCount = real, realCount+1 = cloneTail */
    var current  = 1;
    var dotItems = [].slice.call(dotsBox.children);

    function setPos(idx, animate) {
      if (!animate) track.style.transition = 'none';
      track.style.transform = 'translateX(-' + (idx * 100) + '%)';
      if (!animate) { void track.offsetWidth; track.style.transition = 'transform .38s cubic-bezier(.25,.46,.45,.94)'; }
    }

    function syncDots(realIdx) {
      dotItems.forEach(function (d, i) { d.classList.toggle('is-active', i === realIdx); });
    }

    setPos(current, false);
    syncDots(0);

    /* After each animated move, jump from clone to real counterpart. */
    track.addEventListener('transitionend', function () {
      if (current === 0) {
        current = realCount;          // cloneHead → real last
        setPos(current, false);
      } else if (current === realCount + 1) {
        current = 1;                  // cloneTail → real first
        setPos(current, false);
      }
      syncDots(current - 1);
    });

    function goTo(idx) { current = idx; setPos(current, true); }

    /* --- Drag / swipe (touch, pen and mouse) ----------------------------
       The photo follows the finger while dragging and settles on release,
       looping endlessly in both directions. touch-action: pan-y keeps
       vertical page scrolling native, while a sideways swipe on a photo
       moves only the photos — not a carousel row it sits in. */
    scroller.style.touchAction = 'pan-y';
    [].slice.call(track.querySelectorAll('img')).forEach(function (img) {
      img.setAttribute('draggable', 'false');
    });

    var down = false, axis = null, sx = 0, sy = 0, dx = 0, width = 1, dragged = false;

    function moveTo(n) {
      if (n > realCount + 1) n = realCount + 1;
      if (n < 0) n = 0;
      goTo(n);
    }

    scroller.addEventListener('pointerdown', function (e) {
      if (e.pointerType === 'mouse' && e.button !== 0) return;
      down = true; axis = null; dx = 0; dragged = false;
      sx = e.clientX; sy = e.clientY;
      width = scroller.clientWidth || 1;
    });

    scroller.addEventListener('pointermove', function (e) {
      if (!down) return;
      var mx = e.clientX - sx, my = e.clientY - sy;
      if (axis === null && (Math.abs(mx) > 6 || Math.abs(my) > 6)) {
        axis = Math.abs(mx) > Math.abs(my) ? 'x' : 'y';
        if (axis === 'x') {
          try { scroller.setPointerCapture(e.pointerId); } catch (err) {}
          track.style.transition = 'none';
        }
      }
      if (axis !== 'x') return;
      dx = mx; dragged = true;
      track.style.transform = 'translateX(calc(-' + (current * 100) + '% + ' + dx + 'px))';
    });

    function release() {
      if (!down) return;
      down = false;
      if (axis !== 'x') return;
      track.style.transition = 'transform .38s cubic-bezier(.25,.46,.45,.94)';
      if (Math.abs(dx) > Math.min(60, width * 0.15)) moveTo(dx < 0 ? current + 1 : current - 1);
      else setPos(current, true);
    }
    scroller.addEventListener('pointerup', release);
    scroller.addEventListener('pointercancel', release);
    // The card is a link, and browsers start dragging the link itself on a
    // mouse drag (cancelling our pointer stream) — so turn that off.
    var link = scroller.closest('a');
    if (link) {
      link.setAttribute('draggable', 'false');
      link.addEventListener('dragstart', function (e) { e.preventDefault(); });
    }

    /* A drag must not also open the product. */
    scroller.addEventListener('click', function (e) {
      if (dragged) { e.preventDefault(); e.stopPropagation(); dragged = false; }
    }, true);

    /* --- Dots jump straight to a photo -------------------------------- */
    dotItems.forEach(function (dot, i) {
      dot.addEventListener('click', function (e) {
        e.preventDefault(); e.stopPropagation();
        goTo(i + 1);
      });
    });
  }

  $$('[data-card-scroller]').forEach(initCardScroller);


  /* ------------------------------------------------------------------------
     WISHLIST PAGE — the server sends the whole active catalogue because saved
     items live only in this browser (see ProductController::wishlist). This
     block drops every card the visitor never saved, then marks the survivors'
     hearts as filled. Must run before the generic WISHLIST toggle block so
     that handler reads the same localStorage this one just filtered against.
     ---------------------------------------------------------------------- */
  (function () {
    var grid = $('[data-wishlist-grid]');
    if (!grid) return;

    var emptyEl = $('[data-wishlist-empty]');
    var cards = $$('article', grid);

    var saved = [];
    try { saved = JSON.parse(localStorage.getItem('estele-wishlist') || '[]'); } catch (e) {}

    function keyFor(card) {
      var img = card.querySelector('img');
      return (img && (img.getAttribute('alt') || img.getAttribute('src'))) || card;
    }

    cards.forEach(function (card) {
      if (saved.indexOf(keyFor(card)) === -1) {
        card.remove();
        return;
      }

      var btn = $("[data-wishlist-toggle]", card);
      if (btn) {
        btn.classList.add('opacity-100', 'text-accent');
        var svg = btn.querySelector('svg');
        if (svg) svg.setAttribute('fill', 'currentColor');
      }
    });

    function updateEmptyState() {
      var remaining = grid.querySelectorAll('article').length;
      grid.hidden = remaining === 0;
      if (emptyEl) emptyEl.hidden = remaining !== 0;
    }
    updateEmptyState();

    grid.addEventListener('click', function (e) {
      var btn = e.target.closest("[data-wishlist-toggle]");
      if (!btn) return;

      var card = btn.closest('article');
      if (!card) return;

      // Deferred so the shared toggle handler (bound on document) finishes
      // reading e.target before the card is removed from the DOM.
      setTimeout(function () {
        card.remove();
        updateEmptyState();
      }, 0);
    });
  })();

  /* ------------------------------------------------------------------------
     WISHLIST — heart toggle on product cards, persisted per-browser so the
     header count is consistent across pages
     ---------------------------------------------------------------------- */
  (function () {
    var countEls = $$('[data-wishlist-count]');
    if (!countEls.length) return;

    var saved = [];
    try { saved = JSON.parse(localStorage.getItem('estele-wishlist') || '[]'); } catch (e) {}

    function render() {
      countEls.forEach(function (el) { setBadge(el, saved.length); });
    }
    render();

    document.addEventListener('click', function (e) {
      var btn = e.target.closest("[data-wishlist-toggle]");
      if (!btn) return;
      e.preventDefault();
      e.stopPropagation();

      var card = btn.closest('article, li, [data-product-grid] > *');
      var img  = card && card.querySelector('img');
      var key  = btn.getAttribute('data-wishlist-key') || (img && (img.getAttribute('alt') || img.getAttribute('src'))) || btn;
      var idx  = saved.indexOf(key);
      var on   = idx === -1;

      if (on) saved.push(key); else saved.splice(idx, 1);
      try { localStorage.setItem('estele-wishlist', JSON.stringify(saved)); } catch (e2) {}

      btn.classList.toggle('opacity-100', on);
      btn.classList.toggle('text-accent', on);
      var svg = btn.querySelector('svg');
      if (svg) svg.setAttribute('fill', on ? 'currentColor' : 'none');

      render();
    });
  })();

  /* ------------------------------------------------------------------------
     ADD TO CART — bumps the header badge, persisted per-browser so the
     count is consistent across pages (mirrors the wishlist pattern above).
     Front-end only: no cart storage/checkout wired up yet.
     ---------------------------------------------------------------------- */
  (function () {
    var countEls = $$('[data-cart-count]');
    var addButtons = $$('[data-add-to-cart]');
    if (!countEls.length) return;

    var count = 0;
    try { count = parseInt(localStorage.getItem('estele-cart-count'), 10) || 0; } catch (e) {}

    function render() {
      countEls.forEach(function (el) { setBadge(el, count); });
    }
    render();

    if (!addButtons.length) return;

    addButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var qtyInput = btn.parentElement && $('[data-qty] input[type="number"]', btn.parentElement);
        var qty = (qtyInput && parseInt(qtyInput.value, 10)) || 1;

        count += qty;
        try { localStorage.setItem('estele-cart-count', String(count)); } catch (e2) {}
        render();

        var original = btn.textContent;
        btn.textContent = 'Added!';
        btn.disabled = true;
        setTimeout(function () {
          btn.textContent = original;
          btn.disabled = false;
        }, 1200);
      });
    });
  })();

  /* ------------------------------------------------------------------------
     CART PAGE — remove items and keep row/order-summary totals and the
     header badge in sync (front-end only, no persisted cart storage).
     ---------------------------------------------------------------------- */
  (function () {
    var table = $('[data-cart-table]');
    if (!table) return;

    var grid = $('[data-cart-grid]');
    var emptyEl = $('[data-cart-empty]');
    var subtotalEl = $('[data-cart-subtotal]');
    var totalEl = $('[data-cart-total]');
    var countEls = $$('[data-cart-count]');
    var rows = $$('[data-cart-row]', table);

    function currency(n) {
      return '₹' + n.toLocaleString('en-IN');
    }

    function bumpCartCount(delta) {
      var count = 0;
      try { count = parseInt(localStorage.getItem('estele-cart-count'), 10) || 0; } catch (e) {}
      count = Math.max(0, count + delta);
      try { localStorage.setItem('estele-cart-count', String(count)); } catch (e2) {}
      countEls.forEach(function (el) {
        el.textContent = count;
        el.classList.toggle('hidden', count === 0);
      });
    }

    function rowQty(row) {
      var input = $('input[type="number"]', row);
      return (input && parseInt(input.value, 10)) || 1;
    }

    function recalc() {
      var subtotal = 0;
      var remaining = 0;

      rows.forEach(function (row) {
        if (!row.isConnected) return;
        remaining++;

        var price = parseFloat(row.getAttribute('data-price')) || 0;
        var rowTotal = price * rowQty(row);
        var totalCell = $('[data-row-total]', row);
        if (totalCell) totalCell.textContent = currency(rowTotal);
        subtotal += rowTotal;
      });

      if (subtotalEl) subtotalEl.textContent = currency(subtotal);
      if (totalEl) totalEl.textContent = currency(subtotal);

      if (grid) grid.hidden = remaining === 0;
      if (emptyEl) emptyEl.hidden = remaining !== 0;
    }

    rows.forEach(function (row) {
      var removeBtn = $('[data-cart-remove]', row);
      if (removeBtn) {
        removeBtn.addEventListener('click', function () {
          bumpCartCount(-rowQty(row));
          row.remove();
          recalc();
        });
      }

      var qtyInput = $('input[type="number"]', row);
      if (qtyInput) qtyInput.addEventListener('input', recalc);
    });

    recalc();
  })();

  /* ------------------------------------------------------------------------
     EXPLORE MORE — grids capped to 4 rows (via CSS nth-child per breakpoint)
     expand in place on click instead of paginating.
     ---------------------------------------------------------------------- */
  $$('[data-explore]').forEach(function (wrap) {
    var btn = $('[data-explore-toggle]', wrap);
    var grid = $('[data-explore-grid]', wrap);
    if (!btn || !grid) return;

    btn.addEventListener('click', function () {
      var expanded = grid.classList.toggle('is-expanded');
      btn.textContent = expanded ? (btn.dataset.lessLabel || 'Show less') : (btn.dataset.moreLabel || 'Explore more');
      btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    });
  });

  /* ------------------------------------------------------------------------
     ACTIVE NAV — highlight the current page in the mobile bottom tab bar
     ---------------------------------------------------------------------- */
  (function () {
    var tabbar = $('nav[aria-label="Quick navigation"]');
    if (!tabbar) return;

    var here = location.pathname.split('/').pop() || 'index.html';
    $$('a', tabbar).forEach(function (a) {
      var href = (a.getAttribute('href') || '').split('/').pop();
      if (href && href === here) {
        a.classList.add('text-accent');
        a.setAttribute('aria-current', 'page');
      }
    });
  })();

  /* ------------------------------------------------------------------------
     SUBMIT LOADING DOTS — opt-in via data-loading-submit on a <form>. These
     are plain full-page-reload POSTs (OTP login), so this doesn't skip the
     request — it just swaps the submit button's label for 3 bouncing dots
     immediately so the page feels responsive during the round trip, and
     disables the button to block a double submit.
     ---------------------------------------------------------------------- */
  $$('[data-loop]').forEach(function (root) {
    var stage = $('[data-loop-stage]', root);
    var track = $('[data-loop-track]', root);
    if (!stage || !track) return;

    var originals = $$('.loop__item', track);
    var count = originals.length;
    if (count < 2) return;

    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var step = 0;
    var setWidth = 0;
    var x = 0;
    var vel = 0;
    var target = null;
    var dragging = false;
    var startX = 0;
    var startOffset = 0;
    var moved = 0;
    var lastX = 0;
    var lastT = 0;
    var nextAuto = performance.now() + 3500;
    var hovering = false;
    var last = performance.now();

    function addSet() {
      originals.forEach(function (item) {
        var clone = item.cloneNode(true);
        clone.setAttribute('aria-hidden', 'true');
        clone.setAttribute('tabindex', '-1');
        track.appendChild(clone);
      });
    }

    function layout() {
      var visible = window.innerWidth >= 768 ? 7 : 4;
      var gap = parseFloat(getComputedStyle(stage).getPropertyValue('--loop-gap')) || 10;
      var width = (stage.clientWidth - gap * (visible - 1)) / visible;
      stage.style.setProperty('--loop-w', width + 'px');
      root.style.setProperty('--loop-arrow-y', width / 2 + 'px');
      step = width + gap;
      setWidth = step * count;
      while (track.children.length * step < stage.clientWidth + setWidth * 2) addSet();
      x = Math.round(x / step) * step;
      target = null;
    }

    addSet();
    layout();
    window.addEventListener('resize', layout);

    function snapped() {
      return Math.round((target !== null ? target : x) / step) * step;
    }

    function move(direction) {
      vel = 0;
      target = snapped() - direction * step;
      nextAuto = performance.now() + 5000;
    }

    function frame(now) {
      var dt = Math.min(now - last, 100);
      last = now;
      if (!dragging) {
        if (vel) {
          x += vel * dt;
          vel *= Math.pow(0.92, dt / 16);
          if (Math.abs(vel) < 0.05) {
            vel = 0;
            target = Math.round(x / step) * step;
          }
        } else if (target !== null) {
          x += (target - x) * Math.min(1, dt / 110);
          if (Math.abs(target - x) < 0.4) {
            x = target;
            target = null;
          }
        } else if (!hovering && !reduce && now > nextAuto) {
          target = snapped() - step;
          nextAuto = now + 3500;
        }
      }
      if (setWidth) {
        var wrapped = ((x % setWidth) + setWidth) % setWidth - setWidth;
        if (target !== null) target += wrapped - x;
        x = wrapped;
      }
      track.style.transform = 'translate3d(' + x.toFixed(2) + 'px,0,0)';
      requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);

    var prev = $('[data-loop-prev]', root);
    var next = $('[data-loop-next]', root);
    if (prev) prev.addEventListener('click', function () { move(-1); });
    if (next) next.addEventListener('click', function () { move(1); });

    stage.addEventListener('pointerdown', function (e) {
      if (e.button) return;
      dragging = true;
      vel = 0;
      target = null;
      startX = lastX = e.clientX;
      startOffset = x;
      moved = 0;
      lastT = performance.now();
    });
    document.addEventListener('pointermove', function (e) {
      if (!dragging) return;
      var now = performance.now();
      var dx = e.clientX - startX;
      moved = Math.max(moved, Math.abs(dx));
      x = startOffset + dx;
      if (now - lastT > 0) vel = Math.max(-3, Math.min(3, (e.clientX - lastX) / (now - lastT)));
      lastX = e.clientX;
      lastT = now;
    });
    function release() {
      if (!dragging) return;
      dragging = false;
      if (performance.now() - lastT > 80 || Math.abs(vel) < 0.05) {
        vel = 0;
        target = Math.round(x / step) * step;
      }
      nextAuto = performance.now() + 5000;
    }
    document.addEventListener('pointerup', release);
    document.addEventListener('pointercancel', release);
    root.addEventListener('mouseenter', function () { hovering = true; });
    root.addEventListener('mouseleave', function () { hovering = false; });
    stage.addEventListener('click', function (e) {
      if (moved > 8) {
        e.preventDefault();
        e.stopPropagation();
      }
    }, true);
  });

  $$('[data-sheet-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var sheet = $('[data-sheet="' + btn.getAttribute('data-sheet-open') + '"]');
      if (!sheet) return;
      sheet.hidden = false;
      document.body.classList.add('is-locked');
    });
  });
  $$('[data-sheet-close]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var sheet = btn.closest('[data-sheet]');
      if (sheet) sheet.hidden = true;
      document.body.classList.remove('is-locked');
    });
  });

  $$('[data-filter-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var panel = $('[data-filter-panel]');
      if (!panel) return;
      panel.open = true;
      panel.classList.add('is-sheet-open');
      document.body.classList.add('is-locked');
    });
  });
  $$('[data-filter-close]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var panel = btn.closest('[data-filter-panel]');
      if (panel) panel.classList.remove('is-sheet-open');
      document.body.classList.remove('is-locked');
    });
  });

  // Both mobile sheets used to be dismissible only by their small X. The sort
  // sheet has a real backdrop element, but the filter panel's dim is a
  // box-shadow spread and so cannot be clicked — tapping outside it did
  // nothing. Escape closes either, and a tap outside the filter form closes it.
  function closeMobileSheets() {
    var closed = false;
    $$('[data-sheet]').forEach(function (sheet) {
      if (!sheet.hidden) { sheet.hidden = true; closed = true; }
    });
    $$('[data-filter-panel].is-sheet-open').forEach(function (panel) {
      panel.classList.remove('is-sheet-open');
      closed = true;
    });
    if (closed) document.body.classList.remove('is-locked');
    return closed;
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeMobileSheets();
  });

  document.addEventListener('click', function (e) {
    var panel = $('[data-filter-panel].is-sheet-open');
    if (!panel) return;
    var form = $('form', panel);
    if (!form || form.contains(e.target)) return;
    if (e.target.closest('[data-filter-open]')) return;
    closeMobileSheets();
  });

  document.addEventListener('click', function (e) {
    var link = e.target.closest('[data-page-loading]');
    var pageLoader = $('[data-page-loader]');
    if (link && pageLoader && !e.metaKey && !e.ctrlKey) pageLoader.classList.add('is-active');
  });

  $$('[data-phone-form]').forEach(function (form) {
    var input = $('[data-phone-input]', form);
    var submit = $('[data-phone-submit]', form);
    if (!input || !submit) return;

    function sync() {
      input.value = input.value.replace(/\D/g, '').slice(0, 10);
      submit.disabled = input.value.length !== 10;
    }

    input.addEventListener('input', sync);
    sync();
  });

  // Phone fields elsewhere (checkout contact, address book): letters and
  // symbols are dropped as they're typed or pasted. Capped by data-max-digits
  // rather than maxlength, because maxlength would cut a pasted
  // "+91 98765 43210" to "+91 98765 " before this handler sees it; the
  // country code / leading 0 is removed here first instead.
  $$('[data-digits-only]').forEach(function (input) {
    var max = parseInt(input.getAttribute('data-max-digits'), 10) || 0;

    function clean() {
      var digits = input.value.replace(/\D/g, '');
      if (max === 10 && digits.length > 10) digits = digits.replace(/^(91|0)/, '');
      if (max) digits = digits.slice(0, max);
      if (digits !== input.value) input.value = digits;
    }

    input.addEventListener('input', clean);
    clean();
  });

  $$('[data-otp-form]').forEach(function (form) {
    var boxes = $$('[data-otp-box]', form);
    var value = $('[data-otp-value]', form);
    var submit = $('[data-otp-submit]', form);
    if (!boxes.length || !value) return;

    function sync() {
      value.value = boxes.map(function (b) { return b.value; }).join('');
      if (submit) submit.disabled = value.value.length !== boxes.length;
    }

    function fill(digits, from) {
      digits.split('').forEach(function (d, i) {
        if (boxes[from + i]) boxes[from + i].value = d;
      });
      var next = Math.min(from + digits.length, boxes.length - 1);
      boxes[next].focus();
      sync();
      if (value.value.length === boxes.length && form.requestSubmit) form.requestSubmit();
    }

    boxes.forEach(function (box, index) {
      box.addEventListener('input', function () {
        var digits = box.value.replace(/\D/g, '');
        box.value = '';
        if (digits) fill(digits, index);
        else sync();
      });
      box.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace' && !box.value && index > 0) {
          boxes[index - 1].value = '';
          boxes[index - 1].focus();
          sync();
        }
      });
      box.addEventListener('paste', function (e) {
        var digits = ((e.clipboardData || window.clipboardData).getData('text') || '').replace(/\D/g, '');
        if (!digits) return;
        e.preventDefault();
        fill(digits, 0);
      });
    });

    sync();
  });

  // Sitewide double-submit guard — fires for every real <form> submission
  // (capture phase, so it always runs before any per-form submit handler
  // added elsewhere, including ones that call preventDefault()). Repeated
  // taps on a submit button were firing multiple in-flight requests because
  // most forms had no opt-in guard at all; this makes the guard the default
  // instead of something each new form has to remember to add.
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement)) return;

    var btn = e.submitter || $('button[type="submit"]', form);
    if (!btn || btn.disabled) {
      if (btn) e.preventDefault();
      return;
    }

    btn.disabled = true;
    btn.setAttribute('aria-busy', 'true');

    // A form can have more than one submit button (checkout's inline Place
    // Order plus the sticky phone bar, which points at it via form="…"),
    // so lock all of them, not only the one that was pressed.
    $$('button[type="submit"]', form).concat(form.id ? $$('button[type="submit"][form="' + form.id + '"]') : [])
      .forEach(function (other) { if (other !== btn) other.disabled = true; });

    if (form.hasAttribute('data-loading-submit')) {
      btn.dataset.originalLabel = btn.innerHTML;
      btn.innerHTML = '<span class="btn-loading-dots" aria-hidden="true"><span></span><span></span><span></span></span><span class="sr-only-custom">Please wait</span>';
    }
  }, true);

  // Back/forward cache restores the page exactly as it was mid-submit, so
  // put every button back the way it was before the user pressed it.
  window.addEventListener('pageshow', function (e) {
    if (!e.persisted) return;
    $$('button[aria-busy="true"]').forEach(function (btn) {
      if (btn.dataset.originalLabel) btn.innerHTML = btn.dataset.originalLabel;
      btn.disabled = false;
      btn.removeAttribute('aria-busy');
    });
  });

  /* ------------------------------------------------------------------------
     RESEND COOLDOWN — a code was just sent when this page rendered, so hold
     the resend button for a few seconds with a visible countdown.
     ---------------------------------------------------------------------- */
  $$('[data-resend-cooldown]').forEach(function (btn) {
    var seconds = parseInt(btn.dataset.resendCooldown, 10) || 0;
    if (!seconds) return;

    var label = btn.textContent;
    btn.disabled = true;

    (function tick() {
      if (seconds <= 0) {
        btn.disabled = false;
        btn.textContent = label;
        return;
      }
      btn.textContent = 'Resend code in ' + seconds + 's';
      seconds -= 1;
      setTimeout(tick, 1000);
    })();
  });


  /* ------------------------------------------------------------------------
     GLOBAL IMAGE LIGHTBOX — tap / click a product image to view it full
     screen, then swipe (or scroll, arrow keys, arrows, dots) through every
     other image of the same product without closing it.
     Works on:
       • PDP gallery slider images (.pdp-slide img) — opens on the tapped one,
         with the whole gallery in the strip
       • Any other img tagged [data-lightbox] (shown on its own)
     The PDP expand icon opens it too, via window.esteleLightbox.
     Closes on: ×, a tap beside the image, Escape, or a swipe down.
     --------------------------------------------------------------------- */
  (function () {
    var overlay = document.createElement('div');
    overlay.className = 'lb';
    overlay.id = 'img-lightbox';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', 'Product images');
    overlay.hidden = true;
    overlay.innerHTML =
      '<div class="lb__track" data-lb-track></div>' +
      '<p class="lb__count" data-lb-count aria-live="polite"></p>' +
      '<button class="lb__close" type="button" data-lb-close aria-label="Close">&times;</button>' +
      '<button class="lb__nav lb__nav--prev" type="button" data-lb-prev aria-label="Previous image">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>' +
      '</button>' +
      '<button class="lb__nav lb__nav--next" type="button" data-lb-next aria-label="Next image">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>' +
      '</button>' +
      '<div class="lb__dots" data-lb-dots></div>';
    document.body.appendChild(overlay);

    var track = $('[data-lb-track]', overlay);
    var count = $('[data-lb-count]', overlay);
    var dotsWrap = $('[data-lb-dots]', overlay);
    var closeBtn = $('[data-lb-close]', overlay);
    var prevBtn = $('[data-lb-prev]', overlay);
    var nextBtn = $('[data-lb-next]', overlay);

    var total = 0;
    var current = 0;
    var onClose = null;
    var closeTimer = null;

    /* Best quality URL: prefer the largest srcset entry, else src. */
    function bestSrc(img) {
      var srcset = img.getAttribute('srcset') || '';
      if (srcset) {
        var best = srcset.split(',').reduce(function (acc, part) {
          var m = part.trim().match(/^(\S+)\s+(\d+)w$/);
          if (m && parseInt(m[2], 10) > acc.w) return { url: m[1], w: parseInt(m[2], 10) };
          return acc;
        }, { url: '', w: 0 });
        if (best.url) return best.url;
      }
      return img.getAttribute('data-slide-full') || img.currentSrc || img.src;
    }

    function show(index) {
      current = Math.max(0, Math.min(index, total - 1));
      count.textContent = (current + 1) + ' / ' + total;
      $$('.lb__dot', dotsWrap).forEach(function (dot, i) {
        dot.classList.toggle('is-current', i === current);
        dot.setAttribute('aria-current', i === current ? 'true' : 'false');
      });
      prevBtn.disabled = current === 0;
      nextBtn.disabled = current === total - 1;
    }

    function go(index, smooth) {
      index = Math.max(0, Math.min(index, total - 1));
      track.scrollTo({ left: index * track.clientWidth, behavior: smooth === false ? 'auto' : 'smooth' });
      show(index);
    }

    function build(images) {
      track.innerHTML = '';
      dotsWrap.innerHTML = '';
      total = images.length;

      images.forEach(function (img, i) {
        var slide = document.createElement('div');
        slide.className = 'lb__slide';
        var pic = document.createElement('img');
        pic.src = bestSrc(img);
        pic.alt = img.alt || '';
        pic.decoding = 'async';
        pic.draggable = false;
        slide.appendChild(pic);
        track.appendChild(slide);

        var dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'lb__dot';
        dot.setAttribute('aria-label', 'Show image ' + (i + 1));
        dot.addEventListener('click', function () { go(i); });
        dotsWrap.appendChild(dot);
      });

      overlay.classList.toggle('is-single', total < 2);
    }

    function open(images, index, closeCallback) {
      if (!images.length) return;
      clearTimeout(closeTimer);
      build(images);
      onClose = closeCallback || null;
      overlay.hidden = false;
      lockScroll('lightbox');
      /* Layout exists now that it's unhidden, so the strip can jump
         straight to the tapped image with no visible scroll. */
      go(index, false);
      requestAnimationFrame(function () { overlay.classList.add('is-open'); });
      closeBtn.focus({ preventScroll: true });
    }

    function close() {
      if (overlay.hidden) return;
      overlay.classList.remove('is-open');
      unlockScroll('lightbox');
      if (onClose) onClose(current);
      onClose = null;
      closeTimer = setTimeout(function () {
        overlay.hidden = true;
        track.innerHTML = '';
      }, 220);
    }

    /* The group an image belongs to: every image of the PDP slider it sits
       in, or just itself. Closing on a PDP image leaves the page slider on
       the image the shopper swiped to. */
    function openFrom(img) {
      var slider = img.closest('.pdp-slider');
      if (!slider) {
        open([img], 0);
        return;
      }
      var images = $$('.pdp-slide img', slider);
      open(images, Math.max(0, images.indexOf(img)), function (index) {
        slider.scrollTo({ left: slider.clientWidth * index, behavior: 'auto' });
      });
    }

    window.esteleLightbox = { openFrom: openFrom };

    var scrollFrame = null;
    track.addEventListener('scroll', function () {
      if (scrollFrame) return;
      scrollFrame = requestAnimationFrame(function () {
        scrollFrame = null;
        if (!track.clientWidth) return;
        var index = Math.round(track.scrollLeft / track.clientWidth);
        if (index !== current) show(index);
      });
    }, { passive: true });

    prevBtn.addEventListener('click', function () { go(current - 1); });
    nextBtn.addEventListener('click', function () { go(current + 1); });
    closeBtn.addEventListener('click', close);

    /* A tap on the dark area beside the picture closes; a tap on the
       picture itself doesn't. */
    track.addEventListener('click', function (e) {
      if (e.target.tagName !== 'IMG') close();
    });

    document.addEventListener('keydown', function (e) {
      if (overlay.hidden) return;
      if (e.key === 'Escape') close();
      else if (e.key === 'ArrowRight') go(current + 1);
      else if (e.key === 'ArrowLeft') go(current - 1);
    });

    /* Swipe down closes; sideways swipes are the strip's own scrolling. */
    var startX = 0;
    var startY = 0;
    overlay.addEventListener('touchstart', function (e) {
      startX = e.touches[0].clientX;
      startY = e.touches[0].clientY;
    }, { passive: true });
    overlay.addEventListener('touchend', function (e) {
      var dx = e.changedTouches[0].clientX - startX;
      var dy = e.changedTouches[0].clientY - startY;
      if (dy > 80 && Math.abs(dx) < dy / 2) close();
    }, { passive: true });

    /* Keep the current image in place when the phone rotates. */
    window.addEventListener('resize', function () {
      if (!overlay.hidden) go(current, false);
    });

    /* A tap that was really the end of a swipe on the page slider mustn't
       open the viewer. Only a recent touch counts, so a mouse click (no
       touch at all) always opens it. */
    var touchX = 0;
    var touchY = 0;
    var touchAt = 0;
    document.addEventListener('touchstart', function (e) {
      touchX = e.touches[0].clientX;
      touchY = e.touches[0].clientY;
      touchAt = Date.now();
    }, { passive: true });

    document.addEventListener('click', function (e) {
      var img = e.target.closest && e.target.closest('.pdp-slide img, [data-lightbox]');
      if (!img || img.tagName !== 'IMG' || overlay.contains(img)) return;

      if (Date.now() - touchAt < 1000 &&
          (Math.abs(e.clientX - touchX) > 12 || Math.abs(e.clientY - touchY) > 12)) return;

      e.preventDefault();
      e.stopPropagation();
      openFrom(img);
    });
  })();

})();
