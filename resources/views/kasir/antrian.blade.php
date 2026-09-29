@extends('layouts.app')
@section('title', 'Antrian')

@php $isAdmin = auth()->user()->isAdmin(); @endphp

@section('content')
  @include('partials.topbar', [
    'title' => 'Antrian',
    'sub'   => 'Kasir yang mengatur nomor yang tampil di layar pelanggan',
    'right' => '<a class="btn btn-line btn-sm" target="_blank" href="'.route('menu').'">📺 Buka layar pelanggan</a>',
  ])

  <div class="page">
    <div class="qctrl">
      <div>
        <div class="qbig">
          <div class="cap">Nomor Antrian</div>
          <div class="no">{{ $current ? $current->queueLabel() : '—' }}</div>
          <div class="who">
            {{ $current
                ? trim(($current->customer_name ? $current->customer_name.' · ' : '').$current->invoice_no)
                : 'Belum ada nomor yang dipanggil' }}
          </div>
          <div class="acts">
            <form method="POST" action="{{ $isAdmin ? route('admin.antrian.picked') : route('kasir.antrian.picked') }}" style="flex:1">
              @csrf
              <button class="btn btn-line btn-block" type="submit" {{ $current ? '' : 'disabled' }}
                      style="background:#1B1B1F;border-color:#2C2C32;color:#fff">Sudah diambil</button>
            </form>
            <form method="POST" action="{{ $isAdmin ? route('admin.antrian.next') : route('kasir.antrian.next') }}" style="flex:1">
              @csrf
              <button class="btn btn-red btn-block" type="submit" {{ $waiting->count() ? '' : 'disabled' }}>Panggil berikutnya</button>
            </form>
          </div>
        </div>

        <div class="kpis" style="margin-top:14px;grid-template-columns:1fr 1fr">
          <div class="kpi"><div class="lb">Menunggu</div><div class="vl">{{ $waiting->count() }}</div></div>
          <div class="kpi"><div class="lb">Selesai hari ini</div><div class="vl">{{ $done->count() }}</div></div>
        </div>
      </div>

      <div class="card">
        <div class="card-h"><h3>Daftar tunggu</h3><div class="grow"></div>
          <span class="tag t-gray">{{ $waiting->count() }} nomor</span></div>
        <div class="card-b">
          @forelse($waiting as $i => $t)
            <div class="qrow {{ $i === 0 ? 'next-up' : '' }}">
              <div class="n">{{ $t->queueLabel() }}</div>
              <div class="g">
                <b>{{ $t->customer_name ?: 'Tanpa nama' }}</b>
                <span class="mini">{{ $t->invoice_no }} · {{ $t->created_at->format('H:i') }} · {{ $t->totalQty() }} item</span>
              </div>
              <form method="POST" action="{{ $isAdmin ? route('admin.antrian.call', $t) : route('kasir.antrian.call', $t) }}">
                @csrf
                <button class="btn btn-line btn-sm" type="submit">Panggil</button>
              </form>
            </div>
          @empty
            <div class="empty"><b>Daftar tunggu kosong</b>Nomor antrian baru muncul otomatis setiap transaksi diselesaikan.</div>
          @endforelse
        </div>
      </div>
    </div>

    <div class="sec-title">Sudah selesai</div>
    <div class="card"><div class="card-b">
      @forelse($done as $t)
        <div class="lrow">
          <span class="rank">{{ $t->queueLabel() }}</span>
          <div class="g"><b>{{ $t->customer_name ?: 'Tanpa nama' }}</b>
            <span class="mini">{{ $t->invoice_no }} · {{ $t->created_at->format('H:i') }}</span></div>
          <form method="POST" action="{{ $isAdmin ? route('admin.antrian.call', $t) : route('kasir.antrian.call', $t) }}">
            @csrf
            <button class="btn btn-ghost btn-sm" type="submit">Panggil ulang</button>
          </form>
        </div>
      @empty
        <div class="empty"><b>Belum ada yang selesai</b>Nomor yang sudah diambil pelanggan tampil di sini.</div>
      @endforelse
    </div></div>
  </div>
@endsection
