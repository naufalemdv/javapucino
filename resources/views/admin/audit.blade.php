@extends('layouts.app')
@section('title', 'Audit Log')

@section('content')
  @include('partials.topbar', [
    'title' => 'Audit Log',
    'sub'   => $total.' aktivitas tercatat · read-only',
  ])

  <div class="page">
    <form class="toolbar" method="GET">
      <div class="search">
        <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="5" stroke="#A1A1A8" stroke-width="1.7"/><path d="M11 11l3.2 3.2" stroke="#A1A1A8" stroke-width="1.7" stroke-linecap="round"/></svg>
        <input class="inp" name="q" value="{{ $q }}" placeholder="Cari deskripsi atau pengguna…">
      </div>
      <input type="hidden" name="action" value="{{ $action }}">
    </form>

    <div class="cats" style="margin-bottom:16px">
      @foreach($actions as $a)
        <a class="chip {{ $action === $a ? 'on' : '' }}"
           href="{{ route('admin.audit', ['action' => $a, 'q' => $q]) }}">{{ $a === 'all' ? 'Semua aksi' : $a }}</a>
      @endforeach
    </div>

    @if($logs->count())
      <div class="tbl-wrap">
        <table>
          <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Deskripsi</th><th>IP</th></tr></thead>
          <tbody>
          @foreach($logs as $log)
            <tr>
              <td class="num mini" style="white-space:nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
              <td><b>{{ $log->user?->name ?? 'Sistem' }}</b><div class="mini">{{ $log->user?->role ?? 'sistem' }}</div></td>
              <td><span class="tag {{ $log->tagClass() }}">{{ $log->action }}</span></td>
              <td>{{ $log->description }}</td>
              <td class="mini num">{{ $log->ip_address }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      {{ $logs->links() }}
    @else
      <div class="card"><div class="empty"><b>Tidak ada aktivitas</b>Ubah filter untuk melihat catatan lain.</div></div>
    @endif

    <p class="mini" style="margin-top:12px">BR-11 · Audit log bersifat append-only: tidak ada fitur ubah atau hapus dari aplikasi.</p>
  </div>
@endsection
