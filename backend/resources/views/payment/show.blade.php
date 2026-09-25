@extends('layouts.app')

@section('meta_title', 'Complete Payment | '.($siteSettings['site_name'] ?? 'Estele'))

@section('content')

  <div class="mx-auto w-full max-w-[640px] px-3 py-12 text-center md:px-4 md:py-16">
    <h1 class="mb-2 text-[22px] md:text-[28px]">Complete your payment</h1>
    <p class="mb-8 text-[14px] text-muted">Order Number: <strong>{{ $order->order_number }}</strong></p>

    <div id="payment-error" class="mb-6 hidden border border-salebadge/40 bg-salebadge/10 p-4 text-left text-[13px] text-salebadge"></div>

    @if (session('error'))
      <div class="mb-6 border border-salebadge/40 bg-salebadge/10 p-4 text-left text-[13px] text-salebadge">{{ session('error') }}</div>
    @endif

    <div class="mb-8 rounded-lg border border-line p-5 text-left">
      @if((float) $order->wallet_amount_used > 0)
        <div class="flex items-center justify-between gap-3 pb-3 text-[13px] text-muted">
          <span>Order total ₹{{ number_format($order->total, 2) }} &middot; paid from wallet</span>
          <span>&minus;₹{{ number_format($order->wallet_amount_used, 2) }}</span>
        </div>
      @endif
      <div class="flex items-center justify-between border-t border-line pt-3 text-[15px] first:border-t-0 first:pt-0">
        <span class="font-medium text-heading">Amount payable</span>
        <span class="font-medium text-price">₹{{ number_format($order->amountDue(), 2) }}</span>
      </div>
    </div>

    <button id="pay-now-btn" type="button" class="btn-cta h-[49px]">
      Pay Now
    </button>

    @include('payment._pay-cod-instead')

    <form id="razorpay-callback-form" action="{{ route('payment.callback', $order) }}" method="post" class="hidden">
      @csrf
      <input type="hidden" name="razorpay_order_id">
      <input type="hidden" name="razorpay_payment_id">
      <input type="hidden" name="razorpay_signature">
    </form>
  </div>

  @push('scripts')
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
      (function () {
        var payBtn = document.getElementById('pay-now-btn');
        var errorBox = document.getElementById('payment-error');

        function showError(message) {
          errorBox.textContent = message;
          errorBox.classList.remove('hidden');
        }

        function openCheckout() {
          // checkout.js is blocked by some ad blockers and flaky networks;
          // without it, "Pay Now" used to do nothing at all.
          if (typeof window.Razorpay !== 'function') {
            showError('The payment window could not be loaded. Check your internet connection (or turn off any ad blocker) and reload this page.');
            return;
          }

          payBtn.disabled = true;

          // A rejected key/order (e.g. a mistyped key) makes Razorpay throw
          // here; without this the button stayed disabled and nothing happened.
          try {
            var rzp = new Razorpay({
              key: @json($razorpayKeyId),
              amount: @json($amountPaise),
              currency: 'INR',
              name: @json($siteSettings['site_name'] ?? 'Estele'),
              description: @json('Order '.$order->order_number),
              order_id: @json($order->razorpay_order_id),
              prefill: {
                name: @json($order->customer_name),
                email: @json($order->customer_email),
                contact: @json($order->customer_phone),
              },
              handler: function (response) {
                var form = document.getElementById('razorpay-callback-form');
                form.querySelector('[name="razorpay_order_id"]').value = response.razorpay_order_id;
                form.querySelector('[name="razorpay_payment_id"]').value = response.razorpay_payment_id;
                form.querySelector('[name="razorpay_signature"]').value = response.razorpay_signature;
                payBtn.textContent = 'Confirming payment…';
                form.submit();
              },
              modal: {
                ondismiss: function () { payBtn.disabled = false; },
              },
            });

            rzp.on('payment.failed', function (response) {
              showError('Payment failed: ' + (response.error && response.error.description ? response.error.description : 'please try again.') + ' You can try again with the same or another method.');
              payBtn.disabled = false;
            });

            rzp.open();
          } catch (e) {
            payBtn.disabled = false;
            showError('The payment window could not be opened. Please try again, or choose Cash on Delivery below.');
          }
        }

        payBtn.addEventListener('click', openCheckout);

        // Straight from "Place Order", open the payment window without an
        // extra tap. Not after a failed/rejected attempt (a flashed error),
        // so the customer can read the message first.
        @if(! session('error'))
          openCheckout();
        @endif
      })();
    </script>
  @endpush

@endsection
