@extends('layouts.app')
@php $isNew = ! $category->exists; @endphp
@section('title', $isNew ? 'Tambah kategori' : 'Ubah kategori')

@section('content')
  @include('partials.topbar', ['title' => $isNew ? 'Tambah kategori' : 'Ubah kategori'])

  <div class="page">
    <form method="POST" class="form-grid"
          action="{{ $isNew ? route('admin.categories.store') : route('admin.categories.update', $category) }}">
      @csrf
      @unless($isNew) @method('PUT') @endunless

      @if($errors->any())<div class="alert alert-err">{{ $errors->first() }}</div>@endif

      <div class="card"><div class="card-b">
        <div class="field">
          <label>Ikon</label>
          <input type="hidden" name="icon" id="iconVal" value="{{ old('icon', $category->icon ?: '☕') }}">
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            @foreach(config('javapucino.icons.category') as $ic)
              <button type="button" class="chip" data-icon="{{ $ic }}"
                      style="width:42px;padding:0;justify-content:center;font-size:18px">{{ $ic }}</button>
            @endforeach
          </div>
        </div>

        <div class="field">
          <label>Nama kategori</label>
          <input class="inp {{ $errors->has('name') ? 'is-err' : '' }}" name="name"
                 value="{{ old('name', $category->name) }}" placeholder="Kopi Susu" required>
          @error('name')<div class="err-text">{{ $message }}</div>@enderror
        </div>

        <div class="row2">
          <div class="field">
            <label>Urutan tampil</label>
            <input class="inp num" name="sort_order" inputmode="numeric" value="{{ old('sort_order', $category->sort_order) }}" required>
          </div>
          <div class="field">
            <label>Status</label>
            <select class="inp" name="is_active">
              <option value="1" @selected(old('is_active', (int) $category->is_active) == 1)>Aktif</option>
              <option value="0" @selected(old('is_active', (int) $category->is_active) == 0)>Nonaktif</option>
            </select>
          </div>
        </div>

        <div style="display:flex;gap:9px">
          <a class="btn btn-line" style="flex:1" href="{{ route('admin.categories.index') }}">Batal</a>
          <button class="btn btn-red" style="flex:1.4" type="submit">Simpan</button>
        </div>
      </div></div>
    </form>
  </div>
@endsection

@push('scripts')
<script>
const iconVal = document.getElementById('iconVal');
const paint = () => document.querySelectorAll('[data-icon]').forEach(b =>
  b.classList.toggle('on', b.dataset.icon === iconVal.value));
document.querySelectorAll('[data-icon]').forEach(b => b.addEventListener('click', () => {
  iconVal.value = b.dataset.icon; paint();
}));
paint();
</script>
@endpush
