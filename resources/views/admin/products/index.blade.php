@extends('layouts.app')
@section('title', 'Produk')

@section('content')
  @include('partials.topbar', [
    'title' => 'Produk',
    'sub'   => $total.' menu terdaftar',
    'right' => '<a class="btn btn-red btn-sm" href="'.route('admin.products.create').'">+ Tambah menu</a>',
  ])

  <div class="page">
    <form class="toolbar" method="GET">
      <div class="search">
        <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="5" stroke="#A1A1A8" stroke-width="1.7"/><path d="M11 11l3.2 3.2" stroke="#A1A1A8" stroke-width="1.7" stroke-linecap="round"/></svg>
        <input class="inp" name="q" value="{{ $q }}" placeholder="Cari nama menu atau SKU…">
      </div>
      @if($catId)<input type="hidden" name="kategori" value="{{ $catId }}">@endif
    </form>

    <div class="cats" style="margin-bottom:16px">
      <a class="chip {{ ! $catId ? 'on' : '' }}" href="{{ route('admin.products.index', ['q' => $q]) }}">Semua</a>
      @foreach($categories as $c)
        <a class="chip {{ $catId === $c->id ? 'on' : '' }}"
           href="{{ route('admin.products.index', ['kategori' => $c->id, 'q' => $q]) }}">{{ $c->icon }} {{ $c->name }}</a>
      @endforeach
    </div>

    <div class="tbl-wrap">
      <table>
        <thead><tr>
          <th>Menu</th><th>Kategori</th><th class="r">Harga</th><th class="r">HPP</th>
          <th class="r">Margin</th><th class="r">Stok</th><th>Status</th><th class="r">Aksi</th>
        </tr></thead>
        <tbody>
        @forelse($products as $p)
          @php $st = $p->stockState(); @endphp
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                @if($p->imageUrl())
                  <img class="pthumb" src="{{ $p->imageUrl() }}" alt="{{ $p->name }}">
                @else
                  <span style="font-size:20px">{{ $p->icon ?: '☕' }}</span>
                @endif
                <div><b>{{ $p->name }}</b>@if($p->is_best_seller)<span class="tag t-red">Best</span>@endif
                  <div class="mini">{{ $p->sku ?: '—' }}</div></div>
              </div>
            </td>
            <td>{{ $p->category?->name ?? '—' }}</td>
            <td class="r num" style="font-weight:800">{{ rupiah($p->price) }}</td>
            <td class="r num" style="color:var(--ink-3)">{{ rupiah($p->hpp) }}</td>
            <td class="r num" style="color:var(--green);font-weight:700">{{ $p->marginPercent() }}%</td>
            <td class="r"><span class="tag {{ $st === 'gone' ? 't-red' : ($st === 'low' ? 't-amber' : 't-gray') }}">{{ $p->stock }}</span></td>
            <td>{!! $p->is_active ? '<span class="tag t-green">Aktif</span>' : '<span class="tag t-gray">Nonaktif</span>' !!}</td>
            <td class="r" style="white-space:nowrap">
              <a class="btn btn-line btn-sm" href="{{ route('admin.products.edit', $p) }}">Ubah</a>
              <form method="POST" action="{{ route('admin.products.destroy', $p) }}" style="display:inline"
                    data-confirm="Hapus menu &quot;{{ $p->name }}&quot;? Riwayat transaksi tetap tersimpan.">
                @csrf @method('DELETE')
                <button class="btn btn-danger-line btn-sm" type="submit">Hapus</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="8"><div class="empty"><b>Menu tidak ditemukan</b>Ubah kata kunci atau kategori.</div></td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    {{ $products->links() }}
  </div>
@endsection
