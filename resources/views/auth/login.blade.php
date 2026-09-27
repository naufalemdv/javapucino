@extends('layouts.auth')
@section('title', 'Masuk ke sistem')

@section('form')
  <h1>Masuk ke sistem</h1>
  <p class="lead">Gunakan akun kasir atau administrator Anda.</p>

  @if($errors->any())
    <div class="alert alert-err">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ route('login') }}">
    @csrf
    <div class="field">
      <label>Nama pengguna</label>
      <input class="inp {{ $errors->has('username') ? 'is-err' : '' }}" name="username"
             value="{{ old('username') }}" autocomplete="username" autofocus required>
    </div>
    <div class="field">
      <label>Kata sandi</label>
      <input class="inp {{ $errors->has('password') ? 'is-err' : '' }}" type="password" name="password"
             autocomplete="current-password" required>
    </div>
    <button class="btn btn-red btn-lg btn-block" type="submit">Masuk</button>
  </form>

  @if(config('app.debug') && $demoUsers->isNotEmpty())
    <div class="divide">akun demo</div>
    <div class="demo-pick">
      @foreach($demoUsers as $du)
        <button type="button" class="demo-btn" data-user="{{ $du->username }}">
          <div class="av">{{ $du->initial() }}</div>
          <div style="min-width:0">
            <b>{{ $du->name }}</b>
            <small>&#64;{{ $du->username }} · {{ $du->roleLabel() }}</small>
          </div>
        </button>
      @endforeach
    </div>
    <p class="mini" style="text-align:center">Kata sandi seluruh akun demo: <b>password123</b></p>
  @endif
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-user]').forEach(b => b.addEventListener('click', () => {
  document.querySelector('input[name=username]').value = b.dataset.user;
  document.querySelector('input[name=password]').value = 'password123';
  document.querySelectorAll('.demo-btn').forEach(x => x.classList.remove('on'));
  b.classList.add('on');
}));
</script>
@endpush
