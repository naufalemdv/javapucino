@extends('layouts.auth')
@section('title', 'Buka shift')
@section('tagline', 'Buka shift dulu sebelum jualan.')
@section('lead-art', 'Setiap transaksi wajib terhubung ke shift aktif. Modal awal laci dipakai untuk rekonsiliasi kas saat tutup shift nanti.')

@section('form')
  <h1>Buka shift</h1>
  <p class="lead">Halo {{ explode(' ', auth()->user()->name)[0] }}, {{ now()->format('d/m/Y · H:i') }}</p>

  @if($errors->any())<div class="alert alert-err">{{ $errors->first() }}</div>@endif

  <form method="POST" action="{{ route('kasir.shift.store') }}" id="shiftForm">
    @csrf
    <input type="hidden" name="shift_type" id="shiftType" value="{{ old('shift_type', now()->hour < 15 ? 'pagi' : 'sore') }}">

    <div class="field">
      <label>Jenis shift</label>
      <div class="shift-pick">
        @foreach(config('javapucino.shift_types') as $key => $st)
          <button type="button" class="shift-opt" data-shift="{{ $key }}">
            <b>{{ $st['label'] }}</b><small>{{ $st['range'] }}</small>
          </button>
        @endforeach
      </div>
    </div>

    <div class="field">
      <label>Modal awal laci</label>
      <input class="inp num" id="openCash" inputmode="numeric" value="{{ old('opening_cash', '') }}" data-money="openCashRaw" placeholder="Masukkan nominal modal awal">
      <input type="hidden" name="opening_cash" id="openCashRaw" value="{{ old('opening_cash', '') }}">
      <div class="hint">Uang tunai di laci sebelum transaksi pertama.</div>
    </div>

    <button class="btn btn-red btn-lg btn-block" type="submit" id="btnStartShift" disabled style="opacity:0.5;cursor:not-allowed">Mulai shift &amp; buka kasir</button>
  </form>

  <form method="POST" action="{{ route('logout') }}">
    @csrf
    <button class="btn btn-ghost btn-block" style="margin-top:8px" type="submit">Keluar</button>
  </form>
@endsection

@push('scripts')
<script>
const type = document.getElementById('shiftType');
const mark = () => document.querySelectorAll('[data-shift]').forEach(b =>
  b.classList.toggle('on', b.dataset.shift === type.value));
document.querySelectorAll('[data-shift]').forEach(b => b.addEventListener('click', () => {
  type.value = b.dataset.shift; mark();
}));
mark();

// Disable button until modal awal laci is filled
const btnStart = document.getElementById('btnStartShift');
const cashInput = document.getElementById('openCash');
const cashRaw   = document.getElementById('openCashRaw');

function toggleBtn() {
  const val = parseInt(cashRaw.value) || 0;
  const hasValue = val > 0;
  btnStart.disabled = !hasValue;
  btnStart.style.opacity = hasValue ? '1' : '0.5';
  btnStart.style.cursor  = hasValue ? 'pointer' : 'not-allowed';
}

cashInput.addEventListener('input', () => setTimeout(toggleBtn, 50));
cashInput.addEventListener('change', () => setTimeout(toggleBtn, 50));
new MutationObserver(toggleBtn).observe(cashRaw, { attributes: true, attributeFilter: ['value'] });
setInterval(toggleBtn, 300);
toggleBtn();

</script>
@endpush