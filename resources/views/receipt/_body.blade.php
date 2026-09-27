@php $s = $appSettings; @endphp
<div class="receipt {{ ($s['paper_width'] ?? '58mm') === '80mm' ? 'w80' : '' }}">
  <div class="ctr">
    <div class="logo">{{ $s['store_name'] ?? 'Javapucino' }}</div>
    <div>{{ $s['store_address'] ?? '' }}</div>
    <div>{{ $s['store_phone'] ?? '' }}</div>
  </div>
  <div class="hr"></div>
  <div class="ln"><span>No</span><span>{{ $trx->invoice_no }}</span></div>
  <div class="ln"><span>Tanggal</span><span>{{ $trx->created_at->format('d/m/Y H:i') }}</span></div>
  <div class="ln"><span>Kasir</span><span>{{ $trx->cashier?->name }}</span></div>
  @if($trx->customer_name)
    <div class="ln"><span>Pelanggan</span><span>{{ $trx->customer_name }}</span></div>
  @endif
  @if($trx->queue_no)
    <div class="hr"></div>
    <div class="ctr big">NO. ANTRIAN {{ $trx->queueLabel() }}</div>
  @endif
  <div class="hr"></div>
  @foreach($trx->items as $i)
    <div style="margin-bottom:4px">
      <div>{{ $i->product_name }}</div>
      <div class="ln"><span>&nbsp;&nbsp;{{ $i->qty }} x {{ angka($i->price) }}</span><span>{{ angka($i->subtotal) }}</span></div>
    </div>
  @endforeach
  <div class="hr"></div>
  <div class="ln"><span>Subtotal</span><span>{{ angka($trx->subtotal) }}</span></div>
  @if((float) $trx->tax_amount > 0)
    <div class="ln"><span>Pajak {{ (float) $trx->tax_percent }}%</span><span>{{ angka($trx->tax_amount) }}</span></div>
  @endif
  <div class="ln big"><span>TOTAL</span><span>{{ angka($trx->total) }}</span></div>
  <div class="ln"><span>{{ $trx->paymentLabel() }}</span><span>{{ angka($trx->paid_amount) }}</span></div>
  @if($trx->payment_method === 'cash')
    <div class="ln"><span>Kembali</span><span>{{ angka($trx->change_amount) }}</span></div>
  @else
    <div class="ln"><span>Ref</span><span>{{ $trx->qris_reference ?? '-' }}</span></div>
  @endif
  @if($trx->isVoid())
    <div class="hr"></div>
    <div class="ctr big">*** VOID ***</div>
    <div class="ctr">{{ $trx->void_reason }}</div>
  @endif
  <div class="hr"></div>
  <div class="ctr">{{ $s['receipt_footer'] ?? '' }}</div>
  <div class="ctr" style="margin-top:6px">Cetakan ke-{{ max(1, $trx->printed_count) }} · {{ $s['paper_width'] ?? '58mm' }}</div>
</div>
