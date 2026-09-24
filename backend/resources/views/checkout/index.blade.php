@extends('layouts.app')

@section('meta_title', 'Checkout | '.($siteSettings['site_name'] ?? 'Estele'))

{{-- Phones: Place Order stays pinned to the bottom edge with the amount due
     (the inline button sits below a long form), and the .buybar hides the tab
     bar so nothing competes with it mid-checkout. It submits #checkout-form
     through the form attribute, so it's the same request as the inline one. --}}
@section('sticky_bar')
  <div class="buybar md:hidden">
    <div class="shrink-0 pl-1 leading-tight">
      <span class="block text-[11px] uppercase tracking-[0.08em] text-muted">Total</span>
      <span class="block text-[17px] font-bold text-heading">₹{{ number_format($subtotal - $discount + $shipping['fee'], 0) }}</span>
    </div>
    <button class="btn-cta h-[49px] flex-1 text-[17px]" type="submit" form="checkout-form">Place Order</button>
  </div>
@endsection

@section('content')

  <nav class="mx-auto w-full max-w-wrapper px-3 md:px-4 flex flex-wrap items-center gap-1.5 py-2 text-[13px] text-muted border-b border-line" aria-label="Breadcrumb">
    <x-breadcrumb :items="[['label' => 'Cart', 'url' => route('cart.index')], ['label' => 'Checkout']]" />
  </nav>

  <div class="mx-auto w-full max-w-wrapper px-3 md:px-4 pb-6 pt-4 md:pt-6 md:pb-[60px]">
    <h1 class="mb-5 text-[18px] md:text-[26px]">Checkout</h1>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-[1fr_340px] md:gap-[34px]">
      <form id="checkout-form" action="{{ route('checkout.store') }}" method="post" class="[&_.field-set]:mb-7" data-loading-submit>
        @csrf

        <fieldset class="field-set">
          <legend class="mb-3.5 text-[14px] font-medium uppercase tracking-[0.5px]">Contact</legend>
          <label class="mb-1.5 block text-[13px] font-medium text-heading" for="customer_email">Email</label>
          <input class="w-full border border-line-strong bg-white px-4 py-3 text-base outline-none transition-colors placeholder:text-muted focus:border-heading mb-3.5" id="customer_email" name="customer_email" type="email" placeholder="you@example.com" value="{{ old('customer_email', auth()->user()?->email) }}" autocomplete="email" required>
          @error('customer_email') <p class="mb-3.5 -mt-2 text-[12px] text-salebadge">{{ $message }}</p> @enderror
          <label class="mb-1.5 block text-[13px] font-medium text-heading" for="customer_phone">Phone</label>
          <input class="w-full border border-line-strong bg-white px-4 py-3 text-base outline-none transition-colors placeholder:text-muted focus:border-heading mb-3.5" id="customer_phone" name="customer_phone" type="tel" placeholder="+91" value="{{ old('customer_phone', auth()->user()?->phone) }}" inputmode="tel" autocomplete="tel" required>
          @error('customer_phone') <p class="mb-3.5 -mt-2 text-[12px] text-salebadge">{{ $message }}</p> @enderror
        </fieldset>

        <fieldset class="field-set" data-address-section>
          <legend class="mb-3.5 text-[14px] font-medium uppercase tracking-[0.5px]">Shipping Address</legend>

          @php
            // Manual entry is the form of last resort: shown right away for a
            // guest or a user with no saved address, otherwise only after
            // "Add another address" or a failed validation round-trip that
            // didn't carry an address_id (means the shopper was already
            // mid-manual-entry when it failed).
            $showManualByDefault = $addresses->isEmpty() || (old('shipping_address_line1') && ! old('address_id'));
          @endphp

          @if($addresses->isNotEmpty())
            <div data-address-picker {{ $showManualByDefault ? 'hidden' : '' }}>
              @foreach($addresses as $address)
                <div class="mb-3 border border-line-strong p-3.5 text-[13px]" data-address-card data-address-id="{{ $address->id }}" {{ ! $loop->first ? 'hidden' : '' }}>
                  <p class="mb-0.5 font-medium uppercase tracking-[0.3px] text-heading">{{ $address->label }}</p>
                  <p class="text-muted">
                    {{ $address->line1 }}{{ $address->line2 ? ', '.$address->line2 : '' }},
                    {{ $address->city }}, {{ $address->state }} {{ $address->postal_code }}
                    @if($address->phone) <br>Phone: {{ $address->phone }} @endif
                  </p>
                </div>
              @endforeach

              <input type="hidden" name="address_id" value="{{ old('address_id', $addresses->first()->id) }}" data-address-id-input>

              <div class="flex flex-wrap gap-x-4 gap-y-1.5">
                @if($addresses->count() > 1)
                  <button class="text-[12px] font-medium text-heading underline hover:text-accent" type="button" data-address-change>Change address</button>
                @endif
                <button class="text-[12px] font-medium text-heading underline hover:text-accent" type="button" data-address-add-new>Add another address</button>
              </div>

              <ul class="mt-3 hidden divide-y divide-line-strong/60 border border-line-strong" data-address-list>
                @foreach($addresses as $address)
                  <li>
                    <button class="block w-full px-3.5 py-2.5 text-left text-[13px] hover:bg-pinksoft" type="button" data-address-pick="{{ $address->id }}">
                      <span class="font-medium uppercase tracking-[0.3px] text-heading">{{ $address->label }}</span>
                      <span class="block text-muted">{{ $address->line1 }}, {{ $address->city }} {{ $address->postal_code }}</span>
                    </button>
                  </li>
                @endforeach
              </ul>
            </div>
          @endif

          <div data-address-manual {{ $showManualByDefault ? '' : 'hidden' }}>
            @if($addresses->isNotEmpty())
              <button class="mb-3.5 text-[12px] font-medium text-heading underline hover:text-accent" type="button" data-address-use-saved>&larr; Use a saved address</button>
            @endif
            <div class="mb-3.5 grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <label class="mb-1.5 block text-[13px] font-medium text-heading" for="customer_first_name">First name</label>
                <input class="w-full border border-line-strong bg-white px-4 py-3 text-base outline-none transition-colors placeholder:text-muted focus:border-heading" id="customer_first_name" name="customer_first_name" type="text" value="{{ old('customer_first_name') }}" autocomplete="given-name">
                @error('customer_first_name') <p class="mt-1 text-[12px] text-salebadge">{{ $message }}</p> @enderror
              </div>
              <div>
                <label class="mb-1.5 block text-[13px] font-medium text-heading" for="customer_last_name">Last name</label>
                <input class="w-full border border-line-strong bg-white px-4 py-3 text-base outline-none transition-colors placeholder:text-muted focus:border-heading" id="customer_last_name" name="customer_last_name" type="text" value="{{ old('customer_last_name') }}" autocomplete="family-name">
                @error('customer_last_name') <p class="mt-1 text-[12px] text-salebadge">{{ $message }}</p> @enderror
              </div>
            </div>
            <label class="mb-1.5 block text-[13px] font-medium text-heading" for="shipping_address_line1">Address</label>
            <input class="w-full border border-line-strong bg-white px-4 py-3 text-base outline-none transition-colors placeholder:text-muted focus:border-heading mb-3.5" id="shipping_address_line1" name="shipping_address_line1" type="text" value="{{ old('shipping_address_line1') }}" autocomplete="address-line1">
            @error('shipping_address_line1') <p class="mb-3.5 -mt-2 text-[12px] text-salebadge">{{ $message }}</p> @enderror
            <div class="mb-3.5 grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <label class="mb-1.5 block text-[13px] font-medium text-heading" for="shipping_city">City</label>
                <input class="w-full border border-line-strong bg-white px-4 py-3 text-base outline-none transition-colors placeholder:text-muted focus:border-heading" id="shipping_city" name="shipping_city" type="text" value="{{ old('shipping_city') }}" autocomplete="address-level2">
                @error('shipping_city') <p class="mt-1 text-[12px] text-salebadge">{{ $message }}</p> @enderror
              </div>
              <div>
                <label class="mb-1.5 block text-[13px] font-medium text-heading" for="shipping_postal_code">PIN code</label>
                <input class="w-full border border-line-strong bg-white px-4 py-3 text-base outline-none transition-colors placeholder:text-muted focus:border-heading" id="shipping_postal_code" name="shipping_postal_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" value="{{ old('shipping_postal_code') }}" autocomplete="postal-code">
                @error('shipping_postal_code') <p class="mt-1 text-[12px] text-salebadge">{{ $message }}</p> @enderror
                <p id="shipping_postal_code_status" class="mt-1 text-[12px] text-muted"></p>
              </div>
            </div>
            <label class="mb-1.5 block text-[13px] font-medium text-heading" for="shipping_state">State</label>
            <select class="w-full border border-line-strong bg-white px-4 py-3 text-base outline-none transition-colors placeholder:text-muted focus:border-heading" id="shipping_state" name="shipping_state">
              <option value="" disabled {{ old('shipping_state') ? '' : 'selected' }}>Select state</option>
              @foreach([
                'Andaman and Nicobar Islands', 'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar',
                'Chandigarh', 'Chhattisgarh', 'Dadra and Nagar Haveli and Daman and Diu', 'Delhi', 'Goa',
                'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jammu and Kashmir', 'Jharkhand', 'Karnataka',
                'Kerala', 'Ladakh', 'Lakshadweep', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya',
                'Mizoram', 'Nagaland', 'Odisha', 'Puducherry', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu',
                'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
              ] as $state)
                <option value="{{ $state }}" {{ old('shipping_state') === $state ? 'selected' : '' }}>{{ $state }}</option>
              @endforeach
            </select>
            @error('shipping_state') <p class="mt-1 text-[12px] text-salebadge">{{ $message }}</p> @enderror
          </div>
        </fieldset>

        <fieldset class="field-set">
          <legend class="mb-3.5 text-[14px] font-medium uppercase tracking-[0.5px]">Payment</legend>
          <ul class="space-y-2.5">
            <li>
              <label class="flex items-center gap-2.5 border border-line-strong p-3.5 text-[14px] {{ $onlinePaymentEnabled ? 'cursor-pointer' : 'cursor-not-allowed opacity-50' }}">
                <input class="accent-accent" type="radio" name="payment_method" value="razorpay" {{ ! $onlinePaymentEnabled ? 'disabled' : '' }} {{ old('payment_method') === 'razorpay' ? 'checked' : '' }}> UPI / Card / Netbanking
              </label>
            </li>
            <li>
              <label class="flex cursor-pointer items-center gap-2.5 border border-line-strong p-3.5 text-[14px]">
                <input class="accent-accent" type="radio" name="payment_method" value="cod" {{ old('payment_method', 'cod') === 'cod' ? 'checked' : '' }}> Cash on Delivery
              </label>
            </li>
          </ul>
          @unless($onlinePaymentEnabled)
            <p class="mt-2 text-[12px] text-muted">Online payment is coming soon.</p>
          @endunless
        </fieldset>

        <div class="field-set">
          <label class="mb-1.5 block text-[13px] font-medium text-heading" for="order_note">Order note (optional)</label>
          <textarea class="w-full border border-line-strong bg-white px-4 py-3 text-base outline-none transition-colors placeholder:text-muted focus:border-heading" id="order_note" name="order_note" rows="3" data-order-note>{{ old('order_note') }}</textarea>
        </div>

        @auth
          @if((float) auth()->user()->wallet_balance > 0)
            <div class="mb-4 rounded-lg border border-line p-4">
              <label class="mb-1.5 block text-[13px] font-medium text-heading" for="wallet_amount">
                Use wallet balance (available: ₹{{ number_format((float) auth()->user()->wallet_balance, 2) }})
              </label>
              <input class="w-full border border-line-strong bg-white px-4 py-2.5 text-[13px]" id="wallet_amount" name="wallet_amount" type="number" min="0" step="0.01" max="{{ auth()->user()->wallet_balance }}" placeholder="0.00" value="{{ old('wallet_amount') }}">
              @error('wallet_amount') <p class="mt-1 text-[12px] text-salebadge">{{ $message }}</p> @enderror
            </div>
          @endif
        @endauth

        <button class="btn-cta h-[49px]" type="submit">Place Order</button>
      </form>

      <aside class="rounded-xl border border-line bg-bagsurface p-5 md:sticky md:top-[100px]">
        <h2 class="mb-4.5 text-[14px] font-bold tracking-[0.04em] text-[#454545]">Order Summary</h2>
        <div class="mb-4.5 divide-y divide-line-strong/40">
          @foreach($items as $item)
            <div class="flex items-center justify-between gap-3 py-2 text-[13px]">
              <span class="text-heading">{{ $item->product->title }} &times; {{ $item->quantity }}</span>
              <span class="shrink-0 font-medium">₹{{ number_format($item->unitPrice() * $item->quantity, 0) }}</span>
            </div>
          @endforeach
        </div>
        @php
          // Prices are stored tax-inclusive, so the GST row states that
          // rather than adding an amount that would double-count.
          $youSave = $discount + max(0, $items->sum(fn ($i) => (($i->product->compare_at_price ?? 0) > $i->unitPrice() ? $i->product->compare_at_price - $i->unitPrice() : 0) * $i->quantity));
        @endphp
        <dl class="mb-4.5 space-y-2.5 text-[14px]">
          <div class="flex justify-between"><dt>Item Total (inclusive of Taxes)</dt><dd class="font-bold">₹{{ number_format($subtotal, 0) }}</dd></div>
          @if($discount > 0)
            <div class="flex justify-between"><dt>Discount</dt><dd class="font-bold text-salebadge">&minus;₹{{ number_format($discount, 0) }}</dd></div>
          @endif
          <div class="flex justify-between"><dt>Shipping{{ ($shipping['estimated'] ?? false) ? ' (estimated)' : '' }}</dt><dd class="font-bold {{ $shipping['fee'] > 0 ? '' : 'text-salebadge' }}">{{ $shipping['fee'] > 0 ? '₹'.number_format($shipping['fee'], 0) : 'FREE' }}</dd></div>
          <div class="flex justify-between"><dt>GST</dt><dd class="text-muted">Included</dd></div>
          <div class="flex justify-between border-t border-line-strong pt-3.5 text-[16px] font-bold"><dt class="text-heading">Total Payable</dt><dd>₹{{ number_format($subtotal - $discount + $shipping['fee'], 0) }}</dd></div>
        </dl>
        @if($youSave > 0)
          <p class="mb-4.5 rounded bg-[#D9F2E3] py-2.5 text-center text-[14px] font-bold text-[#1a7d3f]">You Save ₹{{ number_format($youSave, 0) }} In This Order</p>
        @endif
        <p class="mb-4.5 text-center text-[13px] text-[#454545]">UPI, Cards | Secure Checkout</p>
        @if($shipping['estimated'] ?? false)
          <p class="-mt-3 mb-4.5 text-[11.5px] text-muted">Final shipping cost is confirmed once you enter your delivery address below.</p>
        @endif

        @include('partials.offers-banner')

        <div class="mb-1">
          <div class="mb-1.5 flex items-center justify-between">
            <label class="text-[13px] font-medium text-heading" for="checkout-coupon">Coupon code</label>
            <button class="text-[12px] text-muted underline transition-colors hover:text-accent" type="button" data-coupons-modal-open>View all coupons</button>
          </div>
          @if($couponCode)
            <div class="flex items-center justify-between rounded-[4px] border border-line-strong bg-white px-4 py-3 text-[14px]">
              <span>Applied: <strong>{{ $couponCode }}</strong></span>
              <form action="{{ route('cart.coupon.remove') }}" method="post">
                @csrf
                @method('delete')
                <button class="text-[12px] text-muted underline transition-colors hover:text-[#eb001b]" type="submit">Remove</button>
              </form>
            </div>
          @else
            <form action="{{ route('cart.coupon.apply') }}" method="post" class="flex gap-2" data-loading-submit>
              @csrf
              <input class="w-full border border-line-strong bg-white px-4 py-3 text-base outline-none transition-colors placeholder:text-muted focus:border-heading flex-1" id="checkout-coupon" name="code" type="text" placeholder="Enter code" required>
              <button class="w-[72px] shrink-0 rounded-lg bg-success text-[12px] font-bold text-white" type="submit">Apply</button>
            </form>
          @endif
        </div>

        <div class="-mx-5 -mb-5 mt-5">
          @include('partials.trust-badges')
        </div>
      </aside>
    </div>
  </div>

  @push('scripts')
    <script>
      // Toggles between "use a saved address" and "type a new one". The
      // manual fields are only actually required when they're the ones in
      // play — server-side Rule::requiredIf is the real gate (this can't be
      // trusted alone), this just keeps native HTML5 validation honest so a
      // hidden, unfilled field never blocks submit.
      (function () {
        var section = document.querySelector('[data-address-section]');
        if (! section) return;

        var picker = section.querySelector('[data-address-picker]');
        var manual = section.querySelector('[data-address-manual]');
        var addressIdInput = section.querySelector('[data-address-id-input]');
        var list = section.querySelector('[data-address-list]');
        var manualFieldIds = ['shipping_address_line1', 'shipping_city', 'shipping_postal_code', 'shipping_state'];

        function setManualRequired(required) {
          manualFieldIds.forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.required = required;
          });
        }

        function showManual() {
          if (picker) picker.hidden = true;
          if (manual) manual.hidden = false;
          if (addressIdInput) addressIdInput.value = '';
          setManualRequired(true);
        }

        function showPicker() {
          if (picker) picker.hidden = false;
          if (manual) manual.hidden = true;
          setManualRequired(false);
        }

        function selectAddress(id) {
          section.querySelectorAll('[data-address-card]').forEach(function (card) {
            card.hidden = card.getAttribute('data-address-id') !== String(id);
          });
          if (addressIdInput) addressIdInput.value = id;
          if (list) list.classList.add('hidden');
        }

        if (picker && ! picker.hidden) setManualRequired(false);

        var addNewBtn = section.querySelector('[data-address-add-new]');
        if (addNewBtn) addNewBtn.addEventListener('click', showManual);

        var useSavedBtn = section.querySelector('[data-address-use-saved]');
        if (useSavedBtn) {
          useSavedBtn.addEventListener('click', function () {
            var firstCard = section.querySelector('[data-address-card]');
            if (firstCard) selectAddress(firstCard.getAttribute('data-address-id'));
            showPicker();
          });
        }

        var changeBtn = section.querySelector('[data-address-change]');
        if (changeBtn && list) {
          changeBtn.addEventListener('click', function () {
            list.classList.toggle('hidden');
          });
        }

        section.querySelectorAll('[data-address-pick]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            selectAddress(btn.getAttribute('data-address-pick'));
          });
        });
      })();
    </script>

    <script>
      (function () {
        var postalInput = document.getElementById('shipping_postal_code');
        var cityInput = document.getElementById('shipping_city');
        var stateSelect = document.getElementById('shipping_state');
        var statusEl = document.getElementById('shipping_postal_code_status');
        var lastLookedUp = null;

        function setStatus(text) {
          statusEl.textContent = text;
        }

        function lookup(pincode) {
          if (pincode === lastLookedUp) return;
          lastLookedUp = pincode;
          setStatus('Looking up city/state…');

          fetch('{{ url('/checkout/pincode') }}/' + pincode, { headers: { 'Accept': 'application/json' } })
            .then(function (res) {
              if (!res.ok) throw new Error('not found');
              return res.json();
            })
            .then(function (data) {
              if (data.city && !cityInput.value) cityInput.value = data.city;
              if (data.state) {
                var matched = Array.from(stateSelect.options).some(function (opt) {
                  if (opt.value.toLowerCase() === data.state.toLowerCase()) {
                    stateSelect.value = opt.value;
                    return true;
                  }
                  return false;
                });
                setStatus(matched ? 'City/state auto-filled from PIN code.' : '');
              }
            })
            .catch(function () {
              setStatus('Could not find this PIN code — please fill city/state manually.');
            });
        }

        postalInput.addEventListener('input', function () {
          var value = postalInput.value.trim();
          if (/^[0-9]{6}$/.test(value)) {
            lookup(value);
          } else {
            setStatus('');
            lastLookedUp = null;
          }
        });
      })();
    </script>

    <script>
      // Autosaves the checkout form to localStorage as the customer types, and
      // restores it on the next load — a refresh (or an accidental tab close)
      // shouldn't cost someone their whole address entry. Only fills fields
      // that are still blank, so it never overrides old() values Laravel has
      // already repopulated after a failed-validation redirect.
      (function () {
        var STORAGE_KEY = 'checkout_form_draft';
        var form = document.querySelector('form[action="{{ route('checkout.store') }}"]');
        if (!form) return;

        var fields = Array.prototype.filter.call(form.elements, function (el) {
          return el.name && el.name !== '_token';
        });

        var draft = {};
        try {
          draft = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
        } catch (e) {
          draft = {};
        }

        // Radios/checkboxes are restored per group, and only when nothing in
        // the group is already checked — Blade's old() already renders a
        // checked attribute after a failed-validation redirect, and that
        // server value must win over a possibly-stale draft.
        var restoredGroups = {};

        fields.forEach(function (el) {
          if (!(el.name in draft)) return;

          if (el.type === 'radio' || el.type === 'checkbox') {
            if (restoredGroups[el.name]) return;
            var groupAlreadyChecked = fields.some(function (other) {
              return other.name === el.name && other.checked;
            });
            if (groupAlreadyChecked) {
              restoredGroups[el.name] = true;
              return;
            }
            el.checked = el.value === draft[el.name];
          } else if (!el.value) {
            el.value = draft[el.name];
          }
        });

        function save() {
          var data = {};
          fields.forEach(function (el) {
            if (el.type === 'radio' || el.type === 'checkbox') {
              if (el.checked) data[el.name] = el.value;
            } else {
              data[el.name] = el.value;
            }
          });
          localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        }

        form.addEventListener('input', save);
        form.addEventListener('change', save);
        form.addEventListener('submit', function () {
          localStorage.removeItem(STORAGE_KEY);
        });
      })();
    </script>
  @endpush
@endsection
