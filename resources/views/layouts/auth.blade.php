<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>@yield('title', 'Masuk') — {{ $appSettings['store_name'] ?? 'Javapucino' }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,800;1,900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div id="app">
  <div class="shell">
    <div class="view">
      <div class="auth">
        <div class="auth-art">
          <div><span class="wm">{{ $appSettings['store_name'] ?? 'Javapucino' }}</span></div>
          <div class="spacer" style="flex:1"></div>
          <div style="position:relative;z-index:2">
            <h2>@yield('tagline', 'Semua bisa ngopi, semua bisa kasir.')</h2>
            <p>@yield('lead-art', 'Sistem kasir & CMS untuk booth Javapucino. Catat pesanan, terima Cash atau QRIS, cetak struk, dan pantau stok dari satu layar.')</p>
          </div>
          <div class="booth"></div>
        </div>
        <div class="auth-form"><div class="auth-inner">
          @yield('form')
        </div></div>
      </div>
    </div>
  </div>
</div>
@include('partials.flash')
<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
