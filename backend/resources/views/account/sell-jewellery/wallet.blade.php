@extends('layouts.app')

@section('meta_title', 'My Wallet | '.($siteSettings['site_name'] ?? 'Estele'))

@section('content')

  <nav class="mx-auto w-full max-w-wrapper px-3 md:px-4 flex flex-wrap items-center gap-1.5 py-2 text-[13px] text-muted border-b border-line" aria-label="Breadcrumb">
    <x-breadcrumb :items="[['label' => 'My Account', 'url' => route('account.index')], ['label' => 'Sell Your Jewellery', 'url' => route('account.sell-jewellery.landing')], ['label' => 'Wallet']]" />
  </nav>

  <div class="mx-auto w-full max-w-wrapper px-3 pb-6 pt-4 md:pt-6 md:px-4 md:pb-[60px]">
    <div class="mb-6 flex flex-wrap gap-3">
      <a class="inline-flex items-center gap-1.5 rounded-full border border-line px-4 py-2 text-[12px] font-medium uppercase tracking-[0.3px] text-heading transition-colors hover:border-accent hover:text-accent" href="{{ route('account.sell-jewellery.index') }}">
        My Requests
      </a>
      <a class="inline-flex items-center gap-1.5 rounded-full border border-line px-4 py-2 text-[12px] font-medium uppercase tracking-[0.3px] text-heading transition-colors hover:border-accent hover:text-accent" href="{{ route('account.sell-jewellery.wallet') }}">
        My Wallet
      </a>
    </div>

    <h1 class="mb-6 text-[20px] uppercase tracking-[0.5px] md:text-[26px]">My Wallet</h1>

    <div class="mb-8 rounded-lg border border-line bg-pinksoft p-6">
      <p class="text-[12px] uppercase tracking-[0.3px] text-muted">Wallet Balance</p>
      <p class="mt-1 text-[28px] font-medium text-heading">₹{{ number_format((float) $balance, 2) }}</p>
    </div>

    @if ($credits->isNotEmpty())
      <h2 class="mb-4 text-[16px] uppercase tracking-[0.4px] text-heading">Active Credits</h2>
      <div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-2">
        @foreach ($credits as $credit)
          @php
            $daysLeft = $credit->expires_at ? (int) floor(now()->diffInDays($credit->expires_at, false)) : null;
            $expiryClass = $daysLeft !== null && $daysLeft <= 1 ? 'text-red-700' : ($daysLeft !== null && $daysLeft <= 3 ? 'text-amber-700' : 'text-muted');
          @endphp
          <div class="rounded-lg border border-line p-4">
            <div class="mb-2 flex items-center justify-between gap-2">
              <span class="text-[14px] font-medium text-heading">₹{{ number_format((float) $credit->remaining_amount, 2) }}</span>
              <span class="text-[12px] font-medium {{ $expiryClass }}">
                @if ($credit->status === 'expired') Expired @elseif ($daysLeft !== null) {{ max($daysLeft, 0) }}d left @endif
              </span>
            </div>
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
              @php $percentLeft = $credit->credited_amount > 0 ? round(((float) $credit->remaining_amount / (float) $credit->credited_amount) * 100) : 0; @endphp
              <div class="h-full bg-accent" style="width: {{ min(max($percentLeft, 0), 100) }}%"></div>
            </div>
          </div>
        @endforeach
      </div>
      <div class="mb-8">{{ $credits->links() }}</div>
    @endif

    @if ($transactions->isNotEmpty())
      <h2 class="mb-4 text-[16px] uppercase tracking-[0.4px] text-heading">Recent Activity</h2>
      <div class="divide-y divide-line rounded-lg border border-line">
        @foreach ($transactions as $txn)
          <div class="flex items-center justify-between gap-3 p-4 text-[13px]">
            <span class="text-muted">{{ $txn->created_at->formatIst('d M Y, h:i A') }}</span>
            <span class="font-medium {{ $txn->type === 'credit' ? 'text-green-700' : 'text-salebadge' }}">
              {{ $txn->type === 'credit' ? '+' : '−' }} ₹{{ number_format((float) $txn->amount, 2) }}
            </span>
          </div>
        @endforeach
      </div>
      <div class="mt-4">{{ $transactions->links() }}</div>
    @endif

    @if ($credits->isEmpty() && $transactions->isEmpty())
      <div class="rounded-lg border border-line p-8 text-center">
        <p class="mb-4 text-[13px] text-muted">No wallet activity yet.</p>
        <a class="inline-flex items-center justify-center gap-2 border border-accent bg-accent px-6 py-2.5 text-[12px] font-medium uppercase tracking-[0.5px] text-white transition-colors hover:border-accent-dark hover:bg-accent-dark" href="{{ route('account.sell-jewellery.create') }}">
          Sell Your Jewellery
        </a>
      </div>
    @endif
  </div>

@endsection
