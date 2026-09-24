@extends('layouts.app')

@section('meta_title', 'Blog | '.($siteSettings['site_name'] ?? 'Estele'))

@section('content')

  <div class="mx-auto w-full max-w-wrapper px-3 py-6 md:px-4">
    <x-breadcrumb :items="[['label' => 'Blog']]" />

    <div class="mb-6 text-center">
      <h1 class="text-[20px] uppercase tracking-[0.5px] md:text-[26px] mb-2.5">Blog</h1>
    </div>

    @if($categories->isNotEmpty())
      <div class="mb-6 flex flex-wrap justify-center gap-2">
        <a class="rounded-full border px-4 py-1.5 text-[12px] uppercase tracking-[0.3px] {{ !$categorySlug ? 'border-heading text-heading' : 'border-line-strong text-muted hover:text-heading' }}"
           href="{{ route('blogs.index') }}">All</a>
        @foreach($categories as $category)
          <a class="rounded-full border px-4 py-1.5 text-[12px] uppercase tracking-[0.3px] {{ $categorySlug === $category->slug ? 'border-heading text-heading' : 'border-line-strong text-muted hover:text-heading' }}"
             href="{{ route('blogs.index', ['category' => $category->slug]) }}">{{ $category->name }}</a>
        @endforeach
      </div>
    @endif

    @if($posts->isEmpty())
      <p class="py-10 text-center text-[13px] text-muted">No blog posts yet — check back soon.</p>
    @else
      <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($posts as $post)
          <x-blog-card :blog="$post" />
        @endforeach
      </div>

      <x-pagination-links :paginator="$posts" />
      @if($posts->hasPages())
        <div class="mt-8">
          {{ $posts->links() }}
        </div>
      @endif
    @endif
  </div>

@endsection
