@extends('layouts.app')
@section('title', 'Laporan')

@php
  $maxD    = max($perDay->max('value') ?: 0, 1);
  $capStep = (int) ceil($days / 16); // label tanggal dijarangkan untuk rentang panjang
@endphp

@section('content')
  @include('partials.topbar', [
    'title' => 'Laporan',
    'sub'   => 'Penjualan '.$from->format('d/m/Y').' – '.$to->format('d/m/Y').' · '.($cashier ? 'Kasir: '.$cashier->name : 'Semua kasir'),
    'right' => '<a class="btn btn-red btn-sm no-print" href="'.e(route('admin.report.export', array_filter([
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
        <a class="chip {{ $range === $v ? 'on' : '' }}" href="{{ route('admin.report', array_filter(['range' => $v, 'kasir' => $cashierId])) }}">{{ $label }}</a>
      @endforeach

      <form method="GET" action="{{ route('admin.report') }}" class="date-range {{ $range === null ? 'on' : '' }}">
        <span class="lbl">📅</span>
        <input class="inp" type="date" name="from" value="{{ $from->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" required>
        <span class="sep">–</span>
        <input class="inp" type="date" name="to" value="{{ $to->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" required>
        @if($cashierId)<input type="hidden" name="kasir" value="{{ $cashierId }}">@endif
        <button class="btn btn-red btn-sm" type="submit">Terapkan</button>
      </form>

      <form method="GET" action="{{ route('admin.report') }}" class="cashier-pick {{ $cashierId ? 'on' : '' }}">
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
      <div class="kpi hero"><div class="lb">Omzet</div><div class="vl">{{ rupiah($omzet) }}</div>
        <div class="mini" style="color:rgba(255,255,255,.8)">{{ $count }} transaksi</div></div>
      <div class="kpi"><div class="lb">HPP</div><div class="vl">{{ rupiah($hpp) }}</div>
        <div class="mini">modal bahan terjual</div></div>
      <div class="kpi"><div class="lb">Laba kotor</div><div class="vl" style="color:var(--green)">{{ rupiah($omzet - $hpp) }}</div>
        <div class="mini">margin {{ $omzet > 0 ? round(($omzet - $hpp) / $omzet * 100) : 0 }}%</div></div>
      <div class="kpi"><div class="lb">Rata-rata harian</div><div class="vl">{{ rupiah($omzet / $days) }}</div>
        <div class="mini">{{ $range ? $days.' hari terakhir' : 'selama '.$days.' hari' }}</div></div>
    </div>

    <div class="card" style="margin-top:14px">
      <div class="card-h"><h3>Omzet harian</h3></div>
      <div class="card-b">
        <div class="bars">
          @foreach($perDay as $d)
            <div class="b {{ $d['value'] > 0 && $d['value'] == $maxD ? 'top' : '' }}"
                 title="{{ $d['date']->format('d/m/Y') }} — {{ rupiah($d['value']) }}">
              <div class="fill" style="height:{{ max(3, $d['value'] / $maxD * 100) }}%"></div>
              <div class="cap">{{ $loop->index % $capStep === 0 ? $d['date']->format('d/m') : '' }}&nbsp;</div>
            </div>
          @endforeach
        </div>
      </div>
    </div>

    @unless($cashierId)
      <div class="sec-title">Penjualan per kasir</div>
      <div class="tbl-wrap" style="margin-bottom:14px">
        <table>
          <thead><tr>
            <th>Kasir</th><th class="r">Transaksi</th><th class="r">Omzet</th><th class="r">Kontribusi</th><th class="r no-print"></th>
          </tr></thead>
          <tbody>
          @forelse($perCashier as $pc)
            <tr>
              <td><b>{{ $pc['name'] }}</b></td>
              <td class="r num">{{ $pc['count'] }}</td>
              <td class="r num" style="font-weight:800">{{ rupiah($pc['omzet']) }}</td>
              <td class="r num">{{ $omzet > 0 ? round($pc['omzet'] / $omzet * 100) : 0 }}%</td>
              <td class="r no-print">
                @if($pc['id'])
                  <a class="btn btn-line btn-sm" href="{{ route('admin.report', array_filter(['range' => $range, 'from' => $range ? null : $from->format('Y-m-d'), 'to' => $range ? null : $to->format('Y-m-d'), 'kasir' => $pc['id']])) }}">Lihat</a>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="5"><div class="empty"><b>Belum ada penjualan</b>Tidak ada transaksi pada periode ini.</div></td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    @endunless

    <div class="sec-title">Penjualan per menu</div>
    <div class="tbl-wrap">
      <table>
        <thead><tr>
          <th>Menu</th><th class="r">Terjual</th><th class="r">Omzet</th>
          <th class="r">HPP</th><th class="r">Laba kotor</th><th class="r">Margin</th>
        </tr></thead>
        <tbody>
        @forelse($rows as $r)
          @php $profit = (float) $r->revenue - (float) $r->cost; @endphp
          <tr>
            <td><b>{{ $r->product_name }}</b></td>
            <td class="r num">{{ (int) $r->qty }}</td>
            <td class="r num">{{ rupiah($r->revenue) }}</td>
            <td class="r num" style="color:var(--ink-3)">{{ rupiah($r->cost) }}</td>
            <td class="r num" style="font-weight:800;color:var(--green)">{{ rupiah($profit) }}</td>
            <td class="r num">{{ (float) $r->revenue > 0 ? round($profit / (float) $r->revenue * 100) : 0 }}%</td>
          </tr>
        @empty
          <tr><td colspan="6"><div class="empty"><b>Belum ada penjualan</b>Tidak ada transaksi pada periode ini.</div></td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
