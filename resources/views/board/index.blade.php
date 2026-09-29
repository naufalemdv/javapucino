<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Menu — {{ $appSettings['store_name'] ?? 'Javapucino' }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,800;1,900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div id="app">
  <div class="screen">
    <div class="screen-menu poster">
      <div class="slides" id="slides">
        <div class="track">
          @foreach($slides as $src)
            <div class="slide"><img src="{{ $src }}" alt="Menu {{ $appSettings['store_name'] ?? 'Javapucino' }} {{ $loop->iteration }}"></div>
          @endforeach
        </div>
        @if(count($slides) > 1)
          <div class="dots">@foreach($slides as $src)<i class="{{ $loop->first ? 'on' : '' }}"></i>@endforeach</div>
        @endif
      </div>
    </div>

    <aside class="qpanel" id="qpanel">
      @include('board._queue', ['q' => $queue])
    </aside>
  </div>
</div>

<script>
/* Slide gambar bergeser otomatis. */
(function () {
  const box = document.getElementById('slides');
  const track = box.querySelector('.track');
  const dots = box.querySelectorAll('.dots i');
  const n = track.children.length;
  if (n < 2) return;
  let i = 0;
  setInterval(() => {
    i = (i + 1) % n;
    track.style.transform = `translateX(-${i * 100}%)`;
    dots.forEach((d, k) => d.classList.toggle('on', k === i));
  }, {{ $slideMs }});
})();

const slidesV = @json($queue['slides_v']);

/* Layar pelanggan menyegarkan nomor antrian tiap 5 detik. */
async function pollQueue() {
  try {
    const res = await fetch(@json(route('menu.queue')), { headers: { 'Accept': 'application/json' } });
    if (!res.ok) return;
    const q = await res.json();
    if (q.slides_v && q.slides_v !== slidesV) return location.reload();
    render(q);
  } catch (e) { /* diabaikan, coba lagi siklus berikutnya */ }
}

function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

function render(q) {
  const cur = q.current;
  document.getElementById('qpanel').innerHTML = `
    <div class="qhead">
      <div class="lbl">Nomor antrian</div>
      <div class="sub">${q.waiting} pesanan sedang disiapkan · ${q.done} selesai hari ini</div>
    </div>
    ${cur ? `
      <div class="qnow">
          <div class="cap">Nomor Antrian</div>
          <div class="no">${esc(cur.no)}</div>
          ${cur.name ? `<div class="who">${esc(cur.name)}</div>` : ''}
        </div>`
    : `<div class="qnow idle">
        <div class="cap">Belum ada yang dipanggil</div>
        <div class="no">—</div>
        <div class="who">Menunggu kasir memanggil nomor</div>
      </div>`}
    <div class="qnext">
      <div class="lbl" style="font-family:var(--f-d);font-weight:800;font-size:14px">Antrian selanjutnya</div>
      ${q.next.length ? `<div class="qnext-grid">${q.next.map((n,i)=>`
        <div class="qchip ${i===0?'first':''}">
          <div class="n">${esc(n.no)}</div>
          <div class="s">${i===0?'Berikutnya':'Menunggu'}</div>
        </div>`).join('')}</div>`
      : `<div class="qempty">Tidak ada antrian menunggu.<br>Silakan pesan ke kasir.</div>`}
    </div>`;
}

setInterval(pollQueue, 5000);
</script>
</body>
</html>
