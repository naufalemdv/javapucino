<div class="topbar">
  <button class="burger" type="button" data-act="nav">☰</button>
  <div style="min-width:0">
    <h1>{{ $title }}</h1>
    @isset($sub)<div class="sub">{{ $sub }}</div>@endisset
  </div>
  <div class="grow"></div>
  {!! $right ?? '' !!}
</div>
