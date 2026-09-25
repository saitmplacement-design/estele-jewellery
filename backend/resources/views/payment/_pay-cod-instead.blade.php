{{-- Way out of a payment that won't go through: keep the order, pay on
     delivery (PaymentController::switchToCod). --}}
<form class="mt-6" action="{{ route('payment.cod', $order) }}" method="post" data-loading-submit>
  @csrf
  <p class="mb-2 text-[13px] text-muted">Having trouble paying online?</p>
  <button class="btn-cta-outline h-[45px] w-full text-[14px] sm:w-auto sm:px-8" type="submit">
    Pay with Cash on Delivery instead
  </button>
</form>
