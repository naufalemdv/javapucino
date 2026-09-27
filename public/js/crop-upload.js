/* ============================================================
   Javapucino POS — Potong gambar sebelum diunggah
   ------------------------------------------------------------
   Cara pakai pada Blade:

     <input type="file" name="images[]" multiple
            id="slideInput" data-crop data-crop-aspect="3:2"
            data-crop-max="1920" data-crop-maxsize="5">
     <div data-crop-preview="slideInput"></div>

   Atribut opsional:
     data-crop-aspect   rasio awal: free | 3:2 | 16:9 | 4:3 | 1:1   (default: free)
     data-crop-max      sisi terpanjang hasil potong, piksel        (default: 1920)
     data-crop-maxsize  batas ukuran berkas hasil, MB               (default: 5)
     data-crop-title    judul yang tampil di modal

   Berkas asli tidak pernah dikirim ke server; yang dikirim adalah
   hasil potongnya. Bila Cropper.js gagal dimuat, input bekerja
   seperti biasa (unggah tanpa potong).
   ============================================================ */
(function () {
  'use strict';

  if (typeof window.Cropper === 'undefined') {
    console.warn('[crop-upload] Cropper.js tidak ditemukan. Unggah berjalan tanpa fitur potong.');
    return;
  }

  /* ---------- Pilihan rasio ---------- */
  var RATIOS = [
    { key: 'free', label: 'Bebas',   value: NaN },
    { key: '3:2',  label: '3 : 2',   value: 3 / 2 },
    { key: '16:9', label: '16 : 9',  value: 16 / 9 },
    { key: '4:3',  label: '4 : 3',   value: 4 / 3 },
    { key: '1:1',  label: '1 : 1',   value: 1 }
  ];

  var store   = new WeakMap(); // input -> { originals:[], results:[], sizes:[] }
  var ui      = null;          // elemen modal, dibuat sekali
  var session = null;          // proses potong yang sedang berjalan

  /* ============================================================
     Utilitas
     ============================================================ */
  function ratioOf(key) {
    for (var i = 0; i < RATIOS.length; i++) {
      if (RATIOS[i].key === key) return RATIOS[i].value;
    }
    return NaN;
  }

  function extOf(type) {
    if (type === 'image/png') return '.png';
    if (type === 'image/webp') return '.webp';
    return '.jpg';
  }

  function baseName(name) {
    return String(name || 'gambar').replace(/\.[^.]+$/, '').slice(0, 60) || 'gambar';
  }

  function kb(bytes) {
    return bytes >= 1048576
      ? (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB'
      : Math.max(1, Math.round(bytes / 1024)) + ' KB';
  }

  function supportsWebp() {
    var c = document.createElement('canvas');
    c.width = c.height = 1;
    return c.toDataURL('image/webp').indexOf('data:image/webp') === 0;
  }

  function toBlob(canvas, type, quality) {
    return new Promise(function (resolve) {
      canvas.toBlob(function (b) { resolve(b); }, type, quality);
    });
  }

  /* Latar putih untuk gambar transparan yang dikonversi ke JPEG. */
  function flatten(canvas) {
    var out = document.createElement('canvas');
    out.width = canvas.width;
    out.height = canvas.height;
    var ctx = out.getContext('2d');
    ctx.fillStyle = '#FFFFFF';
    ctx.fillRect(0, 0, out.width, out.height);
    ctx.drawImage(canvas, 0, 0);
    return out;
  }

  /* Kanvas -> Blob, menyusut otomatis bila melewati batas ukuran. */
  async function encode(canvas, sourceType, maxBytes) {
    var type = 'image/jpeg';
    if (sourceType === 'image/png') type = 'image/png';
    else if (sourceType === 'image/webp' && supportsWebp()) type = 'image/webp';

    var blob = await toBlob(canvas, type, type === 'image/png' ? undefined : 0.92);
    if (blob && blob.size <= maxBytes) return blob;

    var target = type === 'image/png' ? 'image/jpeg' : type;
    var source = target === 'image/jpeg' ? flatten(canvas) : canvas;
    var steps  = [0.88, 0.8, 0.72, 0.62, 0.5];

    for (var i = 0; i < steps.length; i++) {
      blob = await toBlob(source, target, steps[i]);
      if (blob && blob.size <= maxBytes) break;
    }
    return blob;
  }

  function readOptions(input) {
    var max = parseInt(input.dataset.cropMax || '1920', 10);
    var mb  = parseFloat(input.dataset.cropMaxsize || '5');
    return {
      aspect: input.dataset.cropAspect || 'free',
      max: isNaN(max) || max < 200 ? 1920 : max,
      maxBytes: (isNaN(mb) || mb <= 0 ? 5 : mb) * 1024 * 1024,
      title: input.dataset.cropTitle || 'Potong gambar'
    };
  }

  /* ============================================================
     Modal
     ============================================================ */
  function buildUI() {
    var scrim = document.createElement('div');
    scrim.className = 'crop-scrim';
    scrim.innerHTML =
      '<div class="crop-modal" role="dialog" aria-modal="true" aria-labelledby="cropTitle">' +
        '<div class="crop-head">' +
          '<div style="min-width:0">' +
            '<h3 id="cropTitle">Potong gambar</h3>' +
            '<div class="sub" id="cropSub"></div>' +
          '</div>' +
          '<button class="x" type="button" data-crop-act="cancel" aria-label="Tutup">&#10005;</button>' +
        '</div>' +
        '<div class="crop-body">' +
          '<div class="crop-stage"><img id="cropImg" alt="Gambar yang sedang dipotong"></div>' +
          '<div class="crop-tools">' +
            '<div class="crop-ratios" id="cropRatios"></div>' +
            '<div class="crop-steps">' +
              '<button class="btn btn-line btn-sm" type="button" data-crop-act="zoom-in"  title="Perbesar">+</button>' +
              '<button class="btn btn-line btn-sm" type="button" data-crop-act="zoom-out" title="Perkecil">&minus;</button>' +
              '<button class="btn btn-line btn-sm" type="button" data-crop-act="rotate"   title="Putar 90&deg;">&#8635;</button>' +
              '<button class="btn btn-line btn-sm" type="button" data-crop-act="flip"     title="Cermin">&#8646;</button>' +
              '<button class="btn btn-line btn-sm" type="button" data-crop-act="reset"    title="Kembalikan">&#8634;</button>' +
            '</div>' +
          '</div>' +
          '<p class="crop-note" id="cropNote">Geser gambar untuk menggeser bingkai, tarik sudut kotak untuk mengubah ukuran. Bagian di dalam kotak itulah yang disimpan.</p>' +
        '</div>' +
        '<div class="crop-foot">' +
          '<button class="btn btn-ghost" type="button" data-crop-act="cancel">Batal</button>' +
          '<button class="btn btn-line"  type="button" data-crop-act="skip">Pakai apa adanya</button>' +
          '<button class="btn btn-red"   type="button" data-crop-act="apply">Pakai hasil potong</button>' +
        '</div>' +
      '</div>';

    document.body.appendChild(scrim);

    var ratios = scrim.querySelector('#cropRatios');
    RATIOS.forEach(function (r) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'chip';
      b.dataset.cropRatio = r.key;
      b.textContent = r.label;
      ratios.appendChild(b);
    });

    scrim.addEventListener('click', function (e) {
      var ratioBtn = e.target.closest('[data-crop-ratio]');
      if (ratioBtn) return setRatio(ratioBtn.dataset.cropRatio);

      var actBtn = e.target.closest('[data-crop-act]');
      if (actBtn) return handleAction(actBtn.dataset.cropAct);
    });

    return {
      scrim: scrim,
      img:   scrim.querySelector('#cropImg'),
      title: scrim.querySelector('#cropTitle'),
      sub:   scrim.querySelector('#cropSub'),
      note:  scrim.querySelector('#cropNote'),
      apply: scrim.querySelector('[data-crop-act="apply"]'),
      skip:  scrim.querySelector('[data-crop-act="skip"]'),
      ratios: ratios
    };
  }

  function setRatio(key) {
    if (!session) return;
    session.ratio = key;
    session.cropper.setAspectRatio(ratioOf(key));
    markRatio();
  }

  function markRatio() {
    ui.ratios.querySelectorAll('[data-crop-ratio]').forEach(function (b) {
      b.classList.toggle('on', b.dataset.cropRatio === session.ratio);
    });
  }

  function handleAction(act) {
    if (!session) return;
    if (act === 'zoom-in')  return session.cropper.zoom(0.1);
    if (act === 'zoom-out') return session.cropper.zoom(-0.1);
    if (act === 'rotate')   return session.cropper.rotate(90);
    if (act === 'flip') {
      session.flipped = !session.flipped;
      return session.cropper.scaleX(session.flipped ? -1 : 1);
    }
    if (act === 'reset') {
      session.flipped = false;
      return session.cropper.reset();
    }
    if (act === 'skip')   return nextImage(session.originals[session.order[session.pos]]);
    if (act === 'apply')  return applyCrop();
    if (act === 'cancel') return cancelSession();
  }

  /* ============================================================
     Alur potong
     ============================================================ */
  function startSession(input, order) {
    var data = store.get(input);
    if (!data || !order.length) return;

    ui = ui || buildUI();

    session = {
      input: input,
      opts: readOptions(input),
      originals: data.originals,
      order: order,
      pos: 0,
      ratio: null,
      flipped: false,
      cropper: null,
      prev: data.results.slice()   // dipakai bila proses dibatalkan
    };
    session.ratio = session.opts.aspect;

    ui.title.textContent = session.opts.title;
    ui.scrim.classList.add('open');
    document.body.style.overflow = 'hidden';

    loadCurrent();
  }

  function loadCurrent() {
    var index = session.order[session.pos];
    var file  = session.originals[index];

    ui.sub.textContent = session.order.length > 1
      ? 'Gambar ' + (session.pos + 1) + ' dari ' + session.order.length + ' · ' + file.name
      : file.name;

    var reader = new FileReader();
    reader.onload = function (e) {
      if (session.cropper) { session.cropper.destroy(); session.cropper = null; }
      session.flipped = false;
      ui.img.src = e.target.result;

      session.cropper = new Cropper(ui.img, {
        viewMode: 1,
        dragMode: 'move',
        autoCropArea: 1,
        background: false,
        guides: true,
        center: true,
        highlight: false,
        responsive: true,
        restore: false,
        toggleDragModeOnDblclick: false,
        aspectRatio: ratioOf(session.ratio),
        minContainerHeight: 240
      });

      markRatio();
      setBusy(false);
    };
    reader.readAsDataURL(file);
  }

  function setBusy(on) {
    ui.apply.disabled = on;
    ui.skip.disabled  = on;
    ui.apply.textContent = on ? 'Memproses…' : 'Pakai hasil potong';
  }

  async function applyCrop() {
    setBusy(true);

    var opts = session.opts;
    var index = session.order[session.pos];
    var original = session.originals[index];

    var canvas = session.cropper.getCroppedCanvas({
      maxWidth: opts.max,
      maxHeight: opts.max,
      imageSmoothingEnabled: true,
      imageSmoothingQuality: 'high'
    });

    if (!canvas) { setBusy(false); return; }

    var blob = await encode(canvas, original.type, opts.maxBytes);
    if (!blob) { setBusy(false); return; }

    var file = new File([blob], baseName(original.name) + extOf(blob.type), {
      type: blob.type,
      lastModified: Date.now()
    });

    var data = store.get(session.input);
    data.results[index] = file;
    data.sizes[index]   = canvas.width + ' × ' + canvas.height;

    nextImage(null);
  }

  /* Lanjut ke gambar berikutnya. Bila skipFile diisi, berkas asli dipakai apa adanya. */
  function nextImage(skipFile) {
    if (skipFile) {
      var idx  = session.order[session.pos];
      var data = store.get(session.input);
      data.results[idx] = skipFile;
      data.sizes[idx]   = null;
    }

    session.pos++;

    if (session.pos >= session.order.length) {
      var input = session.input;
      closeSession();
      commit(input);
      return;
    }

    setBusy(false);
    loadCurrent();
  }

  function cancelSession() {
    var input = session.input;
    var data  = store.get(input);

    // Gambar yang belum sempat diproses dikembalikan ke kondisi sebelum sesi ini.
    // Untuk unggahan baru artinya dibuang, untuk "Potong ulang" hasil lama dipertahankan.
    var prev = session.prev;
    session.order.slice(session.pos).forEach(function (i) {
      data.results[i] = prev[i] || null;
      if (!prev[i]) data.sizes[i] = null;
    });

    closeSession();
    compact(input);
    commit(input);
  }

  function closeSession() {
    if (session && session.cropper) session.cropper.destroy();
    session = null;
    ui.scrim.classList.remove('open');
    ui.img.removeAttribute('src');
    document.body.style.overflow = '';
    setBusySafe();
  }

  function setBusySafe() {
    ui.apply.disabled = false;
    ui.skip.disabled  = false;
    ui.apply.textContent = 'Pakai hasil potong';
  }

  /* ============================================================
     Sinkronisasi ke input file + pratinjau
     ============================================================ */
  function compact(input) {
    var data = store.get(input);
    var keep = [];
    data.results.forEach(function (f, i) {
      if (f) keep.push(i);
    });
    data.originals = keep.map(function (i) { return data.originals[i]; });
    data.sizes     = keep.map(function (i) { return data.sizes[i]; });
    data.results   = keep.map(function (i) { return data.results[i]; });
  }

  function commit(input) {
    var data = store.get(input);
    var dt = new DataTransfer();
    data.results.forEach(function (f) { if (f) dt.items.add(f); });
    input.files = dt.files;
    renderPreview(input);
  }

  function renderPreview(input) {
    if (!input.id) return;
    var box = document.querySelector('[data-crop-preview="' + input.id + '"]');
    if (!box) return;

    (box._urls || []).forEach(URL.revokeObjectURL);
    box._urls = [];

    var data = store.get(input);
    if (!data || !data.results.length) {
      box.innerHTML = '';
      return;
    }

    var html = '<p class="crop-note">' + data.results.length +
      ' gambar siap diunggah. Tekan <b>Unggah gambar</b> untuk menyimpannya.</p><div class="crop-preview">';

    data.results.forEach(function (f, i) {
      var url = URL.createObjectURL(f);
      box._urls.push(url);
      var dim = data.sizes[i] ? data.sizes[i] + ' · ' : '';
      html +=
        '<div class="crop-thumb">' +
          '<div class="ph"><img src="' + url + '" alt=""><span class="no">' + (i + 1) + '</span></div>' +
          '<div class="meta"><b>' + escapeHtml(f.name) + '</b><span>' + dim + kb(f.size) + '</span></div>' +
          '<div class="acts">' +
            '<button class="btn btn-line btn-sm" type="button" data-crop-recrop="' + i + '">Potong ulang</button>' +
            '<button class="btn btn-ghost btn-sm" type="button" data-crop-remove="' + i + '" style="color:var(--red)">Hapus</button>' +
          '</div>' +
        '</div>';
    });

    box.innerHTML = html + '</div>';

    box.querySelectorAll('[data-crop-recrop]').forEach(function (b) {
      b.addEventListener('click', function () {
        startSession(input, [parseInt(b.dataset.cropRecrop, 10)]);
      });
    });

    box.querySelectorAll('[data-crop-remove]').forEach(function (b) {
      b.addEventListener('click', function () {
        var i = parseInt(b.dataset.cropRemove, 10);
        var d = store.get(input);
        d.originals.splice(i, 1);
        d.results.splice(i, 1);
        d.sizes.splice(i, 1);
        commit(input);
      });
    });
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ============================================================
     Pemasangan
     ============================================================ */
  function attach(input) {
    if (input._cropReady) return;
    input._cropReady = true;

    input.addEventListener('change', function () {
      var picked = Array.prototype.slice.call(input.files || [])
        .filter(function (f) { return /^image\//.test(f.type); });

      if (!picked.length) return;

      store.set(input, {
        originals: picked,
        results: new Array(picked.length).fill(null),
        sizes: new Array(picked.length).fill(null)
      });

      startSession(input, picked.map(function (_, i) { return i; }));
    });
  }

  document.querySelectorAll('input[type="file"][data-crop]').forEach(attach);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && session) cancelSession();
  });
})();
