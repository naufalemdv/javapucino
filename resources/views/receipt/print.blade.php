<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Struk {{ $trx->invoice_no }}</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
<style>
  body{background:#F0F0F2;overflow:auto;padding:18px}
  @media print{ body{background:#fff;padding:0} .no-print{display:none} }
  @page{ margin:4mm }
</style>
</head>
<body>
  @include('receipt._body')
  <div class="no-print" style="max-width:302px;margin:16px auto 0;display:flex;gap:8px">
    <button class="btn btn-line" style="flex:1" onclick="window.close()">Tutup</button>
    <button class="btn btn-black" style="flex:1.3" onclick="window.print()">🖨️ Cetak</button>
  </div>
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 350));</script>
</body>
</html>
