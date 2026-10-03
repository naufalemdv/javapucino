@extends('layouts.app')
@section('title', 'Stok Bahan')

@php $lowN = $materials->filter(fn($m) => $m->isLow())->count(); @endphp

@section('content')
  @include('partials.topbar', [
    'title' => 'Stok Bahan',
    'sub'   => $materials->count().' bahan - '.$lowN.' menipis',
    'right' => '<a class="btn btn-red btn-sm" href="'.route('admin.materials.create').'">+ Tambah bahan</a>',
  ])

  <div class="page">
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Bahan</th><th>Satuan</th><th class="r">Stok saat ini</th><th class="r">Minimum</th><th class="r">Harga terakhir</th><th>Status</th><th class="r">Aksi</th></tr></thead>
        <tbody>
        @foreach($materials as $m)
          <tr>
            <td><b>{{ $m->name }}</b></td>
            <td class="mini" style="font-weight:700">{{ $m->unit }}</td>
            <td class="r num" style="font-weight:800">{{ angka($m->stock) }}</td>
            <td class="r num" style="color:var(--ink-3)">{{ angka($m->min_stock) }}</td>
            <td class="r num" style="color:var(--ink-3)">{{ $m->unit_cost ? rupiah($m->unit_cost) : '-' }}</td>
            <td>{!! $m->isLow() ? '<span class="tag t-amber">Menipis</span>' : '<span class="tag t-green">Aman</span>' !!}</td>
            <td class="r" style="white-space:nowrap">
              <button class="btn btn-line btn-sm" type="button"
                      data-restock="{{ $m->id }}" data-name="{{ $m->name }}"
                      data-unit="{{ $m->unit }}" data-stock="{{ angka($m->stock) }}"
                      data-cost="{{ $m->unit_cost ? (int) $m->unit_cost : '' }}">Restock</button>
              <a class="btn btn-line btn-sm" href="{{ route('admin.materials.edit', $m) }}">Ubah</a>
              <form method="POST" action="{{ route('admin.materials.destroy', $m) }}" style="display:inline"
                    data-confirm="Hapus bahan &quot;{{ $m->name }}&quot;? Riwayat stok tetap tersimpan.">
                @csrf @method('DELETE')
                <button class="btn btn-danger-line btn-sm" type="submit">Hapus</button>
              </form>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>

    <div class="sec-title">Kartu stok terakhir</div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Waktu</th><th>Item</th><th>Jenis</th><th class="r">Qty</th><th class="r">Harga satuan</th><th class="r">Sebelum</th><th class="r">Sesudah</th><th>Catatan</th></tr></thead>
        <tbody>
        @forelse($movements as $mv)
          <tr>
            <td class="num mini">{{ $mv->created_at->format('d/m/Y H:i') }}</td>
            <td><b>{{ $mv->itemName() }}</b></td>
            <td><span class="tag {{ $mv->type === 'sale' ? 't-gray' : ($mv->type === 'void_return' ? 't-red' : 't-green') }}">{{ $mv->typeLabel() }}</span></td>
            <td class="r num" style="font-weight:800;color:{{ (float) $mv->qty < 0 ? 'var(--red)' : 'var(--green)' }}">
              {{ (float) $mv->qty > 0 ? '+' : '' }}{{ angka($mv->qty) }}
            </td>
            <td class="r num" style="color:var(--ink-3)">{{ $mv->unit_cost ? rupiah($mv->unit_cost) : '-' }}</td>
            <td class="r num">{{ angka($mv->stock_before) }}</td>
            <td class="r num">{{ angka($mv->stock_after) }}</td>
            <td class="mini">{{ $mv->note ?: '-' }}</td>
          </tr>
        @empty
          <tr><td colspan="8"><div class="empty"><b>Belum ada pergerakan stok</b>Setiap penjualan, void, dan restock akan tercatat di sini.</div></td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Form restock (dikirim lewat modal) --}}
  <form method="POST" id="restockForm" action="" style="display:none">
    @csrf
    <input type="hidden" name="qty"       id="rQty">
    <input type="hidden" name="unit_cost" id="rCost">
    <input type="hidden" name="note"      id="rNote">
  </form>
@endsection

@push('scripts')
<script>
const base = @json(url('admin/materials'));

const num = v => Number(String(v).replace(/[^\d.]/g, '') || 0);
const rp  = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');

document.querySelectorAll('[data-restock]').forEach(btn => btn.addEventListener('click', () => {
  const id = btn.dataset.restock;
  openModal(`
    <div class="modal-h">
      <div><h3>Restock bahan</h3>
        <div class="sub">${btn.dataset.name} &middot; stok kini ${btn.dataset.stock} ${btn.dataset.unit}</div></div>
      <button class="x" type="button" data-act="close">&times;</button>
    </div>
    <div class="modal-b">
      <div class="row2">
        <div class="field"><label>Jumlah masuk (${btn.dataset.unit})</label>
          <input class="inp num" id="mAdd" inputmode="numeric" value="0" style="height:52px;font-size:19px;font-weight:800"></div>
        <div class="field"><label>Harga per ${btn.dataset.unit} (Rp)</label>
          <input class="inp num" id="mCost" inputmode="numeric" value="${btn.dataset.cost}" placeholder="0" style="height:52px;font-size:19px;font-weight:800"></div>
      </div>
      <div class="hint" id="mTotal">Total pembelian <b>Rp 0</b> &mdash; akan tercatat otomatis di menu Pengeluaran.</div>
      <div class="field"><label>Catatan</label>
        <input class="inp" id="mNote" placeholder="Kiriman supplier, nota #..."></div>
    </div>
    <div class="modal-f">
      <button class="btn btn-line" type="button" data-act="close">Batal</button>
      <button class="btn btn-red" type="button" id="mSave" style="flex:1.4">Tambah stok</button>
    </div>`);

  const add   = document.getElementById('mAdd');
  const cost  = document.getElementById('mCost');
  const total = document.getElementById('mTotal');

  const refresh = () => {
    total.innerHTML = 'Total pembelian <b>' + rp(num(add.value) * num(cost.value))
                    + '</b> &mdash; akan tercatat otomatis di menu Pengeluaran.';
  };
  add.addEventListener('input', refresh);
  cost.addEventListener('input', refresh);
  refresh();
  add.focus();

  document.getElementById('mSave').addEventListener('click', () => {
    const qty = num(add.value);
    if (qty <= 0) return toast('Jumlah masuk harus lebih dari 0');
    if (cost.value.trim() === '') return toast('Harga satuan wajib diisi');

    const form = document.getElementById('restockForm');
    form.action = base + '/' + id + '/restock';
    document.getElementById('rQty').value  = qty;
    document.getElementById('rCost').value = num(cost.value);
    document.getElementById('rNote').value = document.getElementById('mNote').value;
    form.submit();
  });
}));
</script>