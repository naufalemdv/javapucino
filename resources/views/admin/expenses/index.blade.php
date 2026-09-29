@extends('layouts.app')
@section('title', 'Pengeluaran')

@php
    $maxCat = max($perCategory->max('amount') ?: 0, 1);
@endphp

@section('content')
@include('partials.topbar', [
    'title' => 'Pengeluaran & Laba Bersih',
    'sub'   => $month->translatedFormat('F Y').' · '.$from->format('d/m').' – '.$to->format('d/m/Y')
               .($isRunning ? ' · berjalan (hari ke-'.$dayOfMonth.' dari '.$daysInMonth.')' : ''),
    'right' => '<a class="btn btn-red btn-sm no-print" href="'.e(route('admin.expenses.export', ['bulan' => $month->format('Y-m')])).'">📊 Export Excel</a>'
             . '<a class="btn btn-line btn-sm no-print" href="'.e(route('admin.expenses.create', ['bulan' => $month->format('Y-m')])).'">➕ Catat Pengeluaran</a>'
             . '<button class="btn btn-line btn-sm no-print" type="button" onclick="window.print()">🖨️ Cetak / PDF</button>',
])

<div class="page">

    <div class="toolbar no-print">
        <form method="GET" action="{{ route('admin.expenses.index') }}" class="date-range on">
            <span class="lbl">🗓️</span>
            <select class="inp" name="bulan" onchange="this.form.submit()" aria-label="Pilih bulan">
                @foreach($months as $value => $label)
                    <option value="{{ $value }}" @selected($month->format('Y-m') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </form>

        <form method="GET" action="{{ route('admin.expenses.index') }}" class="cashier-pick {{ $catId ? 'on' : '' }}">
            <input type="hidden" name="bulan" value="{{ $month->format('Y-m') }}">
            <span class="lbl">🏷️</span>
            <select class="inp" name="kategori" onchange="this.form.submit()" aria-label="Filter kategori">
                <option value="">Semua kategori</option>
                @foreach($categories as $c)
                    <option value="{{ $c->id }}" @selected($catId === $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </form>

        <a class="chip" href="{{ route('admin.expenses.categories') }}">⚙️ Kelola kategori</a>
    </div>

    {{-- KPI --}}
    <div class="kpis">
        <div class="kpi">
            <div class="lb">Omzet</div>
            <div class="vl">{{ rupiah($omzet) }}</div>
            <div class="mini">{{ $count }} transaksi</div>
        </div>
        <div class="kpi">
            <div class="lb">HPP</div>
            <div class="vl">{{ rupiah($hpp) }}</div>
            <div class="mini">modal bahan terjual</div>
        </div>
        <div class="kpi">
            <div class="lb">Laba kotor</div>
            <div class="vl" style="color:var(--green)">{{ rupiah($grossProfit) }}</div>
            <div class="mini">margin {{ $omzet > 0 ? round($grossProfit / $omzet * 100) : 0 }}%</div>
        </div>
        <div class="kpi">
            <div class="lb">Biaya operasional</div>
            <div class="vl">− {{ rupiah($operational) }}</div>
            <div class="mini">listrik, wifi, gaji, dll.</div>
        </div>
        <div class="kpi hero">
            <div class="lb">Laba bersih</div>
            <div class="vl">{{ rupiah($netProfit) }}</div>
            <div class="mini" style="color:rgba(255,255,255,.8)">
                margin {{ round($netMargin) }}%
                @if($previous) · bulan lalu {{ rupiah($previous['netProfit']) }} @endif
            </div>
        </div>
    </div>

    @if($isRunning)
        <div class="alert no-print" style="margin-top:12px">
            Bulan ini masih berjalan. Laba bersih belum final selama biaya tetap bulan ini belum diinput.
        </div>
    @endif

    @if($missing->isNotEmpty())
        <div class="alert alert-err no-print" style="margin-top:12px">
            Belum tercatat bulan ini: <b>{{ $missing->join(', ') }}</b> — bulan lalu kategori ini ada isinya.
        </div>
    @endif

    {{-- Arus kas keluar --}}
    <div class="card" style="margin-top:14px">
        <div class="card-h"><h3>Arus kas keluar</h3></div>
        <div class="card-b">
            <div class="bars">
                @forelse($perCategory as $c)
                    <div class="b {{ $c['amount'] > 0 && $c['amount'] == $maxCat ? 'top' : '' }}"
                         title="{{ $c['name'] }} — {{ rupiah($c['amount']) }} ({{ $c['count'] }} entri)">
                        <div class="fill" style="height:{{ max(3, $c['amount'] / $maxCat * 100) }}%"></div>
                        <div class="cap">{{ str($c['name'])->limit(10, '') }}&nbsp;</div>
                    </div>
                @empty
                    <div class="empty"><b>Belum ada pengeluaran</b>Belum ada uang keluar yang tercatat bulan ini.</div>
                @endforelse
            </div>

            <div class="mini" style="margin-top:10px">
                Operasional {{ rupiah($operational) }} · Pembelian bahan {{ rupiah($inventory) }} ·
                <b>Total kas keluar {{ rupiah($cashOut) }}</b>
                @if($hpp > 0 && $inventory > 0)
                    · HPP estimasi {{ rupiah($hpp) }} vs belanja bahan aktual {{ rupiah($inventory) }}
                    (selisih {{ round(abs($inventory - $hpp) / max($hpp, 1) * 100) }}%)
                @endif
            </div>
        </div>
    </div>

    {{-- Rincian --}}
    <div class="sec-title">Rincian pengeluaran</div>
    <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Kategori</th>
                    <th>Keterangan</th>
                    <th>Metode</th>
                    <th class="r">Nominal</th>
                    <th class="r no-print"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($list as $e)
                    <tr>
                        <td class="num">{{ $e->expense_date->format('d/m/Y') }}</td>
                        <td>
                            {{ $e->category?->name ?? '—' }}
                            @unless($e->isOperational())
                                <b class="pill">stok</b>
                            @endunless
                        </td>
                        <td>
                            <b>{{ $e->title }}</b>
                            @if($e->relatedUser)<br><small>untuk {{ $e->relatedUser->name }}</small>@endif
                            @if($e->note)<br><small>{{ $e->note }}</small>@endif
                        </td>
                        <td>{{ $e->paymentLabel() }}</td>
                        <td class="r num" style="font-weight:800">{{ rupiah($e->amount) }}</td>
                        <td class="r no-print">
                            @if($e->isLocked())
                                <a class="btn btn-line btn-sm" href="{{ route('admin.materials.index') }}">Stok Bahan</a>
                            @else
                                <a class="btn btn-line btn-sm" href="{{ route('admin.expenses.edit', $e) }}">Ubah</a>
                                <form method="POST" action="{{ route('admin.expenses.destroy', $e) }}" style="display:inline"
                                      onsubmit="return confirm('Hapus pengeluaran ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-line btn-sm" type="submit">Hapus</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty"><b>Belum ada pengeluaran</b>Catat pengeluaran pertama bulan ini.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection