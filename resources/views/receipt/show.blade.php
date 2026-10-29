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
        <button class="btn btn-black" style="flex:1.4" id="btn-cetak" onclick="bukaModalCetak()">🖨️ Cetak struk</button>
      </div>
    </div>
  </div>

  {{-- Modal cetak Bluetooth --}}
  <div id="modal-cetak" style="display:none;position:fixed;inset:0;z-index:999;background:rgba(0,0,0,.45);align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:16px;padding:24px;width:min(360px,92vw);box-shadow:0 8px 32px rgba(0,0,0,.18)">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <b style="font-size:15px">Cetak Struk</b>
        <button onclick="tutupModalCetak()" style="background:none;border:none;font-size:18px;cursor:pointer;color:#888">✕</button>
      </div>
      <div id="bt-status" style="font-size:13px;color:#555;margin-bottom:16px;min-height:20px"></div>
      <div style="display:flex;flex-direction:column;gap:9px">
        <button class="btn btn-black" id="btn-bt" onclick="cetakBluetooth()">🖨️ Cetak via Bluetooth</button>
        <button class="btn btn-line" style="font-size:13px" onclick="bukaTabCetak()">Cetak via browser (tab baru)</button>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
@php
  $s          = $appSettings;
  $paperWidth = $s['paper_width'] ?? '58mm';
  $charWidth  = $paperWidth === '80mm' ? 48 : 32;
  $receiptData = [
    'store_name'     => $s['store_name']    ?? 'Javapucino',
    'store_address'  => $s['store_address'] ?? '',
    'store_phone'    => $s['store_phone']   ?? '',
    'invoice_no'     => $trx->invoice_no,
    'created_at'     => $trx->created_at->format('d/m/Y H:i'),
    'cashier'        => $trx->cashier?->name ?? '',
    'customer_name'  => $trx->customer_name ?? '',
    'queue_no'       => $trx->queue_no ? $trx->queueLabel() : null,
    'items'          => $trx->items->map(fn($i) => [
      'name'     => $i->product_name,
      'qty'      => $i->qty,
      'price'    => (int) $i->price,
      'subtotal' => (int) $i->subtotal,
    ])->values()->toArray(),
    'subtotal'       => (int) $trx->subtotal,
    'tax_amount'     => (float) $trx->tax_amount,
    'tax_percent'    => (float) $trx->tax_percent,
    'total'          => (int) $trx->total,
    'payment_label'  => $trx->paymentLabel(),
    'paid_amount'    => (int) $trx->paid_amount,
    'payment_method' => $trx->payment_method,
    'change_amount'  => (int) $trx->change_amount,
    'qris_reference' => $trx->qris_reference ?? '',
    'is_void'        => $trx->isVoid(),
    'void_reason'    => $trx->void_reason ?? '',
    'footer'         => $s['receipt_footer'] ?? '',
    'printed_count'  => max(1, $trx->printed_count),
    'paper_width'    => $paperWidth,
    'char_width'     => $charWidth,
  ];
@endphp
<script>
const RECEIPT     = @json($receiptData);
const PRINT_URL   = @json(route('struk.print', $trx));
const CSRF        = @json(csrf_token());

// ─── Modal ────────────────────────────────────────────────────────
function bukaModalCetak() {
  document.getElementById('modal-cetak').style.display = 'flex';
  setStatus('');
}
function tutupModalCetak() {
  document.getElementById('modal-cetak').style.display = 'none';
}
function bukaTabCetak() {
  window.open(PRINT_URL, '_blank');
  tutupModalCetak();
}
function setStatus(msg, color) {
  const el = document.getElementById('bt-status');
  el.textContent = msg;
  el.style.color  = color || '#555';
}

// ─── ESC/POS helpers ──────────────────────────────────────────────
const GS = 0x1D, ESC_BYTE = 0x1B;
function esc(...b) { return new Uint8Array(b); }
const CMD = {
  init:          esc(ESC_BYTE, 0x40),
  alignLeft:     esc(ESC_BYTE, 0x61, 0x00),
  alignCenter:   esc(ESC_BYTE, 0x61, 0x01),
  boldOn:        esc(ESC_BYTE, 0x45, 0x01),
  boldOff:       esc(ESC_BYTE, 0x45, 0x00),
  dblHeightOn:   esc(ESC_BYTE, 0x21, 0x10),
  dblHeightOff:  esc(ESC_BYTE, 0x21, 0x00),
  feed:          esc(0x0A),
  cut:           esc(GS, 0x56, 0x01),
};
const enc = new TextEncoder();
function txt(s) { return enc.encode(s.normalize('NFKD').replace(/[\u0300-\u036f]/g,'')); }
function ln(s='') { return cat(txt(s), CMD.feed); }
function div(w)   { return ln('-'.repeat(w)); }
function pair(l, r, w) {
  const gap = Math.max(1, w - l.length - r.length);
  return ln(l + ' '.repeat(gap) + r);
}
function rp(n)  { return 'Rp ' + Number(n).toLocaleString('id-ID'); }
function angka(n){ return Number(n).toLocaleString('id-ID'); }
function cat(...arrays) {
  const total = arrays.reduce((s,a)=>s+a.length,0);
  const out   = new Uint8Array(total);
  let   off   = 0;
  for (const a of arrays) { out.set(a, off); off += a.length; }
  return out;
}
function buildReceipt(r) {
  const w = r.char_width;
  const c = [];
  const a = (...p) => c.push(...p);
  a(CMD.init);
  a(CMD.alignCenter, CMD.boldOn, CMD.dblHeightOn);
  a(ln(r.store_name));
  a(CMD.dblHeightOff, CMD.boldOff);
  if (r.store_address) a(ln(r.store_address));
  if (r.store_phone)   a(ln(r.store_phone));
  a(CMD.alignLeft);
  a(div(w));
  a(pair('No', r.invoice_no, w));
  a(pair('Tanggal', r.created_at, w));
  a(pair('Kasir', r.cashier, w));
  if (r.customer_name) a(pair('Pelanggan', r.customer_name, w));
  if (r.queue_no) {
    a(div(w), CMD.alignCenter, CMD.boldOn);
    a(ln('NO. ANTRIAN ' + r.queue_no));
    a(CMD.boldOff, CMD.alignLeft);
  }
  a(div(w));
  for (const item of r.items) {
    a(ln(item.name));
    a(pair('  '+item.qty+' x '+angka(item.price), angka(item.subtotal), w));
  }
  a(div(w));
  a(pair('Subtotal', angka(r.subtotal), w));
  if (r.tax_amount > 0) a(pair('Pajak '+r.tax_percent+'%', angka(r.tax_amount), w));
  a(CMD.boldOn, pair('TOTAL', rp(r.total), w), CMD.boldOff);
  a(pair(r.payment_label, angka(r.paid_amount), w));
  if (r.payment_method === 'cash') {
    a(pair('Kembali', angka(r.change_amount), w));
  } else {
    a(pair('Ref', r.qris_reference || '-', w));
  }
  if (r.is_void) {
    a(div(w), CMD.alignCenter, CMD.boldOn, ln('*** VOID ***'), CMD.boldOff, CMD.alignLeft);
    a(ln(r.void_reason));
  }
  a(div(w));
  if (r.footer) { a(CMD.alignCenter, ln(r.footer), CMD.alignLeft); }
  a(CMD.alignCenter, ln('Cetakan ke-'+r.printed_count+' \u00B7 '+r.paper_width), CMD.alignLeft);
  a(CMD.feed, CMD.feed, CMD.feed, CMD.cut);
  return cat(...c);
}

// ─── Web Bluetooth ────────────────────────────────────────────────
const BLE_SERVICES = [
  'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
  '000018f0-0000-1000-8000-00805f9b34fb',
  '49535343-fe7d-4ae5-8fa9-9fafd205e455',
];
let btDevice = null;

async function cetakBluetooth() {
  if (!navigator.bluetooth) {
    setStatus('Web Bluetooth tidak didukung. Gunakan Chrome di Android dengan HTTPS.', '#c0392b');
    return;
  }
  const btn = document.getElementById('btn-bt');
  btn.disabled = true;
  setStatus('Mencari printer Bluetooth...');
  try {
    if (!btDevice || !btDevice.gatt.connected) {
      btDevice = await navigator.bluetooth.requestDevice({
        filters: [
          { services: [BLE_SERVICES[0]] },
          { services: [BLE_SERVICES[1]] },
          { services: [BLE_SERVICES[2]] },
          { namePrefix: 'RPP' },
          { namePrefix: 'VSC' },
          { namePrefix: 'MTP' },
          { namePrefix: 'Printer' },
          { namePrefix: 'BlueTooth' },
        ],
        optionalServices: BLE_SERVICES,
      });
    }
    setStatus('Menghubungkan ke ' + btDevice.name + '...');
    const server = await btDevice.gatt.connect();
    let writeChar = null;
    for (const svcUuid of BLE_SERVICES) {
      try {
        const svc   = await server.getPrimaryService(svcUuid);
        const chars = await svc.getCharacteristics();
        for (const c of chars) {
          if (c.properties.write || c.properties.writeWithoutResponse) {
            writeChar = c; break;
          }
        }
        if (writeChar) break;
      } catch (_) {}
    }
    if (!writeChar) throw new Error('Write characteristic tidak ditemukan. Printer mungkin Classic BT, bukan BLE.');

    setStatus('Mengirim data ke printer...');

    // Catat printed_count via fetch (tanpa pindah halaman)
    fetch(PRINT_URL, {
      method: 'GET',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    }).catch(() => {});  // fire-and-forget, tidak blokir cetak

    const bytes = buildReceipt(RECEIPT);
    const CHUNK = 512;
    for (let i = 0; i < bytes.length; i += CHUNK) {
      const chunk = bytes.slice(i, i + CHUNK);
      if (writeChar.properties.writeWithoutResponse) {
        await writeChar.writeValueWithoutResponse(chunk);
      } else {
        await writeChar.writeValue(chunk);
      }
      await new Promise(r => setTimeout(r, 20));
    }
    setStatus('✓ Struk berhasil dikirim ke printer!', '#2a7a2a');
    RECEIPT.printed_count++;
  } catch (err) {
    if (err.name === 'NotFoundError') {
      setStatus('Tidak ada printer dipilih.', '#888');
    } else {
      setStatus('Gagal: ' + err.message, '#c0392b');
    }
  } finally {
    btn.disabled = false;
  }
}
</script>
@endpush
