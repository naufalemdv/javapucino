@extends('layouts.app')
@section('title', 'Struk '.$trx->invoice_no)

@section('content')
  @include('partials.topbar', [
    'title' => $fresh ? 'Transaksi tersimpan' : 'Struk '.$trx->invoice_no,
    'sub'   => $trx->invoice_no.' · '.$trx->created_at->format('d/m/Y H:i'),
  ])

  <div class="page">
    <div style="max-width:420px;margin:0 auto">
      @if($fresh)
        <div class="paid-hero">
          <div class="tick">✓</div>
          <h3>{{ $trx->payment_method === 'cash' ? 'Pembayaran tunai diterima' : 'Pembayaran QRIS berhasil' }}</h3>
          <div class="mini">{{ $trx->items->count() }} menu · {{ rupiah($trx->total) }}</div>
          @if($trx->payment_method === 'cash')
            <div class="mini" style="margin-top:12px;font-weight:700;color:var(--ink-2)">Kembalian</div>
            <div class="chg">{{ rupiah($trx->change_amount) }}</div>
          @endif
          @if($trx->queue_no)
            <div style="margin-top:16px;background:var(--red);color:#fff;border-radius:16px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;text-align:left">
              <div>
                <b style="font-size:13px">Nomor antrian</b>
                <div style="font-size:11.5px;color:#9A9AA2;font-weight:600">Sudah tampil di layar pelanggan</div>
              </div>
              <span class="num" style="font-size:32px;font-weight:900;letter-spacing:-.04em">{{ $trx->queueLabel() }}</span>
            </div>
          @endif
        </div>
      @endif

      @include('receipt._body')

      <div style="display:flex;gap:9px;margin-top:18px">
        <a class="btn btn-line" style="flex:1"
           href="{{ auth()->user()->isAdmin() ? route('admin.transactions.index') : route('kasir.pos') }}">
          {{ auth()->user()->isAdmin() ? 'Kembali ke transaksi' : 'Kembali ke kasir' }}
        </a>
        <a class="btn btn-black" style="flex:1.4" href="{{ route('struk.print', $trx) }}" target="_blank">🖨️ Cetak struk</a>
      </div>
    </div>
  </div>
@endsection
