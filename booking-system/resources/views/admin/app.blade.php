<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Simavis - @yield('title')</title>
    @vite('resources/css/app.css')
</head>
<body class="antialiased">
@include('admin.nav')
<div class="container-fluid">
@yield('content')
</div>
@vite('resources/js/app.js')
</body>
</html>
