@php
    $u = auth()->user();
        $nav = $u->isAdmin() ? [
        ['g' => 'Ringkasan'],
        ['r' => route('admin.dashboard'),         'i' => '📊', 't' => 'Dashboard',       'a' => request()->routeIs('admin.dashboard')],
        ['r' => route('admin.transactions.index'),'i' => '🧾', 't' => 'Transaksi',       'a' => request()->routeIs('admin.transactions.*')],
        ['r' => route('admin.report'),            'i' => '📈', 't' => 'Laporan',         'a' => request()->routeIs('admin.report')],
        ['r' => route('admin.shifts'),            'i' => '💰', 't' => 'Rekap Shift',     'a' => request()->routeIs('admin.shifts')],
        ['g' => 'Data Master'],
        ['r' => route('admin.products.index'),    'i' => '🍹', 't' => 'Produk',          'a' => request()->routeIs('admin.products.*')],
        ['r' => route('admin.categories.index'),  'i' => '🗂️', 't' => 'Kategori',        'a' => request()->routeIs('admin.categories.*')],
        ['r' => route('admin.materials.index'),   'i' => '📦', 't' => 'Stok Bahan',      'a' => request()->routeIs('admin.materials.*')],
        ['r' => route('admin.users.index'),       'i' => '👥', 't' => 'Pengguna',        'a' => request()->routeIs('admin.users.*')],
        ['g' => 'Sistem'],
        ['r' => route('admin.audit'),             'i' => '🛡️', 't' => 'Audit Log',       'a' => request()->routeIs('admin.audit')],
        ['r' => route('admin.expenses.index'),    'i' => '💸', 't' => 'Pengeluaran',     'a' => request()->routeIs('admin.expenses.*')],
        ['r' => route('admin.settings'),          'i' => '⚙️', 't' => 'Pengaturan',      'a' => request()->routeIs('admin.settings')],
        ['r' => route('admin.antrian'),           'i' => '🔔', 't' => 'Antrian',         'a' => request()->routeIs('admin.antrian')],
        ['r' => route('menu'),                    'i' => '📺', 't' => 'Layar Pelanggan', 'a' => false, 'blank' => true],
    ] : [
        ['g' => 'Operasional'],
        ['r' => route('kasir.pos'),        'i' => '🧾', 't' => 'Kasir',           'a' => request()->routeIs('kasir.pos'), 'cart' => true],
        ['r' => route('kasir.antrian'),    'i' => '🔔', 't' => 'Antrian',         'a' => request()->routeIs('kasir.antrian')],
        ['r' => route('kasir.riwayat'),    'i' => '📑', 't' => 'Riwayat Shift',   'a' => request()->routeIs('kasir.riwayat')],
        ['g' => 'Lainnya'],
        ['r' => route('menu'),             'i' => '📺', 't' => 'Layar Pelanggan', 'a' => false, 'blank' => true],
        ['r' => route('kasir.shift.edit'), 'i' => '🔒', 't' => 'Tutup Shift',     'a' => request()->routeIs('kasir.shift.edit')],
    ];
    $shift = $u->isKasir() ? $u->openShift() : null;
@endphp

<aside class="rail">
    <div class="rail-top">
        <div class="mark" style="width:36px;height:36px;font-size:19px">J</div>
        <div class="rail-brand">
            <span class="wm">{{ $appSettings['store_name'] ?? 'Javapucino' }}</span>
            <small>{{ $u->isAdmin() ? 'CMS Admin' : 'Portal Kasir' }}</small>
        </div>
    </div>

    <div class="rail-scroll">
        @foreach($nav as $n)
            @if(isset($n['g']))
                <div class="rail-group">{{ $n['g'] }}</div>
            @else
                <a class="nav {{ $n['a'] ? 'on' : '' }}" href="{{ $n['r'] }}" @if($n['blank'] ?? false) target="_blank" @endif>
                    <i>{{ $n['i'] }}</i><span>{{ $n['t'] }}</span>
                    @if($n['cart'] ?? false)<b class="pill" id="railCart" style="display:none">0</b>@endif
                </a>
            @endif
        @endforeach
    </div>

    <div class="rail-foot">
        <div class="avatar">{{ $u->initial() }}</div>
        <div class="who">
            <b>{{ $u->name }}</b>
            <small>{{ $u->roleLabel() }}@if($shift) · Shift {{ $shift->shift_type }}@endif</small>
        </div>
        <button class="icon-btn" type="button" title="Keluar"
                onclick="if(confirm('Keluar dari sistem?'))document.getElementById('logout-form').submit()">⏻</button>
    </div>
</aside>