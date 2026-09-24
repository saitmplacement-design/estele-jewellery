@extends('layouts.app')

@section('meta_title', 'Sell Request | '.($siteSettings['site_name'] ?? 'Estele'))

@section('content')

  @php
    $isPolling = in_array($oldJewelleryRequest->status, ['pending', 'submitted', 'vendors_notified', 'bidding_active'], true);

    $statusPanel = match ($oldJewelleryRequest->status) {
        'pending', 'submitted', 'vendors_notified', 'bidding_active' => [
            'tone' => 'border-amber-200 bg-amber-50',
            'icon' => 'bg-amber-100 text-amber-700',
            'title' => 'In Review',
            'text' => 'Your submission is being reviewed. We\'ll update this page as soon as your offer is ready.',
            'path' => 'M12 8v4l2.5 2.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
        ],
        'bidding_closed', 'bid_selected', 'wallet_pending' => [
            'tone' => 'border-amber-200 bg-amber-50',
            'icon' => 'bg-amber-100 text-amber-700',
            'title' => 'Finalising Your Offer',
            'text' => 'Your best offer is being finalised and will be credited to your wallet shortly.',
            'path' => 'M12 8v4l2.5 2.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
        ],
        'wallet_credited', 'completed' => [
            'tone' => 'border-green-200 bg-green-50',
            'icon' => 'bg-green-100 text-green-700',
            'title' => 'Credited to Your Wallet',
            'text' => 'Your offer has been credited. Use it on your next purchase.',
            'path' => 'M5 12.5l4.5 4.5L19 7.5',
        ],
        'wallet_expired' => [
            'tone' => 'border-red-200 bg-red-50',
            'icon' => 'bg-red-100 text-red-700',
            'title' => 'Credit Expired',
            'text' => 'The credit from this request was not used before it expired.',
            'path' => 'M6 6l12 12M18 6L6 18',
        ],
        default => [
            'tone' => 'border-red-200 bg-red-50',
            'icon' => 'bg-red-100 text-red-700',
            'title' => 'Cancelled',
            'text' => 'This request is no longer active.',
            'path' => 'M6 6l12 12M18 6L6 18',
        ],
    };
  @endphp

  <nav class="mx-auto w-full max-w-wrapper px-3 md:px-4 flex flex-wrap items-center gap-1.5 py-2 text-[13px] text-muted border-b border-line" aria-label="Breadcrumb">
    <x-breadcrumb :items="[['label' => 'My Account', 'url' => route('account.index')], ['label' => 'Sell Your Jewellery', 'url' => route('account.sell-jewellery.landing')], ['label' => 'My Requests', 'url' => route('account.sell-jewellery.index')], ['label' => 'Request']]" />
  </nav>

  <div class="mx-auto w-full max-w-2xl px-3 pb-6 pt-4 md:pt-6 md:px-4 md:pb-[60px]" data-poll-status data-should-poll="{{ $isPolling ? '1' : '0' }}" data-status-url="{{ route('account.sell-jewellery.status', $oldJewelleryRequest) }}">
    <h1 class="mb-4 text-[18px] uppercase tracking-[0.4px] text-heading">{{ $oldJewelleryRequest->request_number }}</h1>

    <div class="rounded-xl border {{ $statusPanel['tone'] }} p-5 md:p-6">
      <div class="flex items-start gap-3">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full {{ $statusPanel['icon'] }}">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $statusPanel['path'] }}" /></svg>
        </span>
        <div class="min-w-0 flex-1">
          <p class="text-[16px] font-medium text-heading">{{ $statusPanel['title'] }}</p>
          <p class="mt-1 text-[13px] leading-snug text-muted">{{ $statusPanel['text'] }}</p>
        </div>
      </div>

      <p class="mt-4 border-t border-line/60 pt-3 text-[12px] text-muted">Submitted {{ $oldJewelleryRequest->created_at->formatIst('d M Y, h:i A') }}</p>
    </div>

    @if ($oldJewelleryRequest->final_amount)
      <div class="mt-4 rounded-lg border border-line bg-pinksoft p-4 text-center">
        <p class="text-[12px] uppercase tracking-[0.3px] text-muted">Credited to Wallet</p>
        <p class="mt-1 text-[22px] font-medium text-heading">₹{{ number_format((float) $oldJewelleryRequest->credited_amount, 2) }}</p>
      </div>
    @endif

    @if ($oldJewelleryRequest->hasMedia('image') || $oldJewelleryRequest->description)
      <div class="mt-4 rounded-xl border border-line bg-white p-4 md:p-5">
        <p class="mb-3 text-[12px] font-medium uppercase tracking-[0.3px] text-muted">What you submitted</p>
        @if ($oldJewelleryRequest->getFirstMediaUrl('image', 'thumb'))
          <img class="mb-3 max-h-56 w-full rounded-lg object-cover" src="{{ $oldJewelleryRequest->getFirstMediaUrl('image', 'thumb') }}" alt="Your jewellery photo">
        @endif
        @if ($oldJewelleryRequest->description)
          <p class="text-[13px] leading-relaxed text-muted">{{ $oldJewelleryRequest->description }}</p>
        @endif
      </div>
    @endif
  </div>

  @push('scripts')
    <script>
      (function () {
        var el = document.querySelector('[data-poll-status]');
        if (!el || el.getAttribute('data-should-poll') !== '1') return;

        function poll() {
          fetch(el.getAttribute('data-status-url'), { headers: { Accept: 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (body) {
              if (!body.success) return;
              var terminal = ['bidding_closed', 'bid_selected', 'wallet_pending', 'wallet_credited', 'wallet_expired', 'completed', 'cancelled'];
              if (terminal.indexOf(body.data.status) !== -1) {
                window.location.reload();
              }
            })
            .catch(function () {});
        }

        setInterval(poll, 20000);
      })();
    </script>
  @endpush

@endsection
