@extends('layouts.app')
@section('title', 'Riwayat Shift')

@section('content')
  @include('partials.topbar', ['title' => 'Riwayat Shift', 'sub' => 'Transaksi pada shift aktif Anda'])

  <div class="page">
    <div class="kpis" style="margin-bottom:18px">
      <div class="kpi hero">
        <div class="lb">Total penjualan</div>
        <div class="vl">{{ rupiah($cash + $qris) }}</div>
        <div class="mini" style="color:#9A9AA2">{{ $doneN }} transaksi</div>
      </div>
      <div class="kpi"><div class="lb">Tunai</div><div class="vl">{{ rupiah($cash) }}</div><div class="mini">{{ $cashN }} transaksi</div></div>
      <div class="kpi"><div class="lb">QRIS</div><div class="vl">{{ rupiah($qris) }}</div><div class="mini">{{ $qrisN }} transaksi</div></div>
    </div>

    <form class="toolbar" method="GET" action="{{ route('kasir.riwayat') }}">
      <div class="search">
        <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="5" stroke="#A1A1A8" stroke-width="1.7"/><path d="M11 11l3.2 3.2" stroke="#A1A1A8" stroke-width="1.7" stroke-linecap="round"/></svg>
        <input class="inp" name="q" value="{{ $q }}" placeholder="Cari nomor transaksi atau pelanggan…">
      </div>
      <a class="btn btn-line" href="{{ route('kasir.shift.edit') }}">Tutup shift</a>
    </form>

    @if($transactions->count())
      <div class="tbl-wrap">
        <table>
          <thead><tr>
            <th>No. transaksi</th><th>Waktu</th><th>Pelanggan</th><th>Bayar</th><th class="r">Total</th><th class="r">Aksi</th>
          </tr></thead>
          <tbody>
            @foreach($transactions as $t)
              <tr>
                <td><b>{{ $t->invoice_no }}</b>@if($t->isVoid())<span class="tag t-red">Void</span>@endif</td>
                <td class="num">{{ $t->created_at->format('H:i') }}</td>
                <td>{{ $t->customer_name ?: '—' }}</td>
                <td><span class="tag {{ $t->payment_method === 'cash' ? 't-gray' : 't-black' }}">{{ $t->paymentLabel() }}</span></td>
                <td class="r num" style="font-weight:800">{{ rupiah($t->total) }}</td>
                <td class="r"><a class="btn btn-line btn-sm" href="{{ route('struk.show', $t) }}">Struk</a></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      {{ $transactions->links() }}
    @else
      <div class="card"><div class="empty"><b>Belum ada transaksi</b>Transaksi pada shift ini akan muncul di sini.</div></div>
    @endif
  </div>
@endsection
