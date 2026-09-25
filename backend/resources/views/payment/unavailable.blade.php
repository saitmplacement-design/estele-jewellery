@extends('layouts.app')

@section('meta_title', 'Payment Unavailable | '.($siteSettings['site_name'] ?? 'Estele'))

@section('content')

  <div class="mx-auto w-full max-w-[640px] px-3 py-12 text-center md:px-4 md:py-16">
    <h1 class="mb-2 text-[22px] md:text-[28px]">Payment temporarily unavailable</h1>
    <p class="mb-1 text-[14px] text-muted">Your order has been placed and your items are reserved — Order Number: <strong>{{ $order->order_number }}</strong></p>
    <p class="mb-8 text-[14px] text-muted">We couldn't reach the payment gateway just now. Please try again in a moment.</p>

    <a class="btn-cta w-auto px-8 text-[14px]" href="{{ route('payment.show', $order) }}">
      Try Again
    </a>

    @include('payment._pay-cod-instead')
  </div>

@endsection
