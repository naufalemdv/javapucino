<div class="qhead">
  <div class="lbl">Nomor antrian</div>
  <div class="sub">{{ $q['waiting'] }} pesanan sedang disiapkan · {{ $q['done'] }} selesai hari ini</div>
</div>

@if($q['current'])
  <div class="qnow">
    <div class="cap">Pesanan siap diambil</div>
    <div class="no">{{ $q['current']['no'] }}</div>
    <div class="who">{{ $q['current']['name'] ?: 'Silakan ambil di konter' }}</div>
    <div class="qlive"><span class="beat"></span>Diperbarui langsung oleh kasir</div>
  </div>
@else
  <div class="qnow idle">
    <div class="cap">Belum ada yang dipanggil</div>
    <div class="no">—</div>
    <div class="who">Menunggu kasir memanggil nomor</div>
  </div>
@endif

<div class="qnext">
  <div class="lbl" style="font-family:var(--f-d);font-weight:800;font-size:14px">Antrian selanjutnya</div>
  @if(count($q['next']))
    <div class="qnext-grid">
      @foreach($q['next'] as $i => $n)
        <div class="qchip {{ $i === 0 ? 'first' : '' }}">
          <div class="n">{{ $n['no'] }}</div>
          <div class="s">{{ $i === 0 ? 'Berikutnya' : 'Menunggu' }}</div>
        </div>
      @endforeach
    </div>
  @else
    <div class="qempty">Tidak ada antrian menunggu.<br>Silakan pesan ke kasir.</div>
  @endif
</div>
