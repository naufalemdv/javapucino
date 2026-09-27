@extends('layouts.app')
@php $isNew = ! $product->exists; @endphp
@section('title', $isNew ? 'Tambah menu' : 'Ubah menu')

@section('content')
  @include('partials.topbar', [
    'title' => $isNew ? 'Tambah menu' : 'Ubah menu',
    'sub'   => $isNew ? 'Menu baru langsung tampil di kasir & menu board' : $product->name.' · '.($product->sku ?: '—'),
  ])

  <div class="page">
    <form method="POST" class="form-grid" enctype="multipart/form-data"
          action="{{ $isNew ? route('admin.products.store') : route('admin.products.update', $product) }}">
      @csrf
      @unless($isNew) @method('PUT') @endunless

      @if($errors->any())<div class="alert alert-err">{{ $errors->first() }}</div>@endif

      <div class="card"><div class="card-b">
        <div class="field">
          <label>Nama menu</label>
          <input class="inp {{ $errors->has('name') ? 'is-err' : '' }}" name="name"
                 value="{{ old('name', $product->name) }}" placeholder="Kopi Susu Gula Aren" required>
          @error('name')<div class="err-text">{{ $message }}</div>@enderror
        </div>

        <div class="row2">
          <div class="field">
            <label>SKU</label>
            <input class="inp" name="sku" value="{{ old('sku', $product->sku) }}" placeholder="KS-05">
            @error('sku')<div class="err-text">{{ $message }}</div>@enderror
          </div>
          <div class="field">
            <label>Kategori</label>
            <select class="inp" name="category_id" required>
              @foreach($categories as $c)
                <option value="{{ $c->id }}" @selected(old('category_id', $product->category_id) == $c->id)>{{ $c->name }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="row2">
          <div class="field">
            <label>Harga jual</label>
            <input class="inp num" name="price" inputmode="numeric" value="{{ old('price', (int) $product->price) }}" required>
            @error('price')<div class="err-text">{{ $message }}</div>@enderror
          </div>
          <div class="field">
            <label>HPP (modal)</label>
            <input class="inp num" name="hpp" inputmode="numeric" value="{{ old('hpp', (int) $product->hpp) }}" required>
            @error('hpp')<div class="err-text">{{ $message }}</div>@enderror
          </div>
        </div>

        <div class="row2">
          <div class="field">
            <label>Stok tersedia</label>
            <input class="inp num" name="stock" inputmode="numeric" value="{{ old('stock', (int) $product->stock) }}" required>
            <div class="hint">Perubahan stok otomatis tercatat di kartu stok (BR-12).</div>
          </div>
          <div class="field">
            <label>Status</label>
            <select class="inp" name="is_active">
              <option value="1" @selected(old('is_active', (int) $product->is_active) == 1)>Aktif</option>
              <option value="0" @selected(old('is_active', (int) $product->is_active) == 0)>Nonaktif</option>
            </select>
          </div>
        </div>

        <label class="field" style="display:flex;align-items:center;gap:9px;cursor:pointer">
          <input type="checkbox" name="is_best_seller" value="1" @checked(old('is_best_seller', $product->is_best_seller))
                 style="width:17px;height:17px;accent-color:#E31E24">
          <span style="font-weight:700;font-size:13.5px">Tandai sebagai Best Seller</span>
        </label>

        <div class="field">
          <label>Gambar menu</label>
          <div class="img-pick">
            <div class="img-prev" id="imgPrev">
              @if($product->imageUrl())
                <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}">
              @else
                <span>Belum ada gambar</span>
              @endif
            </div>
            <div style="flex:1;min-width:0">
              <input class="inp {{ $errors->has('image') ? 'is-err' : '' }}" type="file" name="image" id="imgInput"
                     accept="image/jpeg,image/png,image/webp">
              <div class="hint">Format JPG, PNG, atau WEBP. Maksimal 2 MB. Disarankan rasio 1:1.</div>
              @error('image')<div class="err-text">{{ $message }}</div>@enderror
              @if($product->image_path)
                <label style="display:flex;align-items:center;gap:7px;margin-top:8px;cursor:pointer;font-size:12.5px;font-weight:700">
                  <input type="checkbox" name="remove_image" value="1" style="accent-color:#E31E24"> Hapus gambar saat ini
                </label>
              @endif
            </div>
          </div>
        </div>

        <div style="display:flex;gap:9px;margin-top:8px">
          <a class="btn btn-line" style="flex:1" href="{{ route('admin.products.index') }}">Batal</a>
          <button class="btn btn-red" style="flex:1.4" type="submit">{{ $isNew ? 'Simpan menu' : 'Simpan perubahan' }}</button>
        </div>
      </div></div>
    </form>
  </div>
@endsection

@push('scripts')
<script>
document.getElementById('imgInput').addEventListener('change', e => {
  const f = e.target.files[0];
  if (!f) return;
  const img = document.createElement('img');
  img.src = URL.createObjectURL(f);
  document.getElementById('imgPrev').replaceChildren(img);
});
</script>
@endpush
