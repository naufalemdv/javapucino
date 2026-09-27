@extends('layouts.app')
@section('title', 'Pengguna')

@section('content')
  @include('partials.topbar', [
    'title' => 'Pengguna',
    'sub'   => $users->count().' akun terdaftar',
    'right' => '<a class="btn btn-red btn-sm" href="'.route('admin.users.create').'">+ Tambah pengguna</a>',
  ])

  <div class="page">
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Nama</th><th>Username</th><th>Peran</th><th>Login terakhir</th><th>Status</th><th class="r">Aksi</th></tr></thead>
        <tbody>
        @foreach($users as $u)
          <tr>
            <td><div style="display:flex;align-items:center;gap:10px">
              <span class="avatar" style="background:{{ $u->isAdmin() ? '#E31E24' : '#B0141A' }};color:#fff">{{ $u->initial() }}</span>
              <b>{{ $u->name }}</b></div></td>
            <td class="mini" style="font-weight:700">&#64;{{ $u->username }}</td>
            <td><span class="tag {{ $u->isAdmin() ? 't-red' : 't-gray' }}">{{ $u->roleLabel() }}</span></td>
            <td class="num mini">{{ $u->last_login_at?->format('d/m/Y H:i') ?? '—' }}</td>
            <td>{!! $u->is_active ? '<span class="tag t-green">Aktif</span>' : '<span class="tag t-gray">Nonaktif</span>' !!}</td>
            <td class="r" style="white-space:nowrap">
              <a class="btn btn-line btn-sm" href="{{ route('admin.users.edit', $u) }}">Ubah</a>
              <form method="POST" action="{{ route('admin.users.toggle', $u) }}" style="display:inline">
                @csrf
                <button class="btn btn-line btn-sm" type="submit">{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
              </form>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
    <p class="mini" style="margin-top:12px">BR-14 · Pengguna nonaktif tidak dapat login, dan admin tidak dapat menonaktifkan akunnya sendiri.</p>
  </div>
@endsection
