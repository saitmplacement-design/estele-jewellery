@extends('layouts.app')

@section('meta_title', 'My Addresses | '.($siteSettings['site_name'] ?? 'Estele'))

@section('content')

  <nav class="mx-auto w-full max-w-wrapper px-3 md:px-4 flex flex-wrap items-center gap-1.5 py-2 text-[13px] text-muted border-b border-line" aria-label="Breadcrumb">
    <x-breadcrumb :items="[['label' => 'My Account', 'url' => route('account.index')], ['label' => 'Addresses']]" />
  </nav>

  <div class="mx-auto w-full max-w-wrapper px-3 pb-10 pt-4 md:pt-6 md:px-4 md:pb-[60px]">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-[20px] uppercase tracking-[0.5px] md:text-[26px]">My Addresses</h1>
      <a class="text-[13px] font-medium text-heading underline hover:text-accent" href="{{ route('account.index') }}">&larr; Back to Account</a>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2">
      @foreach($addresses as $address)
        <div class="rounded-lg border border-line p-4">
          <div class="mb-2 flex items-center justify-between gap-2">
            <span class="text-[13px] font-medium uppercase tracking-[0.3px] text-heading">{{ $address->label }}</span>
            @if($address->is_default)
              <span class="rounded-full bg-pinksoft px-2.5 py-0.5 text-[10px] font-medium uppercase tracking-[0.3px] text-accent">Default</span>
            @endif
          </div>
          <p class="text-[13px] text-muted">
            {{ $address->line1 }}{{ $address->line2 ? ', '.$address->line2 : '' }},
            {{ $address->city }}, {{ $address->state }} {{ $address->postal_code }}, {{ $address->country }}
            @if($address->phone) <br>Phone: {{ $address->phone }} @endif
          </p>

          <div class="mt-3 flex items-center gap-4">
            <details class="[&_summary]:cursor-pointer">
              <summary class="text-[12px] font-medium uppercase tracking-[0.3px] text-heading underline hover:text-accent">Edit</summary>
              <form class="mt-3 border-t border-line pt-3" action="{{ route('account.addresses.update', $address) }}" method="post">
                @csrf
                @method('PATCH')
                @include('account._address-fields', ['address' => $address])
                <button class="mt-3 inline-flex items-center justify-center gap-2 border border-accent bg-accent px-6 py-2.5 text-[12px] font-medium uppercase tracking-[0.5px] text-white transition-colors hover:border-accent-dark hover:bg-accent-dark" type="submit">
                  Save Changes
                </button>
              </form>
            </details>

            <form action="{{ route('account.addresses.destroy', $address) }}" method="post" onsubmit="return confirm('Remove this address?');">
              @csrf
              @method('DELETE')
              <button class="text-[12px] font-medium uppercase tracking-[0.3px] text-salebadge underline hover:opacity-80" type="submit">Remove</button>
            </form>
          </div>
        </div>
      @endforeach
    </div>

    <details class="rounded-lg border border-line p-4">
      <summary class="cursor-pointer text-[13px] font-medium uppercase tracking-[0.4px] text-heading">+ Add New Address</summary>
      <form class="mt-4" action="{{ route('account.addresses.store') }}" method="post">
        @csrf
        @include('account._address-fields')
        <button class="mt-3 inline-flex items-center justify-center gap-2 border border-accent bg-accent px-6 py-2.5 text-[12px] font-medium uppercase tracking-[0.5px] text-white transition-colors hover:border-accent-dark hover:bg-accent-dark" type="submit">
          Save Address
        </button>
      </form>
    </details>
  </div>

  @push('scripts')
    <script>
      (function () {
        // Delegated: this page can render several address forms at once
        // (one per saved address, plus "Add New"), so inputs aren't unique
        // by id — listen on document and scope lookups to the closest form.
        var lastLookedUp = {};

        function setStatus(form, text) {
          var el = form.querySelector('[data-pincode-lookup-status]');
          if (el) el.textContent = text;
        }

        function lookup(form, pincode) {
          if (lastLookedUp[pincode] === form) return;
          lastLookedUp[pincode] = form;
          setStatus(form, 'Looking up city/state…');

          fetch('{{ url('/checkout/pincode') }}/' + pincode, { headers: { 'Accept': 'application/json' } })
            .then(function (res) {
              if (!res.ok) throw new Error('not found');
              return res.json();
            })
            .then(function (data) {
              var cityInput = form.querySelector('[data-pincode-city]');
              var stateInput = form.querySelector('[data-pincode-state]');
              if (data.city && cityInput) cityInput.value = data.city;
              if (data.state && stateInput) stateInput.value = data.state;
              setStatus(form, 'City/state auto-filled from PIN code.');
            })
            .catch(function () {
              setStatus(form, 'Could not find city/state for this PIN code — please fill manually.');
            });
        }

        document.addEventListener('input', function (event) {
          var input = event.target;
          if (!input.matches || !input.matches('[data-pincode-lookup]')) return;

          var pincode = input.value.trim();
          var form = input.closest('form');
          if (!form) return;

          if (!/^[0-9]{6}$/.test(pincode)) {
            setStatus(form, '');
            return;
          }

          lookup(form, pincode);
        });
      })();
    </script>
  @endpush

@endsection
