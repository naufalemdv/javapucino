@php
  $flash = session('success') ?? session('info') ?? session('error');
  $isErr = (bool) session('error');
@endphp
@if($flash)
  <div class="flash {{ $isErr ? 'err' : '' }}" id="flash">{{ $flash }}</div>
  <script>setTimeout(()=>document.getElementById('flash')?.remove(),3200);</script>
@endif
