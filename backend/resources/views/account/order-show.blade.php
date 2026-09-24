@extends('layouts.app')

@section('meta_title', 'Order #'.$order->order_number.' | '.($siteSettings['site_name'] ?? 'Estele'))

@section('content')

  @php
    // 'accepted' is a real stage every order passes through (Order::ALLOWED_TRANSITIONS
    // goes placed -> accepted -> packed), and the admin labels it "Accepted". Omitting it
    // here left the bar frozen on "Placed" the whole time the store had already accepted.
    $pipeline = ['placed' => 'Placed', 'accepted' => 'Accepted', 'packed' => 'Packed', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];
    $pipelineKeys = array_keys($pipeline);
    $isTerminalOffPipeline = in_array($order->status, ['cancelled', 'returned'], true);
    // array_search returns false for an unknown status, which compares as 0 and would
    // silently light up step 1; -1 leaves the whole bar inactive instead.
    $currentStep = ($found = array_search($order->status, $pipelineKeys, true)) === false ? -1 : $found;
  @endphp

  <nav class="mx-auto w-full max-w-wrapper px-3 md:px-4 flex flex-wrap items-center gap-1.5 py-2 text-[13px] text-muted border-b border-line" aria-label="Breadcrumb">
    <x-breadcrumb :items="[['label' => 'My Account', 'url' => route('account.index')], ['label' => 'Order #'.$order->order_number]]" />
  </nav>

  <div class="mx-auto w-full max-w-wrapper px-3 pb-10 pt-4 md:pt-6 md:px-4 md:pb-[60px]">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-[20px] uppercase tracking-[0.5px] md:text-[26px]">Order #{{ $order->order_number }}</h1>
        <p class="mt-1 text-[13px] text-muted">Placed {{ $order->created_at->format('d M Y, h:i A') }}</p>
      </div>
      <a class="inline-flex items-center justify-center gap-2 border border-line-strong bg-white px-6 py-2.5 text-[12px] font-medium uppercase tracking-[0.5px] text-heading transition-colors hover:border-heading" href="{{ route('account.orders.invoice', $order) }}">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
        Download Invoice
      </a>
    </div>

    {{-- Status timeline --}}
    <div class="mb-8 rounded-lg border border-line p-5">
      @if($isTerminalOffPipeline)
        <span class="inline-block rounded-full bg-pinksoft px-3 py-1 text-[12px] font-medium uppercase tracking-[0.3px] text-accent">{{ ucfirst($order->status) }}</span>
        @if($order->tracking_status)
          <p class="mt-2 text-[13px] text-muted">{{ $order->tracking_status }}</p>
        @endif
      @else
        <div class="flex items-center justify-between">
          @foreach($pipeline as $key => $label)
            @php $stepIndex = array_search($key, $pipelineKeys, true); @endphp
            <div class="flex flex-1 flex-col items-center text-center">
              <span class="mb-1.5 grid h-7 w-7 place-items-center rounded-full text-[11px] font-medium {{ $stepIndex <= $currentStep ? 'bg-heading text-white' : 'bg-greysoft text-muted' }}">
                @if($stepIndex < $currentStep)
                  <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg>
                @else
                  {{ $stepIndex + 1 }}
                @endif
              </span>
              <span class="text-[11px] uppercase tracking-[0.3px] {{ $stepIndex <= $currentStep ? 'text-heading' : 'text-muted' }}">{{ $label }}</span>
            </div>
            @if(! $loop->last)
              <div class="h-0.5 flex-1 {{ $stepIndex < $currentStep ? 'bg-heading' : 'bg-greysoft' }}" style="margin-top: -20px;"></div>
            @endif
          @endforeach
        </div>
        @if($order->tracking_number)
          <p class="mt-5 text-[13px] text-muted">
            Tracking: <span class="font-medium text-heading">{{ $order->tracking_number }}</span>
            @if($order->carrier) &middot; {{ $order->carrier }} @endif
          </p>
        @endif
      @endif

      @if($order->cancellation_requested_at)
        <p class="mt-4 rounded-lg border border-line bg-pinksoft px-4 py-3 text-[13px] text-heading">
          A cancellation/return request was submitted on {{ $order->cancellation_requested_at->format('d M Y') }} — our team will follow up shortly.
        </p>
      @elseif($order->canRequestCancellation())
        <details class="mt-5 border-t border-line pt-4" data-cancel-request>
          <summary class="cursor-pointer text-[13px] font-medium uppercase tracking-[0.4px] text-accent">
            {{ in_array($order->status, \App\Models\Order::RETURNABLE_STATUSES, true) ? 'Request Return' : 'Request Cancellation' }}
          </summary>
          <form class="mt-3" action="{{ route('account.orders.cancellation-request', $order) }}" method="post">
            @csrf
            <label class="mb-1.5 block text-[13px] font-medium text-heading" for="reason">Reason</label>
            <textarea class="mb-3 w-full border border-line-strong bg-white px-4 py-3 text-[14px] outline-none transition-colors placeholder:text-muted focus:border-heading" id="reason" name="reason" rows="2" required maxlength="255"></textarea>
            <button class="inline-flex items-center justify-center gap-2 border border-accent bg-accent px-6 py-2.5 text-[12px] font-medium uppercase tracking-[0.5px] text-white transition-colors hover:border-accent-dark hover:bg-accent-dark" type="submit">
              Submit Request
            </button>
          </form>
        </details>
      @endif
    </div>

    <x-orders.summary :order="$order" />
  </div>

@endsection
