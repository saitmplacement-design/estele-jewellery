@extends('layouts.app')

@section('meta_title', 'My Account | '.($siteSettings['site_name'] ?? 'Estele'))
@section('meta_description', 'View your order history and manage your account details.')

@section('content')

  @php($initials = \Illuminate\Support\Str::of(auth()->user()->name)->trim()->explode(' ')->filter()->take(2)->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))->implode('') ?: 'E')

  {{-- Greeting banner, phones only. It repeats the name the identity card
       below already carries, so on desktop — where both are in view at once —
       it's dropped rather than shown twice. --}}
  <div class="mx-auto w-full max-w-wrapper px-3 pt-3 md:hidden">
    <div class="grad-soft flex items-center gap-3 rounded-2xl border border-accent/15 px-4 py-3.5">
      <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-accent-dark text-[16px] font-bold uppercase text-white" aria-hidden="true">{{ $initials }}</span>
      <div class="min-w-0 flex-1">
        <p class="text-[16px] font-bold leading-tight text-heading">Welcome back!</p>
        <p class="mt-0.5 truncate text-[12px] text-muted">Good to see you again</p>
      </div>
      <svg class="h-5 w-5 shrink-0 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21.2l7.7-7.7 1.1-1.1a5.5 5.5 0 0 0 0-7.8z"/></svg>
    </div>
  </div>

  <nav class="mx-auto w-full max-w-wrapper px-3 md:px-4 flex flex-wrap items-center gap-1.5 py-2 text-[13px] text-muted border-b border-line" aria-label="Breadcrumb">
    <x-breadcrumb :items="[['label' => 'My Account']]" />
  </nav>

  <div class="mx-auto w-full max-w-wrapper px-3 pb-6 pt-4 md:pt-6 md:px-4 md:pb-[60px]">

    @if(session('success'))
      <p class="mb-5 rounded-lg border border-line bg-pinksoft px-4 py-3 text-[13px] text-heading">{{ session('success') }}</p>
    @endif

    {{-- Identity card. Avatar initials stand in for a photo we don't store.
         Stacks to centred-left on phones and keeps the logout control on the
         same row from sm up, so the card never grows a second wrapped line. --}}
    <section class="mb-6 rounded-2xl border border-line bg-pinksoft p-4 sm:p-6">
      <div class="flex flex-wrap items-center gap-4">
        <span class="grid h-14 w-14 shrink-0 place-items-center rounded-full bg-accent-dark text-[18px] font-bold uppercase tracking-[0.5px] text-white sm:h-16 sm:w-16 sm:text-[20px]" aria-hidden="true">
          {{ $initials }}
        </span>

        <div class="min-w-0 flex-1">
          <h1 class="line-clamp-2 break-words text-[18px] font-bold uppercase leading-tight tracking-[0.5px] text-heading sm:text-[20px] md:text-[24px]">{{ auth()->user()->name }}</h1>
          @if(auth()->user()->phone)
            <p class="mt-1 flex items-center gap-1.5 truncate text-[13px] text-muted">
              <svg class="h-3.5 w-3.5 shrink-0 text-accent-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1.1 1A16 16 0 0 1 4 5.1 1 1 0 0 1 5 4z"/></svg>
              {{ auth()->user()->phone }}
            </p>
          @else
            <p class="mt-1 truncate text-[13px] text-muted">{{ auth()->user()->email }}</p>
          @endif
          {{-- Phones: the badge sits under the contact line, so the name gets
               the full row instead of being truncated to a few letters. --}}
          <span class="pill pill-brand mt-2 sm:hidden">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7l4.5 3L12 4l4.5 6L21 7l-2 11H5z"/></svg>
            Premium Member
          </span>
        </div>

        <span class="pill pill-brand hidden shrink-0 sm:inline-flex">
          <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7l4.5 3L12 4l4.5 6L21 7l-2 11H5z"/></svg>
          Premium Member
        </span>

        <div class="flex w-full shrink-0 flex-wrap gap-2 sm:w-auto">
          <a class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg border border-line-strong bg-white px-5 py-3 text-[12px] font-bold uppercase tracking-[0.5px] text-heading transition-colors hover:border-heading sm:flex-none" href="#profile-details">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
            Edit Profile
          </a>
          <form class="flex-1 sm:flex-none" action="{{ route('logout') }}" method="post">
            @csrf
            <button class="grad-brand inline-flex w-full items-center justify-center gap-2 rounded-lg px-5 py-3 text-[12px] font-bold uppercase tracking-[0.5px] text-white transition-opacity hover:opacity-90" type="submit">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
              Logout
            </button>
          </form>
        </div>
      </div>
    </section>

    {{-- md:grid-cols-[1fr_340px] is an arbitrary value with no live Tailwind
         build to compile it here (this backend has no build of its own — see the
         "no live Tailwind build" note in home/index.blade.php); inline style
         sidesteps that the same way the hero banner section does. --}}
    <style>
      @media (min-width: 1024px) {
        .account-layout-grid { grid-template-columns: 1fr 340px; }
      }
    </style>
    <div class="account-layout-grid mb-8 grid grid-cols-1 items-start gap-8 lg:gap-[34px]">

      {{-- Account menu, in the order the customer actually uses it: orders,
           addresses, sell, rewards, settings, help. Each row is one full-width
           link so the whole strip is tappable on a phone. --}}
      <section aria-label="Account menu">
        <div class="mb-3 flex items-end justify-between gap-3">
          <h2 class="inline-block border-b-2 border-accent-dark pb-1 text-[14px] font-bold uppercase tracking-[0.4px] text-heading">Account Menu</h2>
          <span class="text-[11px] italic text-muted">Manage your orders, address &amp; more</span>
        </div>

        @php($menu = [
          [
            'label' => 'My Orders',
            'note' => 'Track and manage orders',
            'url' => '#order-history',
            'icon' => '<path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5z"/><path d="m3 7.5 9 4.5 9-4.5"/><path d="M12 12v9"/>',
          ],
          [
            'label' => 'My Addresses',
            'note' => 'Manage delivery addresses',
            'url' => route('account.addresses'),
            'icon' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>',
          ],
          [
            // Falls back to the "no request yet" copy so the row still
            // renders if the view is built without the controller's state.
            'label' => ($sellJewellery ?? [])['label'] ?? 'Sell Your Jewellery',
            'note' => ($sellJewellery ?? [])['note'] ?? 'Get an offer for old gold',
            'url' => ($sellJewellery ?? [])['url'] ?? route('account.sell-jewellery.create'),
            'icon' => '<path d="M6 3h12l3 6-9 12L3 9z"/><path d="M3 9h18"/><path d="m12 3-3 6 3 12 3-12z"/>',
            'featured' => true,
            'badge' => ($sellJewellery ?? [])['badge'] ?? 'Get an Offer',
          ],
          [
            'label' => 'Rewards & Wallet',
            'note' => 'Balance and unboxing rewards',
            'url' => route('account.rewards.index'),
            'icon' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M3 11h18"/><path d="M16 15h2"/>',
          ],
          [
            'label' => 'Wishlist',
            'note' => 'Your saved items',
            'url' => route('wishlist'),
            'icon' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21.2l7.7-7.7 1.1-1.1a5.5 5.5 0 0 0 0-7.8z"/>',
          ],
          [
            'label' => 'Account Settings',
            'note' => 'Name and contact details',
            'url' => '#profile-details',
            'icon' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2 2 2 0 1 1-4 0 1.7 1.7 0 0 0-2.9-1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.7 1.7 0 0 0 3 15a2 2 0 1 1 0-4 1.7 1.7 0 0 0 1.2-2.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.7 1.7 0 0 0 10 4.1a2 2 0 1 1 4 0 1.7 1.7 0 0 0 2.9 1.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1A1.7 1.7 0 0 0 21 11a2 2 0 1 1 0 4z"/>',
          ],
          [
            'label' => 'Help & Support',
            'note' => 'FAQ and contact us',
            'url' => route('faq.index'),
            'icon' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.2 2.4c-.6.2-.7.6-.7 1.1v.5"/><path d="M12 17h.01"/>',
          ],
        ])

        <ul class="space-y-2.5">
          @foreach($menu as $item)
            <li>
              <a class="menu-row @if($item['featured'] ?? false) menu-row-featured @endif" href="{{ $item['url'] }}">
                <span class="icon-tile @if($item['featured'] ?? false) bg-white/70 @endif" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">{!! $item['icon'] !!}</svg>
                </span>
                <span class="min-w-0 flex-1">
                  <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span class="menu-row-title truncate">{{ $item['label'] }}</span>
                    @if($item['badge'] ?? false)
                      <span class="pill pill-gold">{{ $item['badge'] }}</span>
                    @endif
                  </span>
                  <span class="menu-row-note block truncate">{{ $item['note'] }}</span>
                </span>
                @if($item['featured'] ?? false)
                  <span class="menu-row-featured-chev" aria-hidden="true">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
                  </span>
                @else
                  <svg class="menu-row-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                @endif
              </a>
            </li>
          @endforeach
        </ul>
      </section>

      <aside class="space-y-6">
        {{-- Wallet balance, mirrored from the rewards page so the overview
             answers "how much do I have?" without a second page load. --}}
        <section class="rounded-lg border border-line p-4 sm:p-5">
          <h2 class="text-[13px] font-medium uppercase tracking-[0.4px] text-heading">Wallet Balance</h2>
          <p class="mt-2 text-[26px] font-medium leading-none text-price">₹{{ number_format((float) $walletBalance, 2) }}</p>
          <a class="mt-4 inline-flex w-full items-center justify-center gap-2 border border-line-strong bg-white px-5 py-2.5 text-[12px] font-medium uppercase tracking-[0.5px] text-heading transition-colors hover:border-heading" href="{{ route('account.rewards.index') }}">
            View Rewards &amp; Wallet
          </a>
        </section>

        {{-- Collapsed by default: the details it holds are already shown in the
             identity card above, so this only needs to open when the customer
             actually wants to edit. "Edit Profile" targets #profile-details,
             and :target in the stylesheet opens it on that jump; a successful
             save redirects to the bare account URL, so it closes again on its
             own. It stays open on a validation error, which is the one case
             where the form must come back visible with its message. --}}
        <details class="marker-pm rounded-2xl border border-line p-4 sm:p-5" id="profile-details" @if($errors->hasAny(['name', 'email'])) open @endif>
          <summary class="flex cursor-pointer items-center gap-2 text-[13px] font-medium uppercase tracking-[0.4px] text-heading">Profile Details</summary>
          <form class="mt-4" action="{{ route('account.profile') }}" method="post">
            @csrf
            @method('PATCH')
            <label class="mb-1.5 block text-[13px] font-medium text-heading" for="name">Full name</label>
            <input class="w-full border border-line-strong bg-white px-4 py-3 text-[14px] outline-none transition-colors placeholder:text-muted focus:border-heading mb-3.5" id="name" name="name" type="text" value="{{ old('name', auth()->user()->name) }}" required>
            @error('name') <p class="mb-3.5 -mt-2 text-[12px] text-salebadge">{{ $message }}</p> @enderror

            <label class="mb-1.5 block text-[13px] font-medium text-heading" for="email">Email address</label>
            <input class="w-full border border-line-strong bg-white px-4 py-3 text-[14px] outline-none transition-colors placeholder:text-muted focus:border-heading mb-3.5" id="email" name="email" type="email" value="{{ old('email', auth()->user()->email) }}" placeholder="Not added" autocomplete="email">
            @error('email') <p class="mb-3.5 -mt-2 text-[12px] text-salebadge">{{ $message }}</p> @enderror

            {{-- Read-only on purpose: the OTP login resolves an account by this
                 number, so letting it be edited here — with no verification of
                 the new one — would lock the customer out of their own account.
                 Changing it needs its own OTP-verified flow. --}}
            <label class="mb-1.5 block text-[13px] font-medium text-heading" for="phone">Mobile number</label>
            <input class="w-full border border-line-strong bg-pinksoft/40 px-4 py-3 text-[14px] text-muted placeholder:text-muted" id="phone" type="text" value="{{ auth()->user()->phone }}" placeholder="Not added" disabled>
            <p class="mb-3.5 mt-1.5 text-[11px] leading-snug text-muted">Your mobile number is how you sign in, so it can't be changed here. Contact support to update it.</p>

            <div class="flex flex-wrap gap-2">
              <button class="grad-brand inline-flex flex-1 items-center justify-center gap-2 rounded-lg px-6 py-3 text-[12px] font-bold uppercase tracking-[0.5px] text-white transition-opacity hover:opacity-90" type="submit">
                Save Changes
              </button>
              <button class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg border border-line-strong bg-white px-6 py-3 text-[12px] font-bold uppercase tracking-[0.5px] text-heading transition-colors hover:border-heading" type="button" data-profile-cancel>
                Cancel
              </button>
            </div>
          </form>
        </details>
      </aside>
    </div>

    <section id="order-history">
      {{-- Order status tiles. Two-up on phones (matches the reference), four-up
           from sm so they never stretch into letterboxed cards on a desktop. --}}
      <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4" aria-label="Order summary">
        @foreach([
          ['label' => 'Placed', 'status' => 'placed'],
          ['label' => 'Packed', 'status' => 'packed'],
          ['label' => 'Shipped', 'status' => 'shipped'],
          ['label' => 'Delivered', 'status' => 'delivered'],
        ] as $tile)
          @php($count = (int) ($statusCounts[$tile['status']] ?? 0))
          <div class="flex flex-col items-center justify-center rounded-lg border border-line px-3 py-4 text-center">
            <span class="text-[22px] font-medium leading-none text-price">{{ $count }}</span>
            <span class="mt-1.5 text-[12px] font-medium uppercase tracking-[0.3px] text-heading">{{ $tile['label'] }}</span>
            <span class="mt-0.5 text-[11px] text-muted">{{ $count === 1 ? '1 order' : $count.' orders' }}</span>
          </div>
        @endforeach
      </div>

      <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-[14px] font-medium uppercase tracking-[0.4px]">Order History</h2>
      </div>

      @if($orders->isEmpty())
        <p class="rounded-lg border border-line bg-pinksoft px-4 py-4 text-[13px] text-heading">
          You haven't placed any orders yet. <a class="underline hover:text-accent" href="{{ route('home') }}">Start shopping</a>.
        </p>
      @else
        <div class="space-y-4">
          @foreach($orders as $order)
            <a class="block rounded-lg border border-line p-4 transition-colors hover:border-heading" href="{{ route('account.orders.show', $order) }}">
              <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <span class="text-[13px] font-medium text-heading">Order #{{ $order->order_number }}</span>
                <span class="inline-block rounded-full bg-pinksoft px-3 py-1 text-[11px] font-medium uppercase tracking-[0.3px] text-accent">{{ ucfirst($order->status) }}</span>
              </div>
              <p class="mb-1 text-[12px] text-muted">{{ $order->created_at->format('d M Y') }} &middot; {{ $order->items->count() }} item{{ $order->items->count() === 1 ? '' : 's' }}</p>
              <p class="text-[14px] font-medium text-price">₹{{ number_format($order->total, 0) }}</p>
            </a>
          @endforeach
        </div>
        <div class="mt-6">
          {{ $orders->links() }}
        </div>
      @endif
    </section>
  </div>

  @push('scripts')
    <script>
      (function () {
        var panel = document.getElementById('profile-details');
        if (!panel) return;

        // "Edit Profile" (and the Account Settings menu row) are plain anchors
        // to #profile-details. The jump alone won't open a closed <details>,
        // so open it here and put the cursor in the first field.
        function open(event) {
          if (panel.open) return;
          panel.open = true;
          if (event) {
            // Let the browser finish its own jump to the anchor first,
            // otherwise focusing mid-scroll fights it.
            requestAnimationFrame(function () {
              var name = document.getElementById('name');
              if (name) name.focus({ preventScroll: true });
            });
          }
        }

        document.querySelectorAll('a[href="#profile-details"]').forEach(function (link) {
          link.addEventListener('click', open);
        });

        // Also covers a direct load of /account#profile-details.
        if (window.location.hash === '#profile-details') open();

        var cancel = panel.querySelector('[data-profile-cancel]');
        if (cancel) {
          cancel.addEventListener('click', function () {
            var form = panel.querySelector('form');
            if (form) form.reset();
            panel.open = false;
            // Drop the hash so a refresh doesn't reopen what was just closed.
            if (window.location.hash === '#profile-details') {
              history.replaceState(null, '', window.location.pathname + window.location.search);
            }
          });
        }
      })();
    </script>
  @endpush

@endsection
