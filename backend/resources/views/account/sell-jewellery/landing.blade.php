@extends('layouts.app')

@section('meta_title', 'Sell Your Jewellery | '.($siteSettings['site_name'] ?? 'Estele'))

@section('content')

  <nav class="mx-auto w-full max-w-wrapper px-3 md:px-4 flex flex-wrap items-center gap-1.5 py-2 text-[13px] text-muted border-b border-line" aria-label="Breadcrumb">
    <x-breadcrumb :items="[['label' => 'My Account', 'url' => route('account.index')], ['label' => 'Sell Your Jewellery']]" />
  </nav>

  <div class="mx-auto w-full max-w-wrapper px-3 pb-6 pt-4 md:pt-6 md:px-4 md:pb-[60px]">
    <div class="mb-6 flex flex-wrap gap-3">
      <a class="inline-flex items-center gap-1.5 rounded-full border border-line px-4 py-2 text-[12px] font-medium uppercase tracking-[0.3px] text-heading transition-colors hover:border-accent hover:text-accent" href="{{ route('account.sell-jewellery.index') }}">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6" transform="rotate(90 12 12)" /><rect x="3" y="4" width="18" height="16" rx="2" /></svg>
        My Requests
      </a>
      <a class="inline-flex items-center gap-1.5 rounded-full border border-line px-4 py-2 text-[12px] font-medium uppercase tracking-[0.3px] text-heading transition-colors hover:border-accent hover:text-accent" href="{{ route('account.sell-jewellery.wallet') }}">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="6" width="18" height="13" rx="2" /><path d="M3 10h18M7 15h3" /></svg>
        My Wallet
      </a>
    </div>

    <div class="mb-8 rounded-lg border border-line bg-pinksoft p-6 md:p-10">
      <h1 class="mb-3 text-[22px] uppercase tracking-[0.5px] md:text-[30px]">Sell Your Old Jewellery</h1>
      <p class="max-w-2xl text-[14px] text-muted">
        Get a fair offer for your old gold and jewellery. Upload a photo and a
        short video, and once your offer is ready, the amount is credited
        straight to your Estele wallet.
      </p>
      <a class="mt-6 inline-flex items-center justify-center gap-2 border border-accent bg-accent px-6 py-2.5 text-[12px] font-medium uppercase tracking-[0.5px] text-white transition-colors hover:border-accent-dark hover:bg-accent-dark" href="{{ route('account.sell-jewellery.create') }}">
        Sell Your Jewellery
      </a>
    </div>

    <h2 class="mb-4 text-[16px] uppercase tracking-[0.4px] text-heading">How It Works</h2>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
      <div class="rounded-lg border border-line p-4">
        <div class="mb-2 text-[24px] font-medium text-accent">1</div>
        <p class="text-[13px] font-medium uppercase tracking-[0.3px] text-heading">Submit Details</p>
        <p class="mt-1 text-[13px] text-muted">Upload a photo and a short video of your jewellery, with any details you'd like to share.</p>
      </div>
      <div class="rounded-lg border border-line p-4">
        <div class="mb-2 text-[24px] font-medium text-accent">2</div>
        <p class="text-[13px] font-medium uppercase tracking-[0.3px] text-heading">We Review &amp; Offer</p>
        <p class="mt-1 text-[13px] text-muted">Your submission is reviewed and the best offer is prepared for you.</p>
      </div>
      <div class="rounded-lg border border-line p-4">
        <div class="mb-2 text-[24px] font-medium text-accent">3</div>
        <p class="text-[13px] font-medium uppercase tracking-[0.3px] text-heading">Wallet Credited</p>
        <p class="mt-1 text-[13px] text-muted">The amount is credited to your wallet, valid for 10 days.</p>
      </div>
    </div>
  </div>

@endsection