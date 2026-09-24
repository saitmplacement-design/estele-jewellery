@extends('layouts.app')

@section('meta_title', 'My Sell Requests | '.($siteSettings['site_name'] ?? 'Estele'))

@section('content')

  <nav class="mx-auto w-full max-w-wrapper px-3 md:px-4 flex flex-wrap items-center gap-1.5 py-2 text-[13px] text-muted border-b border-line" aria-label="Breadcrumb">
    <x-breadcrumb :items="[['label' => 'My Account', 'url' => route('account.index')], ['label' => 'Sell Your Jewellery', 'url' => route('account.sell-jewellery.landing')], ['label' => 'My Requests']]" />
  </nav>

  <div class="mx-auto w-full max-w-wrapper px-3 pb-10 pt-4 md:pt-6 md:px-4 md:pb-[60px]">
    <div class="mb-6 flex flex-wrap gap-3">
      <a class="inline-flex items-center gap-1.5 rounded-full border border-line px-4 py-2 text-[12px] font-medium uppercase tracking-[0.3px] text-heading transition-colors hover:border-accent hover:text-accent" href="{{ route('account.sell-jewellery.index') }}">
        My Requests
      </a>
      <a class="inline-flex items-center gap-1.5 rounded-full border border-line px-4 py-2 text-[12px] font-medium uppercase tracking-[0.3px] text-heading transition-colors hover:border-accent hover:text-accent" href="{{ route('account.sell-jewellery.wallet') }}">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="6" width="18" height="13" rx="2" /><path d="M3 10h18M7 15h3" /></svg>
        My Wallet
      </a>
    </div>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-[20px] uppercase tracking-[0.5px] md:text-[26px]">My Sell Requests</h1>
      <a class="text-[12px] font-medium uppercase tracking-[0.3px] text-heading underline hover:text-accent" href="{{ route('account.sell-jewellery.create') }}">+ New Request</a>
    </div>

    @if ($requests->isEmpty())
      <div class="rounded-lg border border-line p-8 text-center">
        <p class="mb-4 text-[13px] text-muted">You haven't submitted any jewellery yet.</p>
        <a class="inline-flex items-center justify-center gap-2 border border-accent bg-accent px-6 py-2.5 text-[12px] font-medium uppercase tracking-[0.5px] text-white transition-colors hover:border-accent-dark hover:bg-accent-dark" href="{{ route('account.sell-jewellery.create') }}">
          Sell Your Jewellery
        </a>
      </div>
    @else
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        @foreach ($requests as $request)
          @include('account.sell-jewellery._status-badge', ['request' => $request, 'asCard' => true])
        @endforeach
      </div>

      <div class="mt-8">
        {{ $requests->links() }}
      </div>
    @endif
  </div>

@endsection
