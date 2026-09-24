@extends('layouts.app')

@section('meta_title', 'Collections | '.($siteSettings['site_name'] ?? 'Estele'))

@section('content')

  <div class="mx-auto w-full max-w-wrapper px-3 py-6 md:px-4">
    <x-breadcrumb :items="[['label' => 'Collections']]" />

    <div class="mb-6 text-center">
      <h1 class="text-[20px] uppercase tracking-[0.5px] md:text-[26px] mb-2.5">All Collections</h1>
    </div>

    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4 sm:gap-4 md:grid-cols-5 md:gap-5 lg:grid-cols-6 lg:gap-6 xl:grid-cols-7 xl:gap-7 2xl:grid-cols-8">
      @foreach($collections as $collection)
        <x-collection-tile :category="$collection" route="collections.show" object-position="left" />
      @endforeach
    </div>
  </div>

@endsection
