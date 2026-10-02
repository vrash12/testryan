@props(['title' => 'NestCrafters'])
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/png" sizes="64x64" href="{{ asset('images/favicon.png') }}">
<link rel="apple-touch-icon" sizes="64x64" href="{{ asset('images/favicon.png') }}">
<title>{{ $title }} · NestCrafters</title>
@if (is_file(public_path('build/manifest.json')) || is_file(public_path('hot')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@endif
