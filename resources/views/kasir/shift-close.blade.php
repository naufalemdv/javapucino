@extends('layouts.app')
@section('title', 'Tutup Shift')

@section('content')
  @include('partials.topbar', ['title' => 'Tutup Shift', 'sub' => 'Rekonsiliasi kas sebelum mengakhiri shift'])

  @php $expected = (float) $shift->opening_cash + $cash; @endphp

  <div class="page"><div style="max-width:560px">
    <div class="card">
      <div class="card-h">
        <h3>Ringkasan shift {{ $shift->shift_type }}</h3>
        <div class="grow"></div>
        <span class="tag t-green">Aktif sejak {{ $shift->opened_at->format('H:i') }}</span>
      </div>
      <div class="card-b">
        <div class="sline"><span>Modal awal laci</span><span class="num">{{ rupiah($shift->opening_cash) }}</span></div>
        <div class="sline"><span>Penjualan tunai ({{ $cashN }} trx)</span><span class="num">{{ rupiah($cash) }}</span></div>
        <div class="sline"><span>Penjualan QRIS ({{ $qrisN }} trx)</span><span class="num">{{ rupiah($qris) }}</span></div>
        <div style="border-top:1px solid var(--line);margin:12px 0"></div>
        <div class="sline" style="font-size:15px">
          <span style="font-weight:800;color:var(--ink)">Uang seharusnya di laci</span>
          <span class="num" style="font-size:19px">{{ rupiah($expected) }}</span>
        </div>

        @if($errors->any())<div class="alert alert-err" style="margin-top:14px">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('kasir.shift.update') }}"
              data-confirm="Tutup shift dan keluar dari sistem?">
          @csrf @method('PUT')

          <div class="field" style="margin-top:18px">
            <label>Uang fisik hasil hitungan</label>
            <input class="inp num" id="actualCash" inputmode="numeric" data-money="actualRaw"
                   value="{{ (int) $expected }}" style="height:52px;font-size:19px;font-weight:800">
            <input type="hidden" name="actual_cash" id="actualRaw" value="{{ (int) $expected }}">
            <div class="hint">Hitung seluruh uang tunai di laci, termasuk modal awal.</div>
          </div>

          <div id="diffBox" class="change-box"><span>Selisih</span><b>Rp 0</b></div>

          <div class="field" style="margin-top:14px">
            <label>Catatan (opsional)</label>
            <textarea class="inp" name="note" placeholder="Misal: selisih karena kembalian kurang di trx 0012">{{ old('note') }}</textarea>
          </div>

          <button class="btn btn-red btn-lg btn-block" type="submit">Tutup shift &amp; keluar</button>
        </form>

        <a class="btn btn-ghost btn-block" style="margin-top:8px" href="{{ route('kasir.pos') }}">Batal, kembali ke kasir</a>
      </div>
    </div>
  </div></div>
@endsection

@push('scripts')
<script>
const expected = {{ (int) $expected }};
const inp = document.getElementById('actualCash');
const box = document.getElementById('diffBox');

function refresh() {
  const val  = Number(String(inp.value).replace(/\D/g, '') || 0);
  const diff = val - expected;
  box.classList.toggle('short', diff < 0);
  box.querySelector('span').textContent = diff < 0 ? 'Kurang' : 'Selisih';
  box.querySelector('b').textContent    = (diff < 0 ? '-' : '') + rp(Math.abs(diff));
}
inp.addEventListener('input', refresh);
refresh();
</script>
@endpush
