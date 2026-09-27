@extends('layouts.app')
@php $isNew = ! $user->exists; @endphp
@section('title', $isNew ? 'Tambah pengguna' : 'Ubah pengguna')

@section('content')
  @include('partials.topbar', ['title' => $isNew ? 'Tambah pengguna' : 'Ubah pengguna'])

  <div class="page">
    <form method="POST" class="form-grid"
          action="{{ $isNew ? route('admin.users.store') : route('admin.users.update', $user) }}">
      @csrf
      @unless($isNew) @method('PUT') @endunless

      @if($errors->any())<div class="alert alert-err">{{ $errors->first() }}</div>@endif

      <div class="card"><div class="card-b">
        <div class="field">
          <label>Nama lengkap</label>
          <input class="inp" name="name" value="{{ old('name', $user->name) }}" placeholder="Rina Oktaviani" required>
          @error('name')<div class="err-text">{{ $message }}</div>@enderror
        </div>

        <div class="row2">
          <div class="field">
            <label>Nama pengguna</label>
            <input class="inp" name="username" value="{{ old('username', $user->username) }}" placeholder="rina" required>
            @error('username')<div class="err-text">{{ $message }}</div>@enderror
          </div>
          <div class="field">
            <label>Email (opsional)</label>
            <input class="inp" type="email" name="email" value="{{ old('email', $user->email) }}">
            @error('email')<div class="err-text">{{ $message }}</div>@enderror
          </div>
        </div>

        <div class="row2">
          <div class="field">
            <label>Peran</label>
            <select class="inp" name="role">
              <option value="kasir" @selected(old('role', $user->role) === 'kasir')>Kasir</option>
              <option value="admin" @selected(old('role', $user->role) === 'admin')>Administrator</option>
            </select>
          </div>
          <div class="field">
            <label>Status</label>
            <select class="inp" name="is_active">
              <option value="1" @selected(old('is_active', (int) $user->is_active) == 1)>Aktif</option>
              <option value="0" @selected(old('is_active', (int) $user->is_active) == 0)>Nonaktif</option>
            </select>
          </div>
        </div>

        <div class="row2">
          <div class="field">
            <label>{{ $isNew ? 'Kata sandi' : 'Kata sandi baru' }}</label>
            <input class="inp" type="password" name="password" {{ $isNew ? 'required' : '' }} autocomplete="new-password">
            <div class="hint">Minimal 8 karakter, disimpan ter-hash bcrypt.{{ $isNew ? '' : ' Kosongkan bila tidak diubah.' }}</div>
            @error('password')<div class="err-text">{{ $message }}</div>@enderror
          </div>
          <div class="field">
            <label>Ulangi kata sandi</label>
            <input class="inp" type="password" name="password_confirmation" {{ $isNew ? 'required' : '' }} autocomplete="new-password">
          </div>
        </div>

        <div style="display:flex;gap:9px">
          <a class="btn btn-line" style="flex:1" href="{{ route('admin.users.index') }}">Batal</a>
          <button class="btn btn-red" style="flex:1.4" type="submit">Simpan</button>
        </div>
      </div></div>
    </form>
  </div>
@endsection
