@extends('layouts.app')
@section('title', 'Stok Bahan')

@php $lowN = $materials->filter(fn($m) => $m->isLow())->count(); @endphp

@section('content')
  @include('partials.topbar', [
    'title' => 'Stok Bahan',
    'sub'   => $materials->count().' bahan · '.$lowN.' menipis',
    'right' => '<a class="btn btn-red btn-sm" href="'.route('admin.materials.create').'">+ Tambah bahan</a>',
  ])

  <div class="page">
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Bahan</th><th>Satuan</th><th class="r">Stok saat ini</th><th class="r">Minimum</th><th>Status</th><th class="r">Aksi</th></tr></thead>
        <tbody>
        @foreach($materials as $m)
          <tr>
            <td><b>{{ $m->name }}</b></td>
            <td class="mini" style="font-weight:700">{{ $m->unit }}</td>
            <td class="r num" style="font-weight:800">{{ angka($m->stock) }}</td>
            <td class="r num" style="color:var(--ink-3)">{{ angka($m->min_stock) }}</td>
            <td>{!! $m->isLow() ? '<span class="tag t-amber">Menipis</span>' : '<span class="tag t-green">Aman</span>' !!}</td>
            <td class="r" style="white-space:nowrap">
              <button class="btn btn-line btn-sm" type="button"
                      data-restock="{{ $m->id }}" data-name="{{ $m->name }}"
                      data-unit="{{ $m->unit }}" data-stock="{{ angka($m->stock) }}">Restock</button>
              <a class="btn btn-line btn-sm" href="{{ route('admin.materials.edit', $m) }}">Ubah</a>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>

    <div class="sec-title">Kartu stok terakhir</div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Waktu</th><th>Item</th><th>Jenis</th><th class="r">Qty</th><th class="r">Sebelum</th><th class="r">Sesudah</th><th>Catatan</th></tr></thead>
        <tbody>
        @forelse($movements as $mv)
          <tr>
            <td class="num mini">{{ $mv->created_at->format('d/m/Y H:i') }}</td>
            <td><b>{{ $mv->itemName() }}</b></td>
            <td><span class="tag {{ $mv->type === 'sale' ? 't-gray' : ($mv->type === 'void_return' ? 't-red' : 't-green') }}">{{ $mv->typeLabel() }}</span></td>
            <td class="r num" style="font-weight:800;color:{{ (float) $mv->qty < 0 ? 'var(--red)' : 'var(--green)' }}">
              {{ (float) $mv->qty > 0 ? '+' : '' }}{{ angka($mv->qty) }}
            </td>
            <td class="r num">{{ angka($mv->stock_before) }}</td>
            <td class="r num">{{ angka($mv->stock_after) }}</td>
            <td class="mini">{{ $mv->note ?: '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="7"><div class="empty"><b>Belum ada pergerakan stok</b>Setiap penjualan, void, dan restock akan tercatat di sini.</div></td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Form restock (dikirim lewat modal) --}}
  <form method="POST" id="restockForm" action="" style="display:none">
    @csrf
    <input type="hidden" name="qty"  id="rQty">
    <input type="hidden" name="note" id="rNote">
  </form>
@endsection

@push('scripts')
<script>
const base = @json(url('admin/materials'));

document.querySelectorAll('[data-restock]').forEach(btn => btn.addEventListener('click', () => {
  const id = btn.dataset.restock;
  openModal(`
    <div class="modal-h">
      <div><h3>Restock bahan</h3>
        <div class="sub">${btn.dataset.name} · stok kini ${btn.dataset.stock} ${btn.dataset.unit}</div></div>
      <button class="x" type="button" data-act="close">✕</button>
    </div>
    <div class="modal-b">
      <div class="field"><label>Jumlah masuk (${btn.dataset.unit})</label>
        <input class="inp num" id="mAdd" inputmode="numeric" value="0" style="height:52px;font-size:19px;font-weight:800"></div>
      <div class="field"><label>Catatan</label>
        <input class="inp" id="mNote" placeholder="Kiriman supplier, nota #…"></div>
    </div>
    <div class="modal-f">
      <button class="btn btn-line" type="button" data-act="close">Batal</button>
      <button class="btn btn-red" type="button" id="mSave" style="flex:1.4">Tambah stok</button>
    </div>`);

  document.getElementById('mAdd').focus();
  document.getElementById('mSave').addEventListener('click', () => {
    const qty = Number(String(document.getElementById('mAdd').value).replace(/[^\d.]/g, '') || 0);
    if (qty <= 0) return toast('Jumlah masuk harus lebih dari 0');
    const form = document.getElementById('restockForm');
    form.action = base + '/' + id + '/restock';
    document.getElementById('rQty').value  = qty;
    document.getElementById('rNote').value = document.getElementById('mNote').value;
    form.submit();
  });
}));
</script>
@endpush
