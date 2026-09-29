@extends('layouts.app')
@section('title', 'Kategori Pengeluaran')

@section('content')
@include('partials.topbar', [
    'title' => 'Kategori Pengeluaran',
    'sub'   => 'Kategori operasional mengurangi laba kotor · kategori stok tidak',
    'right' => '<a class="btn btn-line btn-sm" href="'.e(route('admin.expenses.index')).'">← Kembali</a>',
])

<div class="page">

    @if($errors->any())
        <div class="alert alert-err">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="card">
        <div class="card-h"><h3>Tambah kategori</h3></div>
        <div class="card-b">
            <form method="POST" action="{{ route('admin.expenses.categories.store') }}" class="toolbar">
                @csrf
                <input class="inp" type="text" name="name" required maxlength="60" placeholder="Nama kategori baru">
                <input class="inp" type="number" name="sort_order" min="0" max="999" value="60" style="max-width:110px" placeholder="Urutan">
                <button class="btn btn-red btn-sm" type="submit">Tambah</button>
            </form>
        </div>
    </div>

    <div class="sec-title">Daftar kategori</div>
    <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nama</th><th>Jenis</th><th class="r">Urutan</th>
                    <th class="r">Dipakai</th><th>Status</th><th class="r"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($categories as $c)
                    <tr>
                        <td>
                            <form method="POST" action="{{ route('admin.expenses.categories.update', $c) }}" style="display:flex;gap:8px;align-items:center">
                                @csrf @method('PUT')
                                <input class="inp" type="text" name="name" value="{{ $c->name }}" required maxlength="60">
                                <input type="hidden" name="sort_order" value="{{ $c->sort_order }}">
                                <input type="hidden" name="is_active" value="{{ $c->is_active ? 1 : 0 }}">
                                <button class="btn btn-line btn-sm" type="submit">Simpan</button>
                            </form>
                        </td>
                        <td>{{ $c->typeLabel() }}</td>
                        <td class="r num">{{ $c->sort_order }}</td>
                        <td class="r num">{{ $c->expenses_count }}</td>
                        <td>{{ $c->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                        <td class="r">
                            @if($c->is_locked)
                                <small>bawaan sistem</small>
                            @else
                                <form method="POST" action="{{ route('admin.expenses.categories.destroy', $c) }}"
                                      onsubmit="return confirm('Hapus kategori ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-line btn-sm" type="submit">Hapus</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>
@endsection