@extends('layouts.app')
@section('title', 'Pengaturan')

@push('styles')
  <link rel="stylesheet" href="{{ asset('vendor/cropper/cropper.min.css') }}">
  <link rel="stylesheet" href="{{ asset('css/crop.css') }}">
@endpush

@section('content')
  @include('partials.topbar', ['title' => 'Pengaturan', 'sub' => 'Identitas toko, pajak, struk, dan layar pelanggan'])

  <div class="page">
    @if($errors->any())<div class="alert alert-err">{{ $errors->first() }}</div>@endif

   <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
      @csrf @method('PUT')

      <div class="grid2">
        <div>
          <div class="card">
            <div class="card-h"><h3>Identitas toko</h3></div>
            <div class="card-b">
              <div class="field"><label>Nama toko</label>
                <input class="inp" name="store_name" value="{{ old('store_name', $s['store_name'] ?? '') }}" required></div>
              <div class="field"><label>Alamat</label>
                <input class="inp" name="store_address" value="{{ old('store_address', $s['store_address'] ?? '') }}"></div>
              <div class="field" style="margin-bottom:0"><label>Telepon</label>
                <input class="inp" name="store_phone" value="{{ old('store_phone', $s['store_phone'] ?? '') }}"></div>
            </div>
          </div>

          <div class="card" style="margin-top:14px">
            <div class="card-h"><h3>Transaksi &amp; struk</h3></div>
            <div class="card-b">
              <div class="row2">
                <div class="field"><label>Pajak (%)</label>
                  <input class="inp num" name="tax_percent" inputmode="decimal"
                         value="{{ old('tax_percent', $s['tax_percent'] ?? 0) }}" required></div>
                <div class="field"><label>Lebar kertas</label>
                  <select class="inp" name="paper_width">
                    <option value="58mm" @selected(($s['paper_width'] ?? '58mm') === '58mm')>58mm</option>
                    <option value="80mm" @selected(($s['paper_width'] ?? '') === '80mm')>80mm</option>
                  </select></div>
              </div>
              <div class="field"><label>NMID QRIS</label>
                  <input class="inp" name="qris_nmid" value="{{ old('qris_nmid', $s['qris_nmid'] ?? '') }}">
                  <div class="hint">Isi sesuai NMID yang tertera di QRIS toko.</div></div>
              <div class="field"><label>Gambar QRIS</label>
                  @if(! empty($s['qris_image']))
                      <img src="{{ asset('storage/'.$s['qris_image']) }}" alt="QRIS toko"
                          style="display:block;width:160px;max-width:100%;border:1px solid #E5E5EA;border-radius:10px;margin-bottom:8px">
                  @endif
                  <input class="inp" type="file" name="qris_image" accept="image/jpeg,image/png,image/webp">
                  <div class="hint">Unggah gambar QRIS asli toko (JPG, PNG, atau WEBP, maks 2 MB). Gambar ini tampil di popup pembayaran kasir. Kosongkan kalau tidak ingin mengganti.</div></div>
              <div class="field"><label>Ambang stok menipis</label>
                <input class="inp num" name="low_stock_threshold" inputmode="numeric"
                       value="{{ old('low_stock_threshold', $s['low_stock_threshold'] ?? 5) }}" required>
                <div class="hint">Menu dengan stok ≤ angka ini ditandai “Hampir habis” (BR-13).</div></div>
              <div class="field" style="margin-bottom:0"><label>Catatan kaki struk</label>
                <input class="inp" name="receipt_footer" value="{{ old('receipt_footer', $s['receipt_footer'] ?? '') }}"></div>
            </div>
          </div>

          <div class="card" style="margin-top:14px">
            <div class="card-h"><h3>Layar pelanggan</h3></div>
            <div class="card-b">
              <div class="field" style="margin-bottom:0"><label>Durasi tiap gambar (detik)</label>
                <input class="inp num" name="board_slide_seconds" inputmode="numeric"
                       value="{{ old('board_slide_seconds', $s['board_slide_seconds'] ?? 6) }}" required>
                <div class="hint">Gambar di layar pelanggan bergeser otomatis setiap sekian detik (3–120).</div></div>
            </div>
          </div>

          <button class="btn btn-red btn-lg btn-block" style="margin-top:14px" type="submit">Simpan pengaturan</button>
        </div>

        <div class="card">
          <div class="card-h"><h3>Pratinjau struk</h3><div class="grow"></div>
            <span class="tag t-gray">{{ $s['paper_width'] ?? '58mm' }}</span></div>
          <div class="card-b">
            <div class="receipt {{ ($s['paper_width'] ?? '58mm') === '80mm' ? 'w80' : '' }}">
              <div class="ctr">
                <div class="logo">{{ $s['store_name'] ?? 'Javapucino' }}</div>
                <div>{{ $s['store_address'] ?? '' }}</div>
                <div>{{ $s['store_phone'] ?? '' }}</div>
              </div>
              <div class="hr"></div>
              <div class="ln"><span>No</span><span>TRX-{{ now()->format('Ymd') }}-0001</span></div>
              <div class="ln"><span>Tanggal</span><span>{{ now()->format('d/m/Y H:i') }}</span></div>
              <div class="ln"><span>Kasir</span><span>{{ auth()->user()->name }}</span></div>
              <div class="hr"></div>
              <div class="ctr big">NO. ANTRIAN 007</div>
              <div class="hr"></div>
              <div style="margin-bottom:4px"><div>Kopi Susu Gula Aren</div>
                <div class="ln"><span>&nbsp;&nbsp;1 x 18.000</span><span>18.000</span></div></div>
              <div style="margin-bottom:4px"><div>Cappuccino</div>
                <div class="ln"><span>&nbsp;&nbsp;1 x 22.000</span><span>22.000</span></div></div>
              <div class="hr"></div>
              @php
                $demoSub = 40000;
                $demoTax = round($demoSub * (float) ($s['tax_percent'] ?? 0) / 100);
              @endphp
              <div class="ln"><span>Subtotal</span><span>{{ angka($demoSub) }}</span></div>
              @if($demoTax > 0)
                <div class="ln"><span>Pajak {{ (float) $s['tax_percent'] }}%</span><span>{{ angka($demoTax) }}</span></div>
              @endif
              <div class="ln big"><span>TOTAL</span><span>{{ angka($demoSub + $demoTax) }}</span></div>
              <div class="ln"><span>Tunai</span><span>50.000</span></div>
              <div class="ln"><span>Kembali</span><span>{{ angka(50000 - $demoSub - $demoTax) }}</span></div>
              <div class="hr"></div>
              <div class="ctr">{{ $s['receipt_footer'] ?? '' }}</div>
            </div>
            <p class="mini" style="margin-top:12px;text-align:center">Pratinjau memakai nilai yang tersimpan. Simpan dulu untuk melihat perubahan.</p>
          </div>
        </div>
      </div>
    </form>

    <div class="card" style="margin-top:14px">
      <div class="card-h"><h3>Gambar layar pelanggan</h3><div class="grow"></div>
        <span class="tag t-gray">{{ count($slides) }} gambar</span></div>
      <div class="card-b">
        <form method="POST" action="{{ route('admin.settings.slides.store') }}" enctype="multipart/form-data" class="slide-upload">
          @csrf
          <input class="inp" type="file" name="images[]" id="slideInput"
                 accept="image/jpeg,image/png,image/webp" multiple required
                 data-crop
                 data-crop-aspect="3:2"
                 data-crop-max="1920"
                 data-crop-maxsize="5"
                 data-crop-title="Potong gambar layar pelanggan">
          <button class="btn btn-red" type="submit">Unggah gambar</button>
        </form>
        <div class="hint" style="margin:6px 0 0">Bisa pilih beberapa gambar sekaligus. JPG, PNG, atau WEBP, maks 5 MB per gambar.
          Setelah memilih berkas, jendela pemotongan terbuka supaya Anda menentukan bagian mana yang tampil. Rasio 3:2 paling pas dengan area layar pelanggan.</div>

        {{-- Daftar gambar hasil potong, belum terkirim sampai tombol Unggah gambar ditekan --}}
        <div data-crop-preview="slideInput"></div>

        <div class="hint" style="margin:14px 0 14px">Gambar yang sudah tersimpan tampil bergantian sesuai urutan di bawah.</div>

        @if($slides)
          <div class="slide-grid">
            @foreach($slides as $i => $path)
              <div class="slide-item">
                <div class="ph"><img src="{{ asset('storage/'.$path) }}" alt="Slide {{ $i + 1 }}" loading="lazy"><span class="no">{{ $i + 1 }}</span></div>
                <div class="acts">
                  <form method="POST" action="{{ route('admin.settings.slides.move', $i) }}">@csrf @method('PATCH')
                    <input type="hidden" name="dir" value="-1">
                    <button class="btn btn-line btn-sm" type="submit" title="Geser ke kiri" @disabled($loop->first)>←</button></form>
                  <form method="POST" action="{{ route('admin.settings.slides.move', $i) }}">@csrf @method('PATCH')
                    <input type="hidden" name="dir" value="1">
                    <button class="btn btn-line btn-sm" type="submit" title="Geser ke kanan" @disabled($loop->last)>→</button></form>
                  <form method="POST" action="{{ route('admin.settings.slides.destroy', $i) }}" style="margin-left:auto"
                        data-confirm="Hapus gambar {{ $i + 1 }} dari layar pelanggan?">@csrf @method('DELETE')
                    <button class="btn btn-line btn-sm" type="submit" style="color:var(--red)">Hapus</button></form>
                </div>
              </div>
            @endforeach
          </div>
        @else
          <div class="empty"><b>Belum ada gambar</b>Layar pelanggan menampilkan poster Daftar Menu bawaan sampai Anda mengunggah gambar.</div>
        @endif
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('vendor/cropper/cropper.min.js') }}"></script>
  <script src="{{ asset('js/crop-upload.js') }}"></script>
@endpush
