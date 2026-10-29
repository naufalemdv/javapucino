<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Struk {{ $trx->invoice_no }}</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
<style>
  body{background:#F0F0F2;overflow:auto;padding:18px}
  @media print{ body{background:#fff;padding:0} .no-print{display:none} }
  @page{ margin:4mm }
  #bt-status{font-size:12px;color:#666;margin-top:8px;text-align:center;max-width:302px;margin-left:auto;margin-right:auto}
</style>
</head>
<body>
  @include('receipt._body')
  <div class="no-print" style="max-width:302px;margin:16px auto 0;display:flex;gap:8px;flex-wrap:wrap">
    <button class="btn btn-line" style="flex:1" onclick="window.close()">Tutup</button>
    <button class="btn btn-black" style="flex:1.3" id="bt-btn" onclick="cetakBluetooth()">🖨️ Cetak (BT)</button>
    <button class="btn btn-line" style="flex:1.3;font-size:12px" onclick="window.print()">Cetak (Browser)</button>
  </div>
  <div id="bt-status"></div>

@php
  $s   = $appSettings;
  $trx = $trx;
  $paperWidth = $s['paper_width'] ?? '58mm';
  $charWidth  = $paperWidth === '80mm' ? 48 : 32;

  // Susun data struk untuk dikirim ke JS
  $receiptData = [
    'store_name'    => $s['store_name']    ?? 'Javapucino',
    'store_address' => $s['store_address'] ?? '',
    'store_phone'   => $s['store_phone']   ?? '',
    'invoice_no'    => $trx->invoice_no,
    'created_at'    => $trx->created_at->format('d/m/Y H:i'),
    'cashier'       => $trx->cashier?->name ?? '',
    'customer_name' => $trx->customer_name ?? '',
    'queue_no'      => $trx->queue_no ? $trx->queueLabel() : null,
    'items'         => $trx->items->map(fn($i) => [
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
const RECEIPT = @json($receiptData);

// ─── ESC/POS helpers ──────────────────────────────────────────────
const ESC = 0x1B, GS = 0x1D;

function esc(...bytes) { return new Uint8Array(bytes); }

const CMD = {
  init:       esc(ESC, 0x40),
  alignLeft:  esc(ESC, 0x61, 0x00),
  alignCenter:esc(ESC, 0x61, 0x01),
  boldOn:     esc(ESC, 0x45, 0x01),
  boldOff:    esc(ESC, 0x45, 0x00),
  dblHeightOn: esc(ESC, 0x21, 0x10),
  dblHeightOff:esc(ESC, 0x21, 0x00),
  feed:       esc(0x0A),
  cut:        esc(GS,  0x56, 0x01),   // partial cut
};

const enc = new TextEncoder();

function text(str) {
  // Ganti karakter non-ASCII yang umum
  const clean = str
    .replace(/[Rp]/g, m => m)
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '');  // strip diacritics
  return enc.encode(clean);
}

function line(str = '') { return concat(text(str), CMD.feed); }

function divider(w) {
  return line('-'.repeat(w));
}

function linePair(left, right, w) {
  const gap   = Math.max(1, w - left.length - right.length);
  return line(left + ' '.repeat(gap) + right);
}

function rupiah(n) {
  return 'Rp ' + Number(n).toLocaleString('id-ID');
}

function angka(n) {
  return Number(n).toLocaleString('id-ID');
}

function concat(...arrays) {
  const total = arrays.reduce((s, a) => s + a.length, 0);
  const out   = new Uint8Array(total);
  let   offset = 0;
  for (const a of arrays) { out.set(a, offset); offset += a.length; }
  return out;
}

// ─── Susun ESC/POS bytes ──────────────────────────────────────────
function buildReceipt(r) {
  const w   = r.char_width;   // 32 (58mm) atau 48 (80mm)
  const chunks = [];

  const add = (...parts) => chunks.push(...parts);

  add(CMD.init);

  // Header toko
  add(CMD.alignCenter, CMD.boldOn, CMD.dblHeightOn);
  add(line(r.store_name));
  add(CMD.dblHeightOff, CMD.boldOff);
  if (r.store_address) add(line(r.store_address));
  if (r.store_phone)   add(line(r.store_phone));
  add(CMD.alignLeft);

  add(divider(w));

  add(linePair('No', r.invoice_no, w));
  add(linePair('Tanggal', r.created_at, w));
  add(linePair('Kasir', r.cashier, w));
  if (r.customer_name) add(linePair('Pelanggan', r.customer_name, w));

  if (r.queue_no) {
    add(divider(w));
    add(CMD.alignCenter, CMD.boldOn);
    add(line('NO. ANTRIAN ' + r.queue_no));
    add(CMD.boldOff, CMD.alignLeft);
  }

  add(divider(w));

  // Item
  for (const item of r.items) {
    add(line(item.name));
    const qtyPrice = '  ' + item.qty + ' x ' + angka(item.price);
    add(linePair(qtyPrice, angka(item.subtotal), w));
  }

  add(divider(w));

  add(linePair('Subtotal', angka(r.subtotal), w));
  if (r.tax_amount > 0) {
    add(linePair('Pajak ' + r.tax_percent + '%', angka(r.tax_amount), w));
  }
  add(CMD.boldOn);
  add(linePair('TOTAL', rupiah(r.total), w));
  add(CMD.boldOff);
  add(linePair(r.payment_label, angka(r.paid_amount), w));
  if (r.payment_method === 'cash') {
    add(linePair('Kembali', angka(r.change_amount), w));
  } else {
    add(linePair('Ref', r.qris_reference || '-', w));
  }

  if (r.is_void) {
    add(divider(w));
    add(CMD.alignCenter, CMD.boldOn);
    add(line('*** VOID ***'));
    add(CMD.boldOff, CMD.alignLeft);
    add(line(r.void_reason));
  }

  add(divider(w));

  if (r.footer) {
    add(CMD.alignCenter);
    add(line(r.footer));
    add(CMD.alignLeft);
  }
  add(CMD.alignCenter);
  add(line('Cetakan ke-' + r.printed_count + ' \u00B7 ' + r.paper_width));
  add(CMD.alignLeft);

  // Feed & cut
  add(CMD.feed, CMD.feed, CMD.feed, CMD.cut);

  return concat(...chunks);
}

// ─── Web Bluetooth ────────────────────────────────────────────────
// RPP02 / VSC pakai Serial Port Profile (SPP) di atas RFCOMM
// Service UUID standar SPP
const SPP_SERVICE = '00001101-0000-1000-8000-00805f9b34fb';

// Beberapa printer thermal BLE expose custom service ini
const BLE_PRINT_SERVICES = [
  '000018f0-0000-1000-8000-00805f9b34fb',  // common thermal BLE
  'e7810a71-73ae-499d-8c15-faa9aef0c3f2',  // RPP series
  '49535343-fe7d-4ae5-8fa9-9fafd205e455',  // Zonerich / generic
];

// Karakteristik write yang umum
const BLE_WRITE_CHARS = [
  'bef8d6c9-9c21-4c9e-b632-bd58c1009f9f',  // RPP02 write char
  '000018f0-0000-1000-8000-00805f9b34fb',
  '49535343-8841-43f4-a8d4-ecbe34729bb3',  // Zonerich write
  '49535343-1e4d-4bd9-ba61-23c647249616',
];

let btDevice = null;
let btChar   = null;

function setStatus(msg, color) {
  const el = document.getElementById('bt-status');
  if (el) { el.textContent = msg; el.style.color = color || '#666'; }
}

async function cetakBluetooth() {
  if (!navigator.bluetooth) {
    alert('Web Bluetooth tidak didukung di browser ini. Pastikan Chrome di Android dan akses via HTTPS.');
    return;
  }

  setStatus('Mencari printer...', '#555');
  const btn = document.getElementById('bt-btn');
  if (btn) btn.disabled = true;

  try {
    // Coba koneksi ulang jika sudah punya device
    if (!btDevice || !btDevice.gatt.connected) {
      btDevice = await navigator.bluetooth.requestDevice({
        filters: [
          { services: [BLE_PRINT_SERVICES[0]] },
          { services: [BLE_PRINT_SERVICES[1]] },
          { services: [BLE_PRINT_SERVICES[2]] },
          { namePrefix: 'RPP' },
          { namePrefix: 'VSC' },
          { namePrefix: 'Printer' },
          { namePrefix: 'MTP' },
          { namePrefix: 'BlueTooth' },
        ],
        optionalServices: [...BLE_PRINT_SERVICES],
      });
    }

    setStatus('Menghubungkan ke ' + btDevice.name + '...', '#555');
    const server = await btDevice.gatt.connect();

    // Coba setiap service sampai ketemu yang punya write characteristic
    let writeChar = null;
    for (const svcUuid of BLE_PRINT_SERVICES) {
      try {
        const service = await server.getPrimaryService(svcUuid);
        for (const charUuid of BLE_WRITE_CHARS) {
          try {
            const c = await service.getCharacteristic(charUuid);
            if (c.properties.write || c.properties.writeWithoutResponse) {
              writeChar = c;
              break;
            }
          } catch (_) { /* char tidak ada, lanjut */ }
        }
        // Kalau tidak ketemu di UUID yg dikenal, ambil semua characteristic dan cari yg bisa write
        if (!writeChar) {
          const chars = await service.getCharacteristics();
          for (const c of chars) {
            if (c.properties.write || c.properties.writeWithoutResponse) {
              writeChar = c;
              break;
            }
          }
        }
        if (writeChar) break;
      } catch (_) { /* service tidak ada */ }
    }

    if (!writeChar) {
      throw new Error('Tidak ditemukan write characteristic. Printer mungkin pakai SPP classic Bluetooth, bukan BLE.');
    }

    btChar = writeChar;
    setStatus('Terhubung. Mengirim data cetak...', '#2a7a2a');

    const bytes = buildReceipt(RECEIPT);

    // Kirim dalam chunk 512 bytes (batas MTU BLE)
    const CHUNK = 512;
    for (let i = 0; i < bytes.length; i += CHUNK) {
      const chunk = bytes.slice(i, i + CHUNK);
      if (writeChar.properties.writeWithoutResponse) {
        await writeChar.writeValueWithoutResponse(chunk);
      } else {
        await writeChar.writeValue(chunk);
      }
      // Jeda kecil antar chunk agar printer tidak overflow
      await new Promise(r => setTimeout(r, 20));
    }

    setStatus('Struk berhasil dikirim ke printer!', '#2a7a2a');
  } catch (err) {
    console.error(err);
    if (err.name === 'NotFoundError') {
      setStatus('Tidak ada printer dipilih.', '#888');
    } else {
      setStatus('Gagal: ' + err.message, '#c0392b');
    }
  } finally {
    if (btn) btn.disabled = false;
  }
}

// Auto print via Bluetooth tidak jalan otomatis (butuh gesture user)
// Hanya auto-print browser dialog jika tidak ada Web Bluetooth
if (!navigator.bluetooth) {
  window.addEventListener('load', () => setTimeout(() => window.print(), 350));
}
</script>
</body>
</html>
