@extends('layouts.app')

@section('meta_title', ($collection->seoMeta?->title ?? $collection->name).' | '.($siteSettings['site_name'] ?? 'Estele'))
@section('meta_description', $collection->seoMeta?->description ?: ($collection->description ?: $collection->name))
@if($collection->seoMeta?->og_image || $collection->hasMedia('image'))
  @section('og_image', $collection->seoMeta?->og_image ?? $collection->getFirstMediaUrl('image', 'banner'))
@endif

@section('sticky_bar')
  <x-listing-bar :sort="$sort" :filtered="filled($minPrice) || filled($maxPrice) || $inStock" />
@endsection

@section('content')

  <x-breadcrumb-schema :items="[['label' => $collection->name]]" />

    <div class="flex h-[49px] items-center gap-1 border-b border-line px-2 md:hidden">
    <a class="grid h-10 w-9 shrink-0 place-items-center text-heading" href="{{ url()->previous() === url()->current() ? route('home') : url()->previous() }}" aria-label="Back">
      <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
    </a>
    <span class="truncate text-[16px] font-bold text-heading">{{ $collection->name }}</span>
  </div>

  <nav class="mx-auto hidden w-full max-w-wrapper flex-wrap items-center gap-1.5 px-4 py-4 text-[13px] text-muted md:flex" aria-label="Breadcrumb">
    <x-breadcrumb :items="[['label' => $collection->name]]" />
  </nav>

  <div class="mx-auto w-full max-w-wrapper px-3 md:px-4">
    <header class="pb-4 pt-3 text-center md:pb-[30px] md:pt-2 {{ $collection->description ? '' : 'hidden md:block' }}">
      <h1 class="mb-2.5 hidden text-[20px] uppercase tracking-[0.5px] md:block md:text-[26px]">{{ $collection->name }}</h1>
      @if($collection->description)
        <p class="mx-auto max-w-[70ch] text-[13.5px] text-muted">{{ $collection->description }}</p>
      @endif
    </header>
  </div>

  <div class="mx-auto w-full max-w-wrapper px-2.5 pb-6 pt-3 md:px-4 md:pb-10 md:pt-0">

    <x-filter-panel
      :action="route('collections.show', $collection)"
      :sort="$sort"
      :min-price="$minPrice"
      :max-price="$maxPrice"
      :in-stock="$inStock"
    />

    <div class="mb-3 flex flex-wrap items-center gap-3 md:mb-5 md:border-b md:border-line md:pb-4">
      <p class="text-[12.5px] text-muted md:text-[13px]">
        @if($products->total() > 0)
          Showing {{ $products->firstItem() }}&ndash;{{ $products->lastItem() }} of {{ $products->total() }}
        @else
          No products
        @endif
      </p>
      <label class="ml-auto hidden md:block">
        <span class="sr-only-custom">Sort by</span>
        <select class="border border-line-strong bg-white px-2.5 py-2 text-[12.5px] outline-none transition-colors focus:border-heading md:px-3 md:text-[13px]" onchange="window.location.href=this.value">
          <option value="{{ request()->fullUrlWithQuery(['sort' => 'featured']) }}" @selected($sort === 'featured')>Featured</option>
          <option value="{{ request()->fullUrlWithQuery(['sort' => 'price_asc']) }}" @selected($sort === 'price_asc')>Price: Low to High</option>
          <option value="{{ request()->fullUrlWithQuery(['sort' => 'price_desc']) }}" @selected($sort === 'price_desc')>Price: High to Low</option>
          <option value="{{ request()->fullUrlWithQuery(['sort' => 'newest']) }}" @selected($sort === 'newest')>Newest</option>
        </select>
      </label>
    </div>

    @if($products->isEmpty())
      <p class="py-10 text-center text-[13px] text-muted">No products match these filters. Try widening the price range.</p>
    @else
      <div class="grid grid-cols-2 gap-x-2.5 gap-y-5 sm:grid-cols-3 sm:gap-4 md:grid-cols-4 md:gap-5 lg:gap-6 xl:grid-cols-5 xl:gap-7 2xl:grid-cols-6">
        @foreach($products as $product)
          <x-product-card :product="$product" />
        @endforeach
      </div>

      <x-pagination-links :paginator="$products" />
      @if($products->hasPages())
        <div class="mt-8">
          {{ $products->links() }}
        </div>
      @endif
    @endif
  </div>

@endsection
