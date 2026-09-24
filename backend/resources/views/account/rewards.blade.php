@extends('layouts.app')

@section('meta_title', 'Rewards & Wallet | '.($siteSettings['site_name'] ?? 'Estele'))
@section('meta_description', 'Submit unboxing proof for rewards and manage your wallet balance.')

@section('content')

  <nav class="mx-auto w-full max-w-wrapper px-3 md:px-4 flex flex-wrap items-center gap-1.5 py-2 text-[13px] text-muted border-b border-line" aria-label="Breadcrumb">
    <x-breadcrumb :items="[['label' => 'My Account', 'url' => route('account.index')], ['label' => 'Rewards & Wallet']]" />
  </nav>

  <div class="mx-auto w-full max-w-wrapper px-3 pb-10 pt-4 md:pt-6 md:px-4 md:pb-[60px]">
    <h1 class="mb-6 text-[20px] uppercase tracking-[0.5px] md:text-[26px]">Rewards & Wallet</h1>

    @if(session('success'))
      <p class="mb-6 rounded-lg border border-line bg-pinksoft px-4 py-3 text-[13px] text-heading">{{ session('success') }}</p>
    @endif

    {{-- Wallet balance stat card — full-width on mobile, so it's the first
         thing a customer sees on a phone before scrolling to submissions. --}}
    <section class="mb-8 rounded-lg border border-line p-4 sm:p-6">
      <h2 class="mb-2 text-[14px] font-medium uppercase tracking-[0.4px]">Wallet Balance</h2>
      <p class="text-[28px] font-medium text-price sm:text-[32px]">₹{{ number_format((float) $walletBalance, 2) }}</p>
    </section>

    <section class="mb-8">
      <h2 class="mb-4 text-[14px] font-medium uppercase tracking-[0.4px]">Submit Unboxing Proof</h2>

      @if($eligibleOrders->isEmpty())
        <p class="rounded-lg border border-line bg-pinksoft px-4 py-4 text-[13px] text-heading">
          No delivered orders are currently eligible for a reward submission.
        </p>
      @else
        @foreach($eligibleOrders as $order)
          <form class="mb-4 rounded-lg border border-line p-4 reward-submission-form" action="{{ route('account.rewards.store') }}" method="post" enctype="multipart/form-data" data-max-image-bytes="3145728" data-max-video-bytes="10485760">
            @csrf
            <input type="hidden" name="order_id" value="{{ $order->id }}">
            <p class="mb-3 text-[13px] font-medium text-heading">Order #{{ $order->order_number }}</p>

            <label class="mb-1.5 block text-[13px] font-medium text-heading" for="image-{{ $order->id }}">Image (max 3MB)</label>
            <input class="mb-1 w-full border border-line-strong bg-white px-4 py-2.5 text-[13px]" id="image-{{ $order->id }}" name="image" type="file" accept="image/*" required>
            <p class="reward-file-error mb-3 hidden text-[12px] text-salebadge" data-for="image-{{ $order->id }}"></p>
            @error('image') <p class="mb-3 text-[12px] text-salebadge">{{ $message }}</p> @enderror
            <img class="reward-image-preview mb-3 hidden h-32 w-32 max-w-full rounded-lg border border-line object-cover sm:h-40 sm:w-40" alt="Selected image preview">

            <label class="mb-1.5 block text-[13px] font-medium text-heading" for="video-{{ $order->id }}">Video (max 10MB)</label>
            <input class="mb-1 w-full border border-line-strong bg-white px-4 py-2.5 text-[13px]" id="video-{{ $order->id }}" name="video" type="file" accept="video/mp4,video/webm" required>
            <p class="reward-file-error mb-3 hidden text-[12px] text-salebadge" data-for="video-{{ $order->id }}"></p>
            @error('video') <p class="mb-3 text-[12px] text-salebadge">{{ $message }}</p> @enderror
            <video class="reward-video-preview mb-3 hidden w-full max-w-full rounded-lg border border-line sm:max-w-sm" controls playsinline></video>

            <button class="inline-flex w-full items-center justify-center gap-2 border border-accent bg-accent px-6 py-2.5 text-[12px] font-medium uppercase tracking-[0.5px] text-white transition-colors hover:border-accent-dark hover:bg-accent-dark sm:w-auto" type="submit">
              Submit for Reward
            </button>
          </form>
        @endforeach
      @endif
    </section>

    <section class="mb-8">
      <h2 class="mb-4 text-[14px] font-medium uppercase tracking-[0.4px]">Your Submissions</h2>

      @if($submissions->isEmpty())
        <p class="text-[13px] text-muted">No submissions yet.</p>
      @else
        <div class="space-y-3">
          @foreach($submissions as $submission)
            <div class="rounded-lg border border-line p-4">
              <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <span class="text-[13px] font-medium text-heading">Order #{{ $submission->order->order_number }}</span>
                <span @class([
                  'inline-block rounded-full px-3 py-1 text-[11px] font-medium uppercase tracking-[0.3px]',
                  'bg-pinksoft text-accent' => $submission->status === 'pending',
                  'bg-green-100 text-green-700' => $submission->status === 'approved',
                  'bg-red-100 text-salebadge' => $submission->status === 'rejected',
                ])>{{ ucfirst($submission->status) }}</span>
              </div>
              @if($submission->status === 'approved')
                <p class="text-[13px] text-price">Reward credited: ₹{{ number_format((float) $submission->reward_amount, 2) }}</p>
              @elseif($submission->status === 'rejected')
                <p class="text-[13px] text-muted">Reason: {{ $submission->rejection_reason }}</p>
              @endif
            </div>
          @endforeach
        </div>
      @endif
    </section>

    <section>
      <h2 class="mb-4 text-[14px] font-medium uppercase tracking-[0.4px]">Wallet Transaction History</h2>

      @if($walletTransactions->isEmpty())
        <p class="text-[13px] text-muted">No wallet activity yet.</p>
      @else
        {{-- The table is min-w-[480px], which pushes Amount and Balance — the two
             columns that matter — off-screen on a phone. Stacked rows below sm,
             the table from sm up where it fits. --}}
        <ul class="space-y-2.5 sm:hidden">
          @foreach($walletTransactions as $transaction)
            <li class="rounded-lg border border-line px-3 py-2.5">
              <div class="flex items-baseline justify-between gap-3">
                <span class="text-[13px] font-bold text-heading">{{ str_replace('_', ' ', ucfirst($transaction->reason)) }}</span>
                <span class="shrink-0 text-[14px] font-bold {{ $transaction->type === 'credit' ? 'text-price' : 'text-salebadge' }}">
                  {{ $transaction->type === 'credit' ? '+' : '-' }}₹{{ number_format((float) $transaction->amount, 2) }}
                </span>
              </div>
              <div class="mt-1 flex items-baseline justify-between gap-3 text-[12px] text-muted">
                <span>{{ $transaction->created_at->format('d M Y') }}</span>
                <span>Balance ₹{{ number_format((float) $transaction->balance_after, 2) }}</span>
              </div>
            </li>
          @endforeach
        </ul>

        <div class="hidden overflow-x-auto sm:block">
          <table class="w-full min-w-[480px] text-left text-[13px]">
            <thead>
              <tr class="border-b border-line text-[11px] uppercase tracking-[0.3px] text-muted">
                <th class="py-2">Date</th>
                <th class="py-2">Type</th>
                <th class="py-2">Reason</th>
                <th class="py-2 text-right">Amount</th>
                <th class="py-2 text-right">Balance</th>
              </tr>
            </thead>
            <tbody>
              @foreach($walletTransactions as $transaction)
                <tr class="border-b border-line">
                  <td class="py-2">{{ $transaction->created_at->format('d M Y') }}</td>
                  <td class="py-2">{{ ucfirst($transaction->type) }}</td>
                  <td class="py-2">{{ str_replace('_', ' ', ucfirst($transaction->reason)) }}</td>
                  <td class="py-2 text-right {{ $transaction->type === 'credit' ? 'text-price' : 'text-salebadge' }}">
                    {{ $transaction->type === 'credit' ? '+' : '-' }}₹{{ number_format((float) $transaction->amount, 2) }}
                  </td>
                  <td class="py-2 text-right">₹{{ number_format((float) $transaction->balance_after, 2) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="mt-4">{{ $walletTransactions->links() }}</div>
      @endif
    </section>
  </div>

  <script>
    // UX-only guard: blocks an obviously-oversized file before the network
    // round-trip. The server (RewardSubmissionController::store) re-validates
    // every upload unconditionally — this check is never the security boundary.
    document.querySelectorAll('.reward-submission-form').forEach(function (form) {
      var maxImage = parseInt(form.dataset.maxImageBytes, 10);
      var maxVideo = parseInt(form.dataset.maxVideoBytes, 10);

      var imageInput = form.querySelector('input[name="image"]');
      var videoInput = form.querySelector('input[name="video"]');
      var imagePreview = form.querySelector('.reward-image-preview');
      var videoPreview = form.querySelector('.reward-video-preview');

      imageInput.addEventListener('change', function () {
        if (imageInput.files[0]) {
          imagePreview.src = URL.createObjectURL(imageInput.files[0]);
          imagePreview.classList.remove('hidden');
        } else {
          imagePreview.classList.add('hidden');
        }
      });

      videoInput.addEventListener('change', function () {
        if (videoInput.files[0]) {
          videoPreview.src = URL.createObjectURL(videoInput.files[0]);
          videoPreview.classList.remove('hidden');
        } else {
          videoPreview.classList.add('hidden');
        }
      });

      form.addEventListener('submit', function (event) {
        var valid = true;
        form.querySelectorAll('.reward-file-error').forEach(function (el) { el.classList.add('hidden'); el.textContent = ''; });

        var image = form.querySelector('input[name="image"]');
        var video = form.querySelector('input[name="video"]');

        if (image.files[0] && image.files[0].size > maxImage) {
          valid = false;
          showError(image, 'Image must be 3MB or smaller.');
        }
        if (video.files[0] && video.files[0].size > maxVideo) {
          valid = false;
          showError(video, 'Video must be 10MB or smaller.');
        }

        if (!valid) {
          event.preventDefault();
        }
      });

      function showError(input, message) {
        var error = form.querySelector('.reward-file-error[data-for="' + input.id + '"]');
        if (error) {
          error.textContent = message;
          error.classList.remove('hidden');
        }
      }
    });
  </script>

@endsection
