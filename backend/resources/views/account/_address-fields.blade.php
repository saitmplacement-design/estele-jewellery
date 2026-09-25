@php $address = $address ?? null; @endphp

<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
  <div>
    <label class="mb-1.5 block text-[13px] font-medium text-heading">Label</label>
    <input class="w-full border border-line-strong bg-white px-4 py-2.5 text-[14px] outline-none transition-colors focus:border-heading" name="label" type="text" value="{{ old('label', $address?->label ?? 'Home') }}" required maxlength="50">
  </div>
  <div>
    <label class="mb-1.5 block text-[13px] font-medium text-heading">Phone (optional)</label>
    <input class="w-full border border-line-strong bg-white px-4 py-2.5 text-[14px] outline-none transition-colors focus:border-heading" name="phone" type="tel" value="{{ old('phone', $address?->phone) }}" inputmode="numeric" pattern="[0-9]{10}" title="Enter a 10-digit mobile number" autocomplete="tel-national" placeholder="10-digit mobile number" data-digits-only data-max-digits="10">
  </div>
  <div class="sm:col-span-2">
    <label class="mb-1.5 block text-[13px] font-medium text-heading">Address Line 1</label>
    <input class="w-full border border-line-strong bg-white px-4 py-2.5 text-[14px] outline-none transition-colors focus:border-heading" name="line1" type="text" value="{{ old('line1', $address?->line1) }}" required maxlength="255">
  </div>
  <div class="sm:col-span-2">
    <label class="mb-1.5 block text-[13px] font-medium text-heading">Address Line 2 (optional)</label>
    <input class="w-full border border-line-strong bg-white px-4 py-2.5 text-[14px] outline-none transition-colors focus:border-heading" name="line2" type="text" value="{{ old('line2', $address?->line2) }}" maxlength="255">
  </div>
  <div>
    <label class="mb-1.5 block text-[13px] font-medium text-heading">City</label>
    <input class="w-full border border-line-strong bg-white px-4 py-2.5 text-[14px] outline-none transition-colors focus:border-heading" name="city" type="text" value="{{ old('city', $address?->city) }}" required maxlength="100" data-pincode-city>
  </div>
  <div>
    <label class="mb-1.5 block text-[13px] font-medium text-heading">State</label>
    <input class="w-full border border-line-strong bg-white px-4 py-2.5 text-[14px] outline-none transition-colors focus:border-heading" name="state" type="text" value="{{ old('state', $address?->state) }}" required maxlength="100" data-pincode-state>
  </div>
  <div>
    <label class="mb-1.5 block text-[13px] font-medium text-heading">Postal Code</label>
    <input class="w-full border border-line-strong bg-white px-4 py-2.5 text-[14px] outline-none transition-colors focus:border-heading" name="postal_code" type="text" value="{{ old('postal_code', $address?->postal_code) }}" required pattern="[0-9]{6}" maxlength="6" data-pincode-lookup>
    <p class="mt-1 text-[11.5px] text-muted" data-pincode-lookup-status></p>
  </div>
  <div>
    <label class="mb-1.5 block text-[13px] font-medium text-heading">Country</label>
    <input class="w-full border border-line-strong bg-white px-4 py-2.5 text-[14px] outline-none transition-colors focus:border-heading" name="country" type="text" value="{{ old('country', $address?->country ?? 'India') }}" required maxlength="100">
  </div>
</div>

<label class="mt-3 flex items-center gap-2 text-[13px] text-heading">
  <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $address?->is_default))>
  Set as default address
</label>

@error('label') <p class="mt-2 text-[12px] text-salebadge">{{ $message }}</p> @enderror
@error('line1') <p class="mt-2 text-[12px] text-salebadge">{{ $message }}</p> @enderror
@error('postal_code') <p class="mt-2 text-[12px] text-salebadge">{{ $message }}</p> @enderror
