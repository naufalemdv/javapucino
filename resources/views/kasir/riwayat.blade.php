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
                <td class="r"><a class="btn btn-line btn-sm" href="{{ route('struk.show', $t) }}">Struk</a>@unless($t->isVoid())<button class="btn btn-danger-line btn-sm" type="button" data-void="{{ $t->id }}" data-inv="{{ $t->invoice_no }}" data-total="{{ rupiah($t->total) }}" data-items="{{ $t->items_count }}">Void</button>@endunless</td>
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

  <form method="POST" id="voidForm" action="" style="display:none">
    @csrf
    <input type="hidden" name="void_reason" id="vReason">
  </form>
@endsection

@push('scripts')
<script>
const base = @json(url('kasir/transaksi'));

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
