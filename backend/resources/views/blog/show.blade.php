@extends('layouts.app')

@section('meta_title', ($blog->seoMeta?->title ?? $blog->title).' | '.($siteSettings['site_name'] ?? 'Estele'))
@section('meta_description', $blog->seoMeta?->description ?: ($blog->excerpt ?: $blog->title))
@section('og_type', 'article')
@if($blog->seoMeta?->og_image || $blog->hasMedia('featured_image'))
  @section('og_image', $blog->seoMeta?->og_image ?? $blog->getFirstMediaUrl('featured_image', 'detail'))
@endif

@section('content')

  @php
    $image = $blog->getFirstMediaUrl('featured_image', 'detail');
  @endphp

  <script type="application/ld+json">
    {!! json_encode([
      '@context' => 'https://schema.org',
      '@type' => 'Article',
      'headline' => $blog->title,
      'image' => $image ?: null,
      'datePublished' => $blog->published_at?->toIso8601String(),
      'dateModified' => $blog->updated_at?->toIso8601String(),
      'author' => [
        '@type' => 'Person',
        'name' => $blog->author_name ?: ($siteSettings['site_name'] ?? 'Estele'),
      ],
    ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
  </script>

  @php
    $blogBreadcrumbItems = array_filter([
      ['label' => 'Blog', 'url' => route('blogs.index')],
      $blog->blogCategory ? ['label' => $blog->blogCategory->name, 'url' => route('blogs.index', ['category' => $blog->blogCategory->slug])] : null,
      ['label' => $blog->title],
    ]);
  @endphp
  <x-breadcrumb-schema :items="$blogBreadcrumbItems" />

  <div class="mx-auto w-full max-w-[760px] px-3 py-6 md:px-4">
    <x-breadcrumb :items="$blogBreadcrumbItems" />

    <p class="mb-2 text-[11px] uppercase tracking-[0.3px] text-muted">
      @if($blog->blogCategory){{ $blog->blogCategory->name }} · @endif
      @if($blog->published_at){{ $blog->published_at->format('F j, Y') }}@endif
      @if($blog->author_name) · By {{ $blog->author_name }}@endif
    </p>
    <h1 class="mb-5 text-[24px] md:text-[32px]">{{ $blog->title }}</h1>

    @if($image)
      <img class="mb-6 w-full rounded-lg object-cover" style="aspect-ratio: 16/9;" src="{{ $image }}" alt="{{ $blog->featured_image_alt_text ?: $blog->title }}" width="1600" height="900">
    @endif

    <div class="prose max-w-none text-[14px] leading-relaxed text-ink">
      {!! $blog->content !!}
    </div>
  </div>

  @if($relatedPosts->isNotEmpty())
    <div class="mx-auto w-full max-w-wrapper px-3 pb-6 md:px-4">
      <h2 class="mb-5 text-center text-[16px] uppercase tracking-[0.4px]">You may also like</h2>
      <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
        @foreach($relatedPosts as $post)
          <x-blog-card :blog="$post" />
        @endforeach
      </div>
    </div>
  @endif

@endsection
