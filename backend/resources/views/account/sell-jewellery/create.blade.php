@extends('layouts.app')

@section('meta_title', 'Sell Your Jewellery | '.($siteSettings['site_name'] ?? 'Estele'))

@section('content')

  <nav class="mx-auto w-full max-w-wrapper px-3 md:px-4 flex flex-wrap items-center gap-1.5 py-2 text-[13px] text-muted border-b border-line" aria-label="Breadcrumb">
    <x-breadcrumb :items="[['label' => 'My Account', 'url' => route('account.index')], ['label' => 'Sell Your Jewellery', 'url' => route('account.sell-jewellery.landing')], ['label' => 'New Request']]" />
  </nav>

  <div class="mx-auto w-full max-w-2xl px-3 pb-10 pt-4 md:pt-6 md:px-4 md:pb-[60px]">

    {{-- Hero. The script accent ("Showcase Your Shine") is the one place this
         flow uses the brand's script face — it sits opposite the heading and
         drops out below sm, where there isn't room for it beside the title. --}}
    <div class="grad-soft relative mb-5 overflow-hidden rounded-2xl border border-accent/15 px-4 py-5">
      <div class="flex items-start gap-3">
        <span class="icon-tile shrink-0 bg-white/70" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 8a2 2 0 0 1 2-2h2l1.5-2h5L16 6h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/>
            <circle cx="12" cy="12.5" r="3.5"/>
          </svg>
        </span>
        <div class="min-w-0 flex-1">
          <h1 class="text-[22px] font-bold uppercase leading-tight tracking-[0.5px] text-heading md:text-[26px]">
            Sell Your <span class="text-accent-dark">Jewellery</span>
          </h1>
          <p class="mt-1.5 text-[13px] leading-snug text-muted">Add a short video and a photo. Our vendors value your piece from what they see.</p>
        </div>
        <span class="hidden shrink-0 pt-1 text-right font-script text-[22px] leading-tight text-accent-dark sm:block" aria-hidden="true">
          Showcase<br>Your Shine
        </span>
      </div>
    </div>

    @if ($errors->any())
      <div class="mb-4 rounded-lg border border-salebadge bg-red-50 p-4 text-[13px] text-salebadge">
        <ul class="list-disc space-y-1 pl-4">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form action="{{ route('account.sell-jewellery.store') }}" method="post" enctype="multipart/form-data" data-sell-jewellery-form>
      @csrf

      {{-- Stacked, not side-by-side: each upload carries its own "why this
           helps" list beside it, which needs the full row width to stay
           readable on a phone. --}}
      <div class="mb-5 space-y-4">
        <div class="relative rounded-2xl border border-accent/25 bg-pinksoft/40 p-3 sm:p-4" data-media-picker="video">
          <span class="pill grad-brand absolute -top-2 left-4 z-10 text-white">
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 2.9 6.2 6.6.8-4.9 4.6 1.3 6.6L12 17l-5.9 3.2 1.3-6.6L2.5 9l6.6-.8z"/></svg>
            Recommended
          </span>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto] sm:items-center sm:gap-4">
          <label class="group relative block cursor-pointer overflow-hidden rounded-xl border-2 border-dashed border-line-strong bg-ivory transition-colors hover:border-accent has-[:focus-visible]:border-accent has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-accent/30 data-[filled]:border-solid data-[filled]:border-accent data-[filled]:bg-paper data-[dragging]:border-accent data-[dragging]:bg-pinksoft" data-dropzone>
            <input class="sr-only" type="file" id="video" name="video" accept=".mp4,.mov,video/mp4,video/quicktime" required data-file-input>

            <div class="flex min-h-[190px] flex-col items-center justify-center px-4 py-6 text-center" data-empty>
              <span class="grad-brand mb-3 flex h-14 w-14 items-center justify-center rounded-2xl text-white transition-transform group-hover:scale-105">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <rect x="3" y="6" width="13" height="12" rx="2" />
                  <path d="m16 10 5-3v10l-5-3" />
                </svg>
              </span>
              <span class="text-[15px] font-bold uppercase tracking-[0.4px] text-heading">Add Video</span>
              <span class="mt-1 text-[12px] text-muted">Tap to record or choose</span>
              {{-- Styled as a button but still just the label's own surface —
                   the whole label is the file-input trigger. --}}
              <span class="grad-brand mt-3 inline-flex items-center gap-2 rounded-lg px-5 py-2.5 text-[12px] font-bold uppercase tracking-[0.4px] text-white">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8a2 2 0 0 1 2-2h2l1.5-2h5L16 6h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><circle cx="12" cy="12.5" r="3.5"/></svg>
                Record / Choose
              </span>
              <span class="mt-2.5 text-[11px] text-muted">MP4 / MOV · up to 20MB</span>
            </div>

            <div class="hidden" data-filled>
              <div class="relative aspect-square w-full bg-heading">
                <video class="absolute inset-0 h-full w-full object-cover" data-preview muted playsinline preload="metadata"></video>
                <span class="absolute inset-0 flex items-center justify-center bg-black/25">
                  <span class="flex h-12 w-12 items-center justify-center rounded-full bg-paper/90 text-heading">
                    <svg class="ml-0.5 h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
                  </span>
                </span>
                <span class="absolute left-2 top-2 rounded-full bg-paper/95 px-2 py-0.5 text-[10px] font-medium uppercase tracking-[0.3px] text-heading">Video</span>
              </div>
              <div class="flex items-center gap-2 px-3 py-2.5">
                <div class="min-w-0 flex-1">
                  <p class="truncate text-[12px] font-medium text-heading" data-file-name></p>
                  <p class="text-[11px] text-muted" data-file-size></p>
                </div>
                <span class="shrink-0 text-[11px] font-medium uppercase tracking-[0.3px] text-accent underline">Change</span>
                <button class="shrink-0 rounded-full p-1 text-muted transition-colors hover:bg-greysoft hover:text-heading" type="button" aria-label="Remove video" data-remove>
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                </button>
              </div>
            </div>
          </label>

          {{-- Why a video helps. Sits beside the picker from sm up, below it
               on a phone, and is decorative reassurance — the picker itself
               already carries the format/size rules. --}}
          <ul class="space-y-2.5 text-left sm:w-[164px] sm:border-l sm:border-line sm:pl-4">
            <li class="flex items-start gap-2">
              <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-pinksoft text-accent-dark" aria-hidden="true"><svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>
              <span class="text-[11.5px] leading-snug text-muted">Show real movement &amp; sparkle</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-pinksoft text-accent-dark" aria-hidden="true"><svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6z"/></svg></span>
              <span class="text-[11.5px] leading-snug text-muted">Builds trust and higher value</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-pinksoft text-accent-dark" aria-hidden="true"><svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor"><path d="m12 2 2.9 6.2 6.6.8-4.9 4.6 1.3 6.6L12 17l-5.9 3.2 1.3-6.6L2.5 9l6.6-.8z"/></svg></span>
              <span class="text-[11.5px] leading-snug text-muted">Short videos work best</span>
            </li>
          </ul>
          </div>

          <p class="mt-2.5 text-[11px] leading-snug text-muted">Turn the piece slowly and show any hallmark or stamp — it helps vendors give their best offer.</p>
          <p class="mt-1.5 hidden text-[11px] font-medium text-salebadge" data-file-error></p>
        </div>

        <div class="rounded-2xl border border-gold/35 bg-gold-light/25 p-3 sm:p-4" data-media-picker="image">
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto] sm:items-center sm:gap-4">
          <label class="group relative block cursor-pointer overflow-hidden rounded-xl border-2 border-dashed border-line-strong bg-ivory transition-colors hover:border-accent has-[:focus-visible]:border-accent has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-accent/30 data-[filled]:border-solid data-[filled]:border-accent data-[filled]:bg-paper data-[dragging]:border-accent data-[dragging]:bg-pinksoft" data-dropzone>
            <input class="sr-only" type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-file-input>

            <div class="flex min-h-[190px] flex-col items-center justify-center px-4 py-6 text-center" data-empty>
              <span class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-gold-light text-gold-hover transition-transform group-hover:scale-105">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M4 8a2 2 0 0 1 2-2h2l1.5-2h5L16 6h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z" />
                  <circle cx="12" cy="12.5" r="3.5" />
                </svg>
              </span>
              <span class="text-[15px] font-bold uppercase tracking-[0.4px] text-heading">Add Photo</span>
              <span class="mt-1 text-[12px] text-muted">Tap to take or choose</span>
              <span class="grad-brand mt-3 inline-flex items-center gap-2 rounded-lg px-5 py-2.5 text-[12px] font-bold uppercase tracking-[0.4px] text-white">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8a2 2 0 0 1 2-2h2l1.5-2h5L16 6h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><circle cx="12" cy="12.5" r="3.5"/></svg>
                Choose Photo
              </span>
              <span class="mt-2.5 text-[11px] text-muted">JPG / PNG / WebP · up to 3MB</span>
            </div>

            <div class="hidden" data-filled>
              <div class="relative aspect-square w-full bg-greysoft">
                <img class="absolute inset-0 h-full w-full object-cover" data-preview alt="Selected photo">
                <span class="absolute left-2 top-2 rounded-full bg-paper/95 px-2 py-0.5 text-[10px] font-medium uppercase tracking-[0.3px] text-heading">Photo</span>
              </div>
              <div class="flex items-center gap-2 px-3 py-2.5">
                <div class="min-w-0 flex-1">
                  <p class="truncate text-[12px] font-medium text-heading" data-file-name></p>
                  <p class="text-[11px] text-muted" data-file-size></p>
                </div>
                <span class="shrink-0 text-[11px] font-medium uppercase tracking-[0.3px] text-accent underline">Change</span>
                <button class="shrink-0 rounded-full p-1 text-muted transition-colors hover:bg-greysoft hover:text-heading" type="button" aria-label="Remove photo" data-remove>
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                </button>
              </div>
            </div>
          </label>

          <ul class="space-y-2.5 text-left sm:w-[164px] sm:border-l sm:border-line sm:pl-4">
            <li class="flex items-start gap-2">
              <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-gold-light text-gold-hover" aria-hidden="true"><svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 15 5-4 4 3 3-2 6 4"/></svg></span>
              <span class="text-[11.5px] leading-snug text-muted">Clear, well-lit high-quality photo</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-gold-light text-gold-hover" aria-hidden="true"><svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor"><path d="m12 2 2.9 6.2 6.6.8-4.9 4.6 1.3 6.6L12 17l-5.9 3.2 1.3-6.6L2.5 9l6.6-.8z"/></svg></span>
              <span class="text-[11.5px] leading-snug text-muted">Show all details of your piece</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-gold-light text-gold-hover" aria-hidden="true"><svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6z"/></svg></span>
              <span class="text-[11.5px] leading-snug text-muted">Multiple angles are helpful</span>
            </li>
          </ul>
          </div>

          <p class="mt-2.5 text-[11px] leading-snug text-muted">A clear, well-lit shot of the full piece.</p>
          <p class="mt-1.5 hidden text-[11px] font-medium text-salebadge" data-file-error></p>
        </div>
      </div>

      <div class="mb-5 flex items-start gap-3 rounded-2xl border border-line bg-pinksoft/50 px-4 py-3.5">
        <span class="icon-tile h-9 w-9 shrink-0 bg-white" aria-hidden="true">
          <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 21h4"/><path d="M12 3a6 6 0 0 0-3.5 10.9c.4.3.5.7.5 1.1h6c0-.4.1-.8.5-1.1A6 6 0 0 0 12 3z"/></svg>
        </span>
        <div class="min-w-0 flex-1">
          <p class="text-[13px] font-bold leading-tight text-heading">Quick Tip</p>
          <p class="mt-0.5 text-[12px] leading-snug text-muted">Use a plain background and good lighting for the best results.</p>
        </div>
      </div>

      <button class="grad-brand inline-flex w-full items-center justify-center gap-2 rounded-lg px-6 py-4 text-[14px] font-bold uppercase tracking-[0.5px] text-white transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60" type="submit" data-submit>
        <svg class="hidden h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true" data-submit-spinner>
          <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"></circle>
          <path class="opacity-90" d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
        </svg>
        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21 3-9.5 9.5"/><path d="M21 3 14.5 21l-3-7.5L4 10.5z"/></svg>
        <span data-submit-label>Submit Request</span>
        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h13M13 6l6 6-6 6"/></svg>
      </button>
    </form>
  </div>

  @push('scripts')
    <script>
      (function () {
        // Keep in sync with StoreOldJewelleryRequestRequest's max: rules (in KB).
        var MAX_BYTES = { image: 3 * 1024 * 1024, video: 20 * 1024 * 1024 };
        var MAX_LABEL = { image: '3MB', video: '20MB' };

        function formatSize(bytes) {
          if (bytes < 1024 * 1024) return Math.max(1, Math.round(bytes / 1024)) + ' KB';
          return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        var pickers = [];

        document.querySelectorAll('[data-media-picker]').forEach(function (picker) {
          var kind = picker.getAttribute('data-media-picker');
          var zone = picker.querySelector('[data-dropzone]');
          var input = picker.querySelector('[data-file-input]');
          var empty = picker.querySelector('[data-empty]');
          var filled = picker.querySelector('[data-filled]');
          var preview = picker.querySelector('[data-preview]');
          var nameEl = picker.querySelector('[data-file-name]');
          var sizeEl = picker.querySelector('[data-file-size]');
          var removeBtn = picker.querySelector('[data-remove]');
          var errorEl = picker.querySelector('[data-file-error]');
          var objectUrl = null;

          function clearPreview() {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = null;
            preview.removeAttribute('src');
            if (preview.tagName === 'VIDEO') preview.load();
          }

          function showError(message) {
            if (!errorEl) return;
            errorEl.textContent = message;
            errorEl.classList.remove('hidden');
          }

          function clearError() {
            if (!errorEl) return;
            errorEl.textContent = '';
            errorEl.classList.add('hidden');
          }

          function render() {
            var file = input.files && input.files[0];
            clearPreview();

            if (!file) {
              zone.removeAttribute('data-filled');
              empty.classList.remove('hidden');
              filled.classList.add('hidden');
              return;
            }

            // Reject oversized files before they ever preview or reach the
            // server — photo must stay under 3MB, video under 20MB.
            var limit = MAX_BYTES[kind];
            if (limit && file.size > limit) {
              input.value = '';
              zone.removeAttribute('data-filled');
              empty.classList.remove('hidden');
              filled.classList.add('hidden');
              showError((kind === 'video' ? 'Video' : 'Photo') + ' is too large — max ' + MAX_LABEL[kind] + ' (this file is ' + formatSize(file.size) + ').');
              return;
            }

            clearError();
            objectUrl = URL.createObjectURL(file);
            preview.src = objectUrl;
            nameEl.textContent = file.name;
            sizeEl.textContent = formatSize(file.size);
            zone.setAttribute('data-filled', '');
            empty.classList.add('hidden');
            filled.classList.remove('hidden');
          }

          input.addEventListener('change', render);

          removeBtn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            input.value = '';
            clearError();
            render();
          });

          ['dragenter', 'dragover'].forEach(function (name) {
            zone.addEventListener(name, function (event) {
              event.preventDefault();
              zone.setAttribute('data-dragging', '');
            });
          });

          ['dragleave', 'drop'].forEach(function (name) {
            zone.addEventListener(name, function () {
              zone.removeAttribute('data-dragging');
            });
          });

          zone.addEventListener('drop', function (event) {
            event.preventDefault();
            if (!event.dataTransfer || !event.dataTransfer.files.length) return;
            input.files = event.dataTransfer.files;
            render();
          });

          pickers.push({ kind: kind, input: input });
        });

        var form = document.querySelector('[data-sell-jewellery-form]');
        var submit = form && form.querySelector('[data-submit]');
        var spinner = submit && submit.querySelector('[data-submit-spinner]');
        var label = submit && submit.querySelector('[data-submit-label]');

        if (form && submit) {
          form.addEventListener('submit', function (event) {
            // Final guard: block submit if any picker still holds an
            // oversize file (covers the rare case a size slips through,
            // e.g. a file re-selected via the OS picker after a drop).
            for (var i = 0; i < pickers.length; i++) {
              var file = pickers[i].input.files && pickers[i].input.files[0];
              var limit = MAX_BYTES[pickers[i].kind];
              if (file && limit && file.size > limit) {
                event.preventDefault();
                return;
              }
            }

            submit.disabled = true;
            if (spinner) spinner.classList.remove('hidden');

            // Cycle the label through a few reassuring messages instead of
            // sitting on a single static "Uploading…" for the whole
            // (often slow, on mobile data) upload — keeps it feeling alive.
            if (label) {
              var messages = ['Uploading…', 'Almost there…', 'Hang tight…'];
              var i2 = 0;
              label.textContent = messages[0];
              setInterval(function () {
                i2 = (i2 + 1) % messages.length;
                label.textContent = messages[i2];
              }, 2200);
            }
          });
        }
      })();
    </script>
  @endpush

@endsection
