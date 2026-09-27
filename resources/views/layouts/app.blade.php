<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Javapucino POS') — {{ $appSettings['store_name'] ?? 'Javapucino' }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,800;1,900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
@stack('styles')
</head>
<body>
<div id="app">
  <div class="shell">
    @include('partials.rail')
    <div class="view">
      @yield('content')
    </div>
  </div>
</div>

<div class="scrim" id="scrim"><div class="modal" id="modal"></div></div>
@include('partials.flash')

<form id="logout-form" method="POST" action="{{ route('logout') }}" style="display:none">@csrf</form>

<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
