@extends('layouts.app')

@section('meta_title', ($category->seoMeta?->title ?? $category->name).' | '.($siteSettings['site_name'] ?? 'Estele'))
@section('meta_description', $category->seoMeta?->description ?: ($category->description ?: $category->name))
@if($category->seoMeta?->og_image || $category->hasMedia('image'))
  @section('og_image', $category->seoMeta?->og_image ?? $category->getFirstMediaUrl('image', 'tile'))
@endif

@section('sticky_bar')
  <x-listing-bar :sort="$sort" :filtered="filled($minPrice) || filled($maxPrice) || $inStock || count($subcategorySlugs) > 0" />
@endsection

@section('content')

  {{-- No wide banner section here: Category images are portrait product/tile photography
       (used for the Shop by Category carousel), not wide banner art like Collections have.
       Force-cropping a portrait image into a 16:5 banner just showed a blank middle slice. --}}

  <x-breadcrumb-schema :items="[['label' => $category->name]]" />

  {{-- Page title/subtitle removed by design ask — breadcrumb is the only page
       identifier now. The product count that used to live in the subtitle is
       still shown, just folded into the "Showing X–Y of N" line below. --}}
    <div class="flex h-[49px] items-center gap-1 border-b border-line px-2 md:hidden">
    <a class="grid h-10 w-9 shrink-0 place-items-center text-heading" href="{{ url()->previous() === url()->current() ? route('home') : url()->previous() }}" aria-label="Back">
      <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
    </a>
    <span class="truncate text-[16px] font-bold text-heading">{{ $category->name }}</span>
  </div>

  <nav class="mx-auto hidden w-full max-w-wrapper flex-wrap items-center gap-1.5 px-4 py-4 text-[13px] text-muted md:flex" aria-label="Breadcrumb">
    <x-breadcrumb :items="[['label' => $category->name]]" />
  </nav>

  <div class="mx-auto w-full max-w-wrapper px-2.5 pb-6 pt-3 md:px-4 md:pb-10 md:pt-0">

    <x-filter-panel
      :action="route('categories.show', $category)"
      :sort="$sort"
      :min-price="$minPrice"
      :max-price="$maxPrice"
      :in-stock="$inStock"
      :categories="$category->children"
      :selected-categories="$subcategorySlugs"
    />

    <div class="mb-3 flex flex-wrap items-center gap-3.5 md:mb-5 md:border-b md:border-line md:pb-4.5">
      <p class="w-full text-[13px] text-muted md:mr-auto md:w-auto">
        @if($products->total() > 0)
          Showing {{ $products->firstItem() }}&ndash;{{ $products->lastItem() }} of {{ $products->total() }}
        @else
          No products
        @endif
      </p>
      <label class="ml-auto hidden md:block">
        <span class="sr-only-custom">Sort by</span>
        <select class="border border-line-strong bg-white px-3 py-2 text-[13px] outline-none transition-colors focus:border-heading" onchange="window.location.href=this.value">
          <option value="{{ request()->fullUrlWithQuery(['sort' => 'featured']) }}" @selected($sort === 'featured')>Featured</option>
          <option value="{{ request()->fullUrlWithQuery(['sort' => 'price_asc']) }}" @selected($sort === 'price_asc')>Price: Low to High</option>
          <option value="{{ request()->fullUrlWithQuery(['sort' => 'price_desc']) }}" @selected($sort === 'price_desc')>Price: High to Low</option>
          <option value="{{ request()->fullUrlWithQuery(['sort' => 'newest']) }}" @selected($sort === 'newest')>Newest</option>
        </select>
      </label>
    </div>

    @if($products->isEmpty())
      <p class="py-16 text-center text-[13px] text-muted">No products in this category yet — check back soon.</p>
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
