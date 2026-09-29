@extends('layouts.app')
@php $isNew = ! $material->exists; @endphp
@section('title', $isNew ? 'Tambah bahan' : 'Ubah bahan')

@section('content')
  @include('partials.topbar', ['title' => $isNew ? 'Tambah bahan' : 'Ubah bahan'])

  <div class="page">
    <form method="POST" class="form-grid"
          action="{{ $isNew ? route('admin.materials.store') : route('admin.materials.update', $material) }}">
      @csrf
      @unless($isNew) @method('PUT') @endunless

      @if($errors->any())<div class="alert alert-err">{{ $errors->first() }}</div>@endif

      <div class="card"><div class="card-b">
        <div class="field">
          <label>Nama bahan</label>
          <input class="inp" name="name" value="{{ old('name', $material->name) }}" placeholder="Susu UHT Full Cream" required>
          @error('name')<div class="err-text">{{ $message }}</div>@enderror
        </div>

        <div class="row2">
          <div class="field">
            <label>Satuan</label>
            <select class="inp" name="unit">
              @foreach(config('javapucino.units') as $u)
                <option value="{{ $u }}" @selected(old('unit', $material->unit) === $u)>{{ $u }}</option>
              @endforeach
            </select>
          </div>
          <div class="field">
            <label>Stok saat ini</label>
            <input class="inp num" name="stock" inputmode="decimal" value="{{ old('stock', (float) $material->stock) }}" required>
          </div>
        </div>

        <div class="row2">
          <div class="field">
            <label>Batas minimum</label>
            <input class="inp num" name="min_stock" inputmode="decimal" value="{{ old('min_stock', (float) $material->min_stock) }}" required>
            @error('min_stock')<div class="err-text">{{ $message }}</div>@enderror
            <div class="hint">Status berubah jadi “Menipis” bila stok di bawah angka ini (BR-13).</div>
          </div>
          <div class="field">
            <label>Harga satuan (opsional)</label>
            <input class="inp num" name="unit_cost" inputmode="numeric" placeholder="0"
                   value="{{ old('unit_cost', $material->unit_cost ? (int) $material->unit_cost : '') }}">
            @error('unit_cost')<div class="err-text">{{ $message }}</div>@enderror
            <div class="hint">
              @if($isNew)
                Bila diisi bersama stok awal, nilainya tercatat sebagai pembelian di menu Pengeluaran.
              @else
                Harga beli terakhir, dipakai sebagai nilai awal pada form Restock.
              @endif
            </div>
          </div>
        </div>

        @unless($isNew)
          <div class="hint">
            Nilai persediaan saat ini:
            <b>{{ rupiah($material->stockValue()) }}</b>
            ({{ angka($material->stock) }} {{ $material->unit }} × {{ rupiah($material->unit_cost) }})
          </div>
        @endunless

        <div style="display:flex;gap:9px">
          <a class="btn btn-line" style="flex:1" href="{{ route('admin.materials.index') }}">Batal</a>
          <button class="btn btn-red" style="flex:1.4" type="submit">Simpan</button>
        </div>
      </div></div>
    </form>
  </div>
@endsection