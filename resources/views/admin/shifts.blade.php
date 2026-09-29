@extends('layouts.app')
@section('title', 'Rekap Shift')

@section('content')
  @include('partials.topbar', [
    'title' => 'Rekap Shift & Selisih Kas',
    'sub'   => $from->format('d/m/Y').' – '.$to->format('d/m/Y').' · '.($cashier ? 'Kasir: '.$cashier->name : 'Semua kasir'),
    'right' => '<a class="btn btn-red btn-sm no-print" href="'.e(route('admin.shifts.export', array_filter([
                    'range' => $range,
                    'from'  => $range ? null : $from->format('Y-m-d'),
                    'to'    => $range ? null : $to->format('Y-m-d'),
                    'kasir' => $cashierId,
                ]))).'">📊 Export Excel</a>'
             .'<button class="btn btn-line btn-sm no-print" type="button" onclick="window.print()">🖨️ Cetak / PDF</button>',
  ])

  <div class="page">

    <div class="toolbar no-print">
      @foreach([7 => '7 hari', 14 => '14 hari', 30 => '30 hari'] as $v => $label)
        <a class="chip {{ $range === $v ? 'on' : '' }}"
           href="{{ route('admin.shifts', array_filter(['range' => $v, 'kasir' => $cashierId])) }}">{{ $label }}</a>
      @endforeach

      <form method="GET" action="{{ route('admin.shifts') }}" class="date-range {{ $range === null ? 'on' : '' }}">
        <span class="lbl">📅</span>
        <input class="inp" type="date" name="from" value="{{ $from->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" required>
        <span class="sep">–</span>
        <input class="inp" type="date" name="to" value="{{ $to->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" required>
        @if($cashierId)<input type="hidden" name="kasir" value="{{ $cashierId }}">@endif
        <button class="btn btn-red btn-sm" type="submit">Terapkan</button>
      </form>

      <form method="GET" action="{{ route('admin.shifts') }}" class="cashier-pick {{ $cashierId ? 'on' : '' }}">
        @if($range)
          <input type="hidden" name="range" value="{{ $range }}">
        @else
          <input type="hidden" name="from" value="{{ $from->format('Y-m-d') }}">
          <input type="hidden" name="to" value="{{ $to->format('Y-m-d') }}">
        @endif
        <span class="lbl">👤</span>
        <select class="inp" name="kasir" onchange="this.form.submit()" aria-label="Filter kasir">
          <option value="">Semua kasir</option>
          @foreach($cashiers as $c)
            <option value="{{ $c->id }}" @selected($cashierId === $c->id)>{{ $c->name }}{{ $c->trashed() ? ' (nonaktif)' : '' }}</option>
          @endforeach
        </select>
      </form>
    </div>

    @if($errors->any())<div class="alert alert-err no-print">Format tanggal tidak valid.</div>@endif

    <div class="kpis">
      <div class="kpi">
        <div class="lb">Penjualan tunai</div>
        <div class="vl">{{ rupiah($cashTotal) }}</div>
        <div class="mini">masuk laci kasir</div>
      </div>
      <div class="kpi">
        <div class="lb">Penjualan QRIS</div>
        <div class="vl">{{ rupiah($qrisTotal) }}</div>
        <div class="mini">masuk rekening</div>
      </div>
      <div class="kpi {{ abs($gapTotal) > 0.009 ? 'hero' : '' }}">
        <div class="lb">Akumulasi selisih kas</div>
        <div class="vl" @if(abs($gapTotal) <= 0.009) style="color:var(--green)" @endif>
          {{ $gapTotal > 0 ? '+' : '' }}{{ rupiah($gapTotal) }}
        </div>
        <div class="mini" @if(abs($gapTotal) > 0.009) style="color:rgba(255,255,255,.8)" @endif>
          {{ $gapTotal < 0 ? 'kas kurang' : ($gapTotal > 0 ? 'kas lebih' : 'kas cocok') }}
        </div>
      </div>
      <div class="kpi">
        <div class="lb">Shift</div>
        <div class="vl">{{ $shifts->count() }}</div>
        <div class="mini">{{ $gapCount }} selisih · {{ $openCount }} berjalan</div>
      </div>
    </div>

    @unless($cashierId)
      <div class="sec-title">Selisih per kasir</div>
      <div class="tbl-wrap" style="margin-bottom:14px">
        <table>
          <thead><tr>
            <th>Kasir</th><th class="r">Shift</th><th class="r">Penjualan tunai</th>
            <th class="r">Total selisih</th><th class="r">Rata-rata</th><th class="r no-print"></th>
          </tr></thead>
          <tbody>
          @forelse($perCashier as $pc)
            <tr>
              <td><b>{{ $pc['name'] }}</b></td>
              <td class="r num">{{ $pc['shifts'] }}</td>
              <td class="r num">{{ rupiah($pc['cash']) }}</td>
              <td class="r num" style="font-weight:800;color:{{ abs($pc['gap']) > 0.009 ? 'var(--red)' : 'var(--green)' }}">
                {{ $pc['gap'] > 0 ? '+' : '' }}{{ rupiah($pc['gap']) }}
              </td>
              <td class="r num" style="color:var(--ink-3)">{{ $pc['gapAvg'] > 0 ? '+' : '' }}{{ rupiah($pc['gapAvg']) }}</td>
              <td class="r no-print">
                @if($pc['id'])
                  <a class="btn btn-line btn-sm" href="{{ route('admin.shifts', array_filter(['range' => $range, 'from' => $range ? null : $from->format('Y-m-d'), 'to' => $range ? null : $to->format('Y-m-d'), 'kasir' => $pc['id']])) }}">Lihat</a>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6"><div class="empty"><b>Belum ada shift</b>Tidak ada shift pada periode ini.</div></td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    @endunless

    <div class="sec-title">Riwayat shift</div>
    <div class="tbl-wrap">
      <table>
        <thead><tr>
          <th>Tanggal</th><th>Kasir</th><th>Shift</th><th class="r">Trx</th>
          <th class="r">Modal awal</th><th class="r">Penjualan tunai</th>
          <th class="r">Kas seharusnya</th><th class="r">Kas aktual</th><th class="r">Selisih</th><th>Catatan</th>
        </tr></thead>
        <tbody>
        @forelse($shifts as $s)
          @php $gap = (float) ($s->gap ?? 0); @endphp
          <tr>
            <td class="num mini">
              {{ $s->opened_at->format('d/m/Y') }}<br>
              <span style="color:var(--ink-3)">{{ $s->opened_at->format('H:i') }}–{{ $s->closed_at?->format('H:i') ?? '…' }}</span>
            </td>
            <td><b>{{ $s->user?->name ?? 'Kasir terhapus' }}</b></td>
            <td>
              <span class="tag t-gray">{{ ucfirst($s->shift_type) }}</span>
              @if($s->isOpen())<span class="tag t-amber">Berjalan</span>@endif
            </td>
            <td class="r num">{{ (int) $s->trx_count }}</td>
            <td class="r num" style="color:var(--ink-3)">{{ rupiah($s->opening_cash) }}</td>
            <td class="r num">{{ rupiah($s->cash_sales) }}</td>
            <td class="r num" style="font-weight:800">{{ rupiah($s->expected) }}</td>
            <td class="r num">{{ $s->isOpen() ? '—' : rupiah($s->actual_cash) }}</td>
            <td class="r num" style="font-weight:800">
              @if($s->isOpen())
                <span style="color:var(--ink-3)">—</span>
              @else
                <span class="tag {{ abs($gap) > 0.009 ? ($gap < 0 ? 't-red' : 't-amber') : 't-green' }}">
                  {{ $gap > 0 ? '+' : '' }}{{ rupiah($gap) }}
                </span>
              @endif
            </td>
            <td class="mini">{{ $s->note ?: '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="10"><div class="empty"><b>Belum ada shift</b>Tidak ada shift yang dibuka pada periode ini.</div></td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="mini" style="margin-top:10px">
      Kas seharusnya = modal awal + penjualan tunai. Selisih = kas aktual − kas seharusnya.
      Nilai minus berarti kas kurang, plus berarti kas lebih. QRIS tidak ikut dihitung karena tidak masuk laci.
    </div>

  </div>
@endsection