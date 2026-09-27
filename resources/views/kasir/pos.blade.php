@extends('layouts.app')
@section('title', 'Kasir')

@section('content')
  @include('partials.topbar', [
    'title' => 'Kasir',
    'sub'   => 'Shift '.($shift?->shift_type ?? '-').' · '.now()->format('d/m/Y'),
    'right' => '<div class="clock" id="clock">'.now()->format('H:i').'</div>',
  ])

  <div class="pos">
    <div class="pos-menu">
      <div class="pos-filters">
        <form class="toolbar" style="margin-bottom:10px" method="GET" action="{{ route('kasir.pos') }}">
          <div class="search">
            <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="5" stroke="#A1A1A8" stroke-width="1.7"/><path d="M11 11l3.2 3.2" stroke="#A1A1A8" stroke-width="1.7" stroke-linecap="round"/></svg>
            <input class="inp" name="q" value="{{ $q }}" placeholder="Cari menu atau SKU…">
          </div>
          @if($catId)<input type="hidden" name="kategori" value="{{ $catId }}">@endif
        </form>
        <div class="cats">
          <a class="chip {{ ! $catId ? 'on' : '' }}" href="{{ route('kasir.pos', ['q' => $q]) }}">Semua menu</a>
          @foreach($categories as $c)
            <a class="chip {{ $catId === $c->id ? 'on' : '' }}"
               href="{{ route('kasir.pos', ['kategori' => $c->id, 'q' => $q]) }}">{{ $c->icon }} {{ $c->name }}</a>
          @endforeach
        </div>
      </div>

      <div class="pos-grid">
        @forelse($products as $p)
          @php $state = $p->stockState(); @endphp
          <button class="mcard {{ $state === 'gone' ? 'out' : '' }}" type="button"
                  data-add="{{ $p->id }}" data-name="{{ $p->name }}" data-price="{{ (int) $p->price }}"
                  data-icon="{{ $p->icon }}" data-image="{{ $p->imageUrl() }}" data-stock="{{ $p->stock }}"
                  {{ $state === 'gone' ? 'disabled' : '' }}>
            <span class="qbadge" data-badge="{{ $p->id }}" style="display:none">0</span>
            @if($state === 'gone')
              <span class="flag gone">Habis</span>
            @elseif($state === 'low')
              <span class="flag low">Sisa {{ $p->stock }}</span>
            @elseif($p->is_best_seller)
              <span class="flag best">Best</span>
            @endif
            @if($p->imageUrl())
              <img class="pimg" src="{{ $p->imageUrl() }}" alt="{{ $p->name }}" loading="lazy">
            @else
              <div class="emo">{{ $p->icon ?: '☕' }}</div>
            @endif
            <div class="nm">{{ $p->name }}</div>
            <div class="st">{{ $state === 'gone' ? 'Stok habis' : 'Stok '.$p->stock }}</div>
            <div class="pr">{{ rupiah($p->price) }}</div>
          </button>
        @empty
          <div class="empty" style="grid-column:1/-1">
            <b>Menu tidak ditemukan</b>Coba kata kunci lain atau pilih kategori “Semua menu”.
          </div>
        @endforelse
      </div>
    </div>

    {{-- Keranjang --}}
    <aside class="cart">
      <div class="cart-h">
        <h2>Keranjang</h2>
        <span class="tag t-gray" id="cartCount" style="display:none">0 item</span>
        <div style="flex:1"></div>
        <button class="btn btn-ghost btn-sm" type="button" id="btnClear" style="display:none">Kosongkan</button>
        <button class="x" type="button" data-act="close-cart" style="display:none" id="closeCart">✕</button>
      </div>

      <div class="cart-items" id="cartItems"></div>

      <div class="cart-cust" id="custWrap" style="display:none">
        <input class="inp" id="custName" maxlength="100" placeholder="Nama pelanggan / no. antrian (opsional)">
      </div>

      <div class="cart-sum">
        <div class="sline"><span>Subtotal</span><span class="num" id="sumSub">Rp 0</span></div>
        <div class="sline"><span>Pajak {{ (float) ($appSettings['tax_percent'] ?? 0) }}%</span><span class="num" id="sumTax">Rp 0</span></div>
        <div class="total-box"><span>Total bayar</span><b id="sumTotal">Rp 0</b></div>
        <button class="btn btn-black btn-lg btn-block" type="button" id="btnPay" disabled>Bayar sekarang</button>
      </div>
    </aside>
  </div>

  <button class="cart-fab" type="button" data-act="open-cart" id="cartFab" style="display:none">
    <span class="l"><span class="cnt" id="fabCount">0</span> Lihat keranjang</span>
    <span class="t" id="fabTotal">Rp 0</span>
  </button>

  {{-- Form submit transaksi --}}
  <form method="POST" action="{{ route('kasir.transaksi.store') }}" id="checkoutForm" style="display:none">
    @csrf
    <input type="hidden" name="payment_method" id="fMethod" value="cash">
    <input type="hidden" name="paid_amount"    id="fPaid"   value="0">
    <input type="hidden" name="customer_name"  id="fCust"   value="">
    <div id="fItems"></div>
  </form>
@endsection

@push('scripts')
<script>
window.JV = {
  taxPercent: {{ (float) ($appSettings['tax_percent'] ?? 0) }},
  storeName : @json($appSettings['store_name'] ?? 'Javapucino'),
  qrisNmid  : @json($appSettings['qris_nmid'] ?? '-'),
};
</script>
<script src="{{ asset('js/pos.js') }}"></script>
@endpush
