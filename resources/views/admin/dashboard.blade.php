@extends('layouts.app')
@section('title', 'Dashboard')

@php
  $maxH = max($byHour->max('value') ?: 0, 1);
  $maxTop = $top->max('qty') ?: 1;
  $pct = $count ? round($cashN / $count * 100) : 0;
@endphp

@section('content')
  @include('partials.topbar', [
    'title' => 'Dashboard',
    'sub'   => 'Ringkasan hari ini · '.now()->format('d/m/Y'),
    'right' => '<a class="btn btn-line btn-sm" target="_blank" href="'.route('menu').'">Lihat menu board</a>',
  ])

  <div class="page">
    <div class="kpis">
      <div class="kpi hero">
        <div class="lb">Omzet hari ini</div>
        <div class="vl">{{ rupiah($omzet) }}</div>
        <div class="mini" style="color:#9A9AA2">Laba kotor {{ rupiah($omzet - $hpp) }}</div>
      </div>
      <div class="kpi"><div class="lb">Transaksi</div><div class="vl">{{ $count }}</div>
        <div class="mini">{{ $cashN }} tunai · {{ $qrisN }} QRIS</div></div>
      <div class="kpi"><div class="lb">Rata-rata per struk</div><div class="vl">{{ rupiah($avg) }}</div>
        <div class="mini">dari {{ $count }} struk</div></div>
      <div class="kpi"><div class="lb">Item terjual</div><div class="vl">{{ $items }}</div>
        <div class="mini">{{ $variety }} jenis menu</div></div>
    </div>

    <div class="grid2" style="margin-top:14px">
      <div class="card">
        <div class="card-h"><h3>Penjualan per jam</h3><div class="grow"></div>
          <span class="mini">Puncak {{ rupiah($byHour->max('value')) }}</span></div>
        <div class="card-b">
          <div class="bars">
            @foreach($byHour as $h)
              <div class="b {{ $h['value'] > 0 && $h['value'] == $maxH ? 'top' : '' }}" title="{{ $h['hour'] }}:00 — {{ rupiah($h['value']) }}">
                <div class="fill" style="height:{{ max(3, $h['value'] / $maxH * 100) }}%"></div>
                <div class="cap">{{ $h['hour'] }}</div>
              </div>
            @endforeach
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-h"><h3>Metode pembayaran</h3></div>
        <div class="card-b">
          <div class="donut">
            <svg width="112" height="112" viewBox="0 0 42 42" style="flex:none">
              <circle cx="21" cy="21" r="15.9" fill="none" stroke="#F5A5A7" stroke-width="7"/>
              <circle cx="21" cy="21" r="15.9" fill="none" stroke="#E31E24" stroke-width="7"
                      stroke-dasharray="{{ $pct }} {{ 100 - $pct }}" stroke-dashoffset="25"/>
              <text x="21" y="22.6" text-anchor="middle" font-size="7" font-weight="800" font-family="Archivo">{{ $pct }}%</text>
            </svg>
            <div style="flex:1">
              <div class="legend"><span class="dot" style="background:#E31E24"></span>Tunai
                <span style="margin-left:auto" class="num">{{ rupiah($cashSum) }}</span></div>
              <div class="legend"><span class="dot" style="background:#F5A5A7"></span>QRIS
                <span style="margin-left:auto" class="num">{{ rupiah($qrisSum) }}</span></div>
              <p class="mini" style="margin:12px 0 0">Setoran tunai ke brankas perlu dicocokkan saat tutup shift.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="grid3" style="margin-top:14px">
      <div class="card">
        <div class="card-h"><h3>Menu terlaris hari ini</h3></div>
        <div class="card-b" style="padding-top:6px">
          @forelse($top as $i => $row)
            <div class="lrow">
              <span class="rank {{ $i === 0 ? 'p1' : '' }}">{{ $i + 1 }}</span>
              <div class="g"><b>{{ $row->product_name }}</b>
                <div class="meter"><i style="width:{{ $row->qty / $maxTop * 100 }}%"></i></div></div>
              <span class="num" style="font-weight:800">{{ $row->qty }}</span>
            </div>
          @empty
            <div class="empty"><b>Belum ada penjualan</b>Data muncul setelah transaksi pertama.</div>
          @endforelse
        </div>
      </div>

      <div class="card">
        <div class="card-h"><h3>Perlu restock</h3><div class="grow"></div>
          <span class="tag t-amber">{{ $low->count() }}</span></div>
        <div class="card-b" style="padding-top:6px">
          @forelse($low->take(6) as $x)
            <div class="lrow">
              <div class="g"><b>{{ $x['name'] }}</b><span class="mini">{{ $x['info'] }}</span></div>
              <span class="tag {{ $x['out'] ? 't-red' : 't-amber' }}">{{ $x['out'] ? 'Habis' : 'Menipis' }}</span>
            </div>
          @empty
            <div class="empty"><b>Stok aman</b>Tidak ada item di bawah batas minimum.</div>
          @endforelse
        </div>
      </div>

      <div class="card">
        <div class="card-h"><h3>Transaksi terbaru</h3><div class="grow"></div>
          <a class="btn btn-ghost btn-sm" href="{{ route('admin.transactions.index') }}">Semua</a></div>
        <div class="card-b" style="padding-top:6px">
          @forelse($latest as $t)
            <a class="lrow" href="{{ route('struk.show', $t) }}">
              <div class="g"><b>{{ $t->invoice_no }}</b>
                <span class="mini">{{ $t->created_at->format('H:i') }} · {{ $t->cashier?->name }}</span></div>
              <span class="num" style="font-weight:800;{{ $t->isVoid() ? 'text-decoration:line-through;color:var(--ink-4)' : '' }}">{{ rupiah($t->total) }}</span>
            </a>
          @empty
            <div class="empty"><b>Belum ada transaksi</b>Struk terbaru akan muncul di sini.</div>
          @endforelse
        </div>
      </div>
    </div>
  </div>
@endsection
