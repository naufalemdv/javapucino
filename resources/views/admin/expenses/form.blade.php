@extends('layouts.app')
@section('title', $expense->exists ? 'Ubah Pengeluaran' : 'Catat Pengeluaran')

@section('content')
@include('partials.topbar', [
    'title' => $expense->exists ? 'Ubah Pengeluaran' : 'Catat Pengeluaran',
    'sub'   => 'Biaya operasional yang mengurangi laba kotor',
    'right' => '<a class="btn btn-line btn-sm" href="'.e(route('admin.expenses.index', ['bulan' => $bulan])).'">← Kembali</a>',
])

<div class="page">
    <form method="POST" action="{{ $expense->exists ? route('admin.expenses.update', $expense) : route('admin.expenses.store') }}">
        @csrf
        @if($expense->exists) @method('PUT') @endif

        @if($errors->any())
            <div class="alert alert-err">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        <div class="card">
            <div class="card-h"><h3>Detail pengeluaran</h3></div>
            <div class="card-b">

                <label class="lbl" for="expense_category_id">Kategori</label>
                <select class="inp" id="expense_category_id" name="expense_category_id" required>
                    <option value="">— pilih kategori —</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->id }}" @selected(old('expense_category_id', $expense->expense_category_id) == $c->id)>
                            {{ $c->name }}
                        </option>
                    @endforeach
                </select>

                <label class="lbl" for="expense_date">Tanggal</label>
                <input class="inp" id="expense_date" type="date" name="expense_date" required
                       max="{{ now()->format('Y-m-d') }}"
                       value="{{ old('expense_date', optional($expense->expense_date)->format('Y-m-d') ?: now()->format('Y-m-d')) }}">

                <label class="lbl" for="title">Keterangan</label>
                <input class="inp" id="title" type="text" name="title" required maxlength="120"
                       placeholder="contoh: Token listrik September"
                       value="{{ old('title', $expense->title) }}">

                <label class="lbl" for="amount">Nominal (Rp)</label>
                <input class="inp" id="amount" type="number" name="amount" required min="1" step="1"
                       value="{{ old('amount', $expense->amount ? (int) $expense->amount : '') }}">

                <label class="lbl" for="payment_method">Metode pembayaran</label>
                <select class="inp" id="payment_method" name="payment_method" required>
                    @foreach(['cash' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('payment_method', $expense->payment_method) === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <label class="lbl" for="related_user_id">Untuk karyawan (opsional)</label>
                <select class="inp" id="related_user_id" name="related_user_id">
                    <option value="">— tidak spesifik —</option>
                    @foreach($staff as $s)
                        <option value="{{ $s->id }}" @selected(old('related_user_id', $expense->related_user_id) == $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>

                <label class="lbl" for="note">Catatan (opsional)</label>
                <textarea class="inp" id="note" name="note" rows="3" maxlength="500">{{ old('note', $expense->note) }}</textarea>

                <div style="margin-top:14px">
                    <button class="btn btn-red" type="submit">Simpan</button>
                    <a class="btn btn-line" href="{{ route('admin.expenses.index', ['bulan' => $bulan]) }}">Batal</a>
                </div>

            </div>
        </div>
    </form>
</div>
@endsection