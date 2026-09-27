@extends('layouts.app')
@section('title', 'Kategori')

@section('content')
  @include('partials.topbar', [
    'title' => 'Kategori',
    'sub'   => $categories->count().' kategori menu',
    'right' => '<a class="btn btn-red btn-sm" href="'.route('admin.categories.create').'">+ Tambah kategori</a>',
  ])

  <div class="page">
    <div class="tbl-wrap">
      <table>
        <thead><tr><th style="width:64px">Urutan</th><th>Kategori</th><th class="r">Jumlah menu</th><th>Status</th><th class="r">Aksi</th></tr></thead>
        <tbody>
        @foreach($categories as $c)
          <tr>
            <td class="num" style="color:var(--ink-4)">{{ $c->sort_order }}</td>
            <td><div style="display:flex;align-items:center;gap:10px">
              <span style="font-size:20px">{{ $c->icon }}</span><b>{{ $c->name }}</b></div></td>
            <td class="r num">{{ $c->products_count }}</td>
            <td>{!! $c->is_active ? '<span class="tag t-green">Aktif</span>' : '<span class="tag t-gray">Nonaktif</span>' !!}</td>
            <td class="r" style="white-space:nowrap">
              <a class="btn btn-line btn-sm" href="{{ route('admin.categories.edit', $c) }}">Ubah</a>
              <form method="POST" action="{{ route('admin.categories.destroy', $c) }}" style="display:inline"
                    data-confirm="Hapus kategori &quot;{{ $c->name }}&quot;?">
                @csrf @method('DELETE')
                <button class="btn btn-danger-line btn-sm" type="submit">Hapus</button>
              </form>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
    <p class="mini" style="margin-top:12px">BR-10 · Kategori yang masih memiliki menu aktif tidak dapat dihapus.</p>
  </div>
@endsection
