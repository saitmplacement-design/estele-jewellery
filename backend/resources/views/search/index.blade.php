@extends('layouts.app')

@section('meta_title', 'Search'.($query !== '' ? " — {$query}" : '').' | '.($siteSettings['site_name'] ?? 'Estele'))

@if($query !== '')
  @section('sticky_bar')
    <x-listing-bar :sort="$sort" :options="['relevance' => 'Relevance', 'price_asc' => 'Price: Low to High', 'price_desc' => 'Price: High to Low', 'newest' => 'Newest']" :filtered="filled($minPrice) || filled($maxPrice) || $inStock || count($categorySlugs) > 0" />
  @endsection
@endif

@section('content')

    <div class="flex h-[49px] items-center gap-1 border-b border-line px-2 md:hidden">
    <a class="grid h-10 w-9 shrink-0 place-items-center text-heading" href="{{ url()->previous() === url()->current() ? route('home') : url()->previous() }}" aria-label="Back">
      <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
    </a>
    <span class="truncate text-[16px] font-bold text-heading">{{ $query !== '' ? $query : 'Search' }}</span>
  </div>

  <nav class="mx-auto hidden w-full max-w-wrapper flex-wrap items-center gap-1.5 px-4 py-4 text-[13px] text-muted md:flex" aria-label="Breadcrumb">
    <x-breadcrumb :items="[['label' => 'Search']]" />
  </nav>

  <div class="mx-auto w-full max-w-wrapper px-2.5 pb-6 pt-3 md:px-4 md:pb-10 md:pt-0">
    <h1 class="mb-5 hidden text-[26px] md:block">Search</h1>

    @if($query !== '')
      <div class="mb-5 flex flex-wrap items-center gap-3.5">
        <p class="text-[13px] text-muted md:mr-auto">{{ $products->total() }} {{ \Illuminate\Support\Str::plural('result', $products->total()) }} for &ldquo;{{ $query }}&rdquo;</p>
        <label class="ml-auto hidden md:block">
          <span class="sr-only-custom">Sort by</span>
          <select class="border border-line-strong bg-white px-3 py-2 text-[13px] outline-none transition-colors focus:border-heading" onchange="window.location.href=this.value">
            <option value="{{ request()->fullUrlWithQuery(['sort' => 'relevance']) }}" @selected($sort === 'relevance')>Relevance</option>
            <option value="{{ request()->fullUrlWithQuery(['sort' => 'price_asc']) }}" @selected($sort === 'price_asc')>Price: Low to High</option>
            <option value="{{ request()->fullUrlWithQuery(['sort' => 'price_desc']) }}" @selected($sort === 'price_desc')>Price: High to Low</option>
            <option value="{{ request()->fullUrlWithQuery(['sort' => 'newest']) }}" @selected($sort === 'newest')>Newest</option>
          </select>
        </label>
      </div>

      <x-filter-panel
        :action="route('search')"
        :q="$query"
        :sort="$sort"
        :min-price="$minPrice"
        :max-price="$maxPrice"
        :in-stock="$inStock"
        :categories="$categories"
        :selected-categories="$categorySlugs"
      />
    @endif

    @if($products->isEmpty())
      <p class="py-16 text-center text-[13px] text-muted">
        @if($query === '')
          Use the search bar at the top of the page to find products.
        @else
          No products matched &ldquo;{{ $query }}&rdquo;.
        @endif
      </p>
    @else
      <div class="grid grid-cols-2 gap-x-2.5 gap-y-5 sm:grid-cols-3 md:gap-5 lg:grid-cols-4">
        @foreach($products as $product)
          <x-product-card :product="$product" />
        @endforeach
      </div>

      @if($products->hasPages())
        <div class="mt-8">
          {{ $products->links() }}
        </div>
      @endif
    @endif
  </div>

@endsection
