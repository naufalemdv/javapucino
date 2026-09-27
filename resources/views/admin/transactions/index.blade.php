@extends('layouts.app')
@section('title', 'Transaksi')

@php
  $filter = $filters['filter'] ?? 'all';
  $q      = $filters['q'] ?? '';
@endphp

@section('content')
  @include('partials.topbar', [
    'title' => 'Transaksi',
    'sub'   => $total.' struk tercatat',
    'right' => '<a class="btn btn-line btn-sm" href="'.route('admin.transactions.export', request()->query()).'">⬇ Export Excel</a>',
  ])

  <div class="page">
    <form class="toolbar" method="GET">
      <div class="search">
        <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="5" stroke="#A1A1A8" stroke-width="1.7"/><path d="M11 11l3.2 3.2" stroke="#A1A1A8" stroke-width="1.7" stroke-linecap="round"/></svg>
        <input class="inp" name="q" value="{{ $q }}" placeholder="Cari nomor transaksi atau pelanggan…">
      </div>
      <input class="inp" type="date" name="dari"   value="{{ $filters['dari'] ?? '' }}"   style="width:auto" data-autosubmit>
      <input class="inp" type="date" name="sampai" value="{{ $filters['sampai'] ?? '' }}" style="width:auto" data-autosubmit>
      <select class="inp" name="kasir" style="width:auto" data-autosubmit>
        <option value="">Semua kasir</option>
        @foreach($cashiers as $c)
          <option value="{{ $c->id }}" @selected(($filters['kasir'] ?? null) == $c->id)>{{ $c->name }}</option>
        @endforeach
      </select>
      <input type="hidden" name="filter" value="{{ $filter }}">
    </form>

    <div class="cats" style="margin-bottom:16px">
      @foreach(['all' => 'Semua', 'cash' => 'Tunai', 'qris' => 'QRIS', 'void' => 'Void'] as $k => $label)
        <a class="chip {{ $filter === $k ? 'on' : '' }}"
           href="{{ route('admin.transactions.index', array_merge(request()->query(), ['filter' => $k, 'page' => 1])) }}">{{ $label }}</a>
      @endforeach
    </div>

    @if($transactions->count())
      <div class="tbl-wrap">
        <table>
          <thead><tr>
            <th>No. transaksi</th><th>Tanggal</th><th>Kasir</th><th>Pelanggan</th>
            <th>Bayar</th><th class="r">Total</th><th class="r">Aksi</th>
          </tr></thead>
          <tbody>
          @foreach($transactions as $t)
            <tr>
              <td><b>{{ $t->invoice_no }}</b>@if($t->isVoid())<span class="tag t-red">Void</span>@endif</td>
              <td class="num">{{ $t->created_at->format('d/m/Y H:i') }}</td>
              <td>{{ $t->cashier?->name }}</td>
              <td>{{ $t->customer_name ?: '—' }}</td>
              <td><span class="tag {{ $t->payment_method === 'cash' ? 't-gray' : 't-black' }}">{{ $t->paymentLabel() }}</span></td>
              <td class="r num" style="font-weight:800;{{ $t->isVoid() ? 'text-decoration:line-through;color:var(--ink-4)' : '' }}">{{ rupiah($t->total) }}</td>
              <td class="r" style="white-space:nowrap">
                <a class="btn btn-line btn-sm" href="{{ route('struk.show', $t) }}">Struk</a>
                @unless($t->isVoid())
                  <button class="btn btn-danger-line btn-sm" type="button"
                          data-void="{{ $t->id }}" data-inv="{{ $t->invoice_no }}"
                          data-total="{{ rupiah($t->total) }}" data-items="{{ $t->items_count }}">Void</button>
                @endunless
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      {{ $transactions->links() }}
    @else
      <div class="card"><div class="empty"><b>Tidak ada transaksi</b>Ubah kata kunci atau filter untuk melihat hasil lain.</div></div>
    @endif
  </div>

  <form method="POST" id="voidForm" action="" style="display:none">
    @csrf
    <input type="hidden" name="void_reason" id="vReason">
  </form>
@endsection

@push('scripts')
<script>
const base = @json(url('admin/transaksi'));

document.querySelectorAll('[data-void]').forEach(btn => btn.addEventListener('click', () => {
  openModal(`
    <div class="modal-h">
      <div><h3>Void transaksi</h3><div class="sub">${btn.dataset.inv} · ${btn.dataset.total}</div></div>
      <button class="x" type="button" data-act="close">✕</button>
    </div>
    <div class="modal-b">
      <div class="card" style="border-color:#F3BFC1;background:var(--red-soft);margin-bottom:16px">
        <div class="card-b" style="padding:13px 15px">
          <b style="font-size:13px;color:var(--red-dark)">Transaksi tidak dihapus</b>
          <p class="mini" style="margin:5px 0 0;color:var(--red-dark)">
            Status berubah menjadi <b>void</b>, stok ${btn.dataset.items} item dikembalikan,
            dan seluruh aksi tercatat di audit log.</p>
        </div>
      </div>
      <div class="field"><label>Alasan void (wajib, min. 5 karakter)</label>
        <textarea class="inp" id="vInput" placeholder="Contoh: pelanggan membatalkan pesanan sebelum diracik"></textarea></div>
    </div>
    <div class="modal-f">
      <button class="btn btn-line" type="button" data-act="close">Batal</button>
      <button class="btn btn-red" type="button" id="vOk" style="flex:1.4">Void transaksi</button>
    </div>`);

  document.getElementById('vInput').focus();
  document.getElementById('vOk').addEventListener('click', () => {
    const reason = document.getElementById('vInput').value.trim();
    if (reason.length < 5) return toast('Alasan void wajib diisi minimal 5 karakter');
    const form = document.getElementById('voidForm');
    form.action = base + '/' + btn.dataset.void + '/void';
    document.getElementById('vReason').value = reason;
    form.submit();
  });
}));
</script>
@endpush
