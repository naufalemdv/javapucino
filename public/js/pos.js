/* ============================================================
   Javapucino POS — keranjang & pembayaran (FR-06 s.d. FR-12)
   ============================================================ */
(function () {
  'use strict';

  const KEY  = 'jv-cart';
  const tax  = () => (window.JV && window.JV.taxPercent) || 0;
  let cart   = [];

  try { cart = JSON.parse(sessionStorage.getItem(KEY) || '[]'); } catch (e) { cart = []; }

  const $  = s => document.querySelector(s);
  const el = h => { const d = document.createElement('div'); d.innerHTML = h.trim(); return d.firstElementChild; };
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const sub    = () => cart.reduce((s, i) => s + i.price * i.qty, 0);
  const taxAmt = () => Math.round(sub() * tax() / 100);
  const total  = () => sub() + taxAmt();
  const qty    = () => cart.reduce((s, i) => s + i.qty, 0);

  const save = () => { try { sessionStorage.setItem(KEY, JSON.stringify(cart)); } catch (e) {} };

  /* ---------- Render ---------- */
  function render() {
    const box = $('#cartItems');

    if (!cart.length) {
      box.innerHTML = '<div class="cart-empty"><div class="big">🧾</div><b>Belum ada pesanan</b>' +
        '<p>Ketuk menu di sebelah kiri untuk menambah item ke keranjang.</p></div>';
    } else {
      box.innerHTML = cart.map(i => `
        <div class="ci">
          ${i.image ? `<img class="pthumb" src="${esc(i.image)}" alt="">` : `<div class="emo">${esc(i.icon) || '☕'}</div>`}
          <div class="info"><b>${esc(i.name)}</b><small>${rp(i.price)} × ${i.qty}</small></div>
          <div class="right">
            <div class="sub">${rp(i.price * i.qty)}</div>
            <div class="stepper">
              <button type="button" data-dec="${i.product_id}">−</button>
              <span class="q">${i.qty}</span>
              <button type="button" data-inc="${i.product_id}">+</button>
            </div>
          </div>
        </div>`).join('');
    }

    $('#sumSub').textContent   = rp(sub());
    $('#sumTax').textContent   = rp(taxAmt());
    $('#sumTotal').textContent = rp(total());
    $('#btnPay').disabled      = !cart.length;

    const count = $('#cartCount');
    count.style.display = cart.length ? '' : 'none';
    count.textContent   = qty() + ' item';

    $('#btnClear').style.display = cart.length ? '' : 'none';
    $('#custWrap').style.display = cart.length ? '' : 'none';

    const fab = $('#cartFab');
    if (fab) {
      fab.style.display = cart.length ? '' : 'none';
      $('#fabCount').textContent = qty();
      $('#fabTotal').textContent = rp(total());
    }

    const pill = document.getElementById('railCart');
    if (pill) { pill.style.display = cart.length ? '' : 'none'; pill.textContent = qty(); }

    document.querySelectorAll('[data-badge]').forEach(b => {
      const item = cart.find(i => i.product_id === Number(b.dataset.badge));
      b.style.display = item ? '' : 'none';
      if (item) b.textContent = item.qty;
    });

    save();
  }

  /* ---------- Aksi keranjang ---------- */
  function add(btn) {
    const id    = Number(btn.dataset.add);
    const stock = Number(btn.dataset.stock);
    const item  = cart.find(i => i.product_id === id);

    if (item) {
      if (item.qty >= stock) return toast('Stok ' + btn.dataset.name + ' tinggal ' + stock);
      item.qty++;
    } else {
      cart.push({
        product_id: id,
        name : btn.dataset.name,
        icon : btn.dataset.icon,
        image: btn.dataset.image,
        price: Number(btn.dataset.price),
        stock: stock,
        qty  : 1,
      });
    }
    render();
  }

  function step(id, d) {
    const item = cart.find(i => i.product_id === id);
    if (!item) return;
    if (d > 0 && item.qty >= item.stock) return toast('Stok ' + item.name + ' tinggal ' + item.stock);
    item.qty += d;
    if (item.qty <= 0) cart = cart.filter(i => i.product_id !== id);
    render();
  }

  document.addEventListener('click', function (e) {
    /* Popup QRIS besar: tutup / buka */
    if (e.target.id === 'qrisZoom' || e.target.closest('[data-act="qris-zoom-close"]')) return closeQrisZoom();
    if (e.target.closest('[data-act="qris-zoom"]')) return openQrisZoom();

    const addBtn = e.target.closest('[data-add]');
    if (addBtn) return add(addBtn);

    const inc = e.target.closest('[data-inc]');
    if (inc) return step(Number(inc.dataset.inc), 1);

    const dec = e.target.closest('[data-dec]');
    if (dec) return step(Number(dec.dataset.dec), -1);

    if (e.target.closest('#btnClear')) {
      if (cart.length && confirm('Kosongkan keranjang?')) { cart = []; render(); }
      return;
    }
    if (e.target.closest('[data-act="open-cart"]'))  document.body.classList.add('cart-open');
    if (e.target.closest('[data-act="close-cart"]')) document.body.classList.remove('cart-open');
    if (e.target.closest('#btnPay')) openPay();
  });

  /* ---------- Pembayaran (FR-09 s.d. FR-11) ---------- */
  let pay = { method: 'cash', paid: 0 };

  function qrSVG() {
    let cells = '';
    const n = 21, seed = String(window.JV.qrisNmid || 'x').length;
    for (let y = 0; y < n; y++) for (let x = 0; x < n; x++) {
      const corner = (x < 7 && y < 7) || (x > n - 8 && y < 7) || (x < 7 && y > n - 8);
      if (corner) continue;
      if (((x * 7 + y * 13 + seed * 3) % 5) < 2) cells += `<rect x="${x * 8}" y="${y * 8}" width="8" height="8"/>`;
    }
    const eye = (ox, oy) =>
      `<rect x="${ox}" y="${oy}" width="56" height="56" fill="none" stroke="#B0141A" stroke-width="8"/>` +
      `<rect x="${ox + 16}" y="${oy + 16}" width="24" height="24"/>`;
    return `<svg viewBox="0 0 168 168" width="100%" height="100%" fill="#B0141A" role="img" aria-label="Kode QRIS">
      ${cells}${eye(0, 0)}${eye(112, 0)}${eye(0, 112)}</svg>`;
  }

  /* Isi kotak QRIS: gambar asli kalau sudah diunggah, pola cadangan kalau belum */
  function qrisInner() {
    return window.JV.qrisImage
      ? `<img src="${esc(window.JV.qrisImage)}" alt="QRIS" style="width:100%;height:100%;object-fit:contain">`
      : qrSVG();
  }

  /* Popup QRIS besar, tampil di atas popup pembayaran */
  function openQrisZoom() {
    if (document.getElementById('qrisZoom')) return;
    document.body.appendChild(el(`
      <div class="qris-zoom" id="qrisZoom">
        <div class="qris-zoom-box">
          <div class="qris-zoom-img">${qrisInner()}</div>
          <div class="qris-zoom-name">${esc(window.JV.storeName)}</div>
          <div class="qris-zoom-nmid">NMID ${esc(window.JV.qrisNmid)}</div>
          <div class="qris-zoom-total">Total ${rp(total())}</div>
          <button type="button" class="btn btn-red btn-block" data-act="qris-zoom-close">← Kembali ke pembayaran</button>
        </div>
      </div>`));
  }

  function closeQrisZoom() {
    const z = document.getElementById('qrisZoom');
    if (z) z.remove();
  }

  /* Esc menutup popup QRIS dulu, popup pembayaran tetap terbuka */
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && document.getElementById('qrisZoom')) {
      e.stopImmediatePropagation();
      closeQrisZoom();
    }
  }, true);

  function payHTML() {
    const t      = total();
    const change = pay.paid - t;
    const ok     = pay.method === 'qris' || pay.paid >= t;
    const round  = n => Math.ceil(t / n) * n;
    const opts   = [...new Set([t, round(5000), round(10000), round(20000), round(50000), 100000])]
      .filter(v => v >= t).slice(0, 6);
    const cust   = ($('#custName').value || '').trim();

    return `
    <div class="modal-h">
      <div><h3>Pembayaran</h3><div class="sub">${qty()} item${cust ? ' · ' + esc(cust) : ''}</div></div>
      <button class="x" type="button" data-act="close">✕</button>
    </div>
    <div class="modal-b">
      <div class="total-box" style="margin-top:0"><span>Total tagihan</span><b>${rp(t)}</b></div>
      <div class="pay-tabs">
        <button type="button" class="pay-tab ${pay.method === 'cash' ? 'on' : ''}" data-pm="cash"><span class="ic">💵</span>Tunai</button>
        <button type="button" class="pay-tab ${pay.method === 'qris' ? 'on' : ''}" data-pm="qris"><span class="ic">📱</span>QRIS</button>
      </div>
      ${pay.method === 'cash' ? `
        <div class="field">
          <label>Uang diterima</label>
          <input class="inp num" id="paidInp" inputmode="numeric" placeholder="0"
                 value="${pay.paid ? rp0(pay.paid) : ''}" style="font-size:19px;height:52px;font-weight:800">
          <div class="quick-cash">
            ${opts.map((v, i) => `<button type="button" class="qc ${i === 0 ? 'exact' : ''}" data-cash="${v}">${i === 0 ? 'Uang pas' : rp0(v)}</button>`).join('')}
          </div>
        </div>
        <div class="change-box ${change < 0 ? 'short' : ''}">
          <span>${change < 0 ? 'Kurang' : 'Kembalian'}</span><b>${rp(Math.abs(change))}</b>
        </div>`
      : `
        <div class="qris">
          <div class="qris-frame" data-act="qris-zoom" style="cursor:zoom-in" title="Klik untuk memperbesar">${qrisInner()}</div>
          <button type="button" class="btn btn-line btn-sm" data-act="qris-zoom" style="margin:10px 0 12px">🔍 Perbesar QRIS</button>
          <div style="font-weight:800;font-family:var(--f-d);font-size:15px">${esc(window.JV.storeName)}</div>
          <div class="mini">NMID ${esc(window.JV.qrisNmid)} · Semua bank &amp; e-wallet</div>
          <div class="mini" style="margin-top:10px">Tunjukkan QR ke pelanggan, lalu tekan Selesaikan setelah pembayaran masuk.</div>
        </div>`}
    </div>
    <div class="modal-f">
      <button class="btn btn-line" type="button" data-act="close">Batal</button>
      <button class="btn btn-red" type="button" id="btnFinish" ${ok ? '' : 'disabled'} style="flex:1.5">Selesaikan transaksi</button>
    </div>`;
  }

  function openPay() {
    if (!cart.length) return;
    pay = { method: 'cash', paid: 0 };
    openModal(payHTML());
    bindPay();
  }

  function refreshPay() { openModal(payHTML()); bindPay(); }

  function bindPay() {
    document.querySelectorAll('[data-pm]').forEach(b => b.addEventListener('click', () => {
      pay.method = b.dataset.pm;
      if (pay.method === 'qris') pay.paid = total();
      refreshPay();
    }));

    document.querySelectorAll('[data-cash]').forEach(b => b.addEventListener('click', () => {
      pay.paid = Number(b.dataset.cash);
      refreshPay();
    }));

    const inp = document.getElementById('paidInp');
    if (inp) {
      inp.addEventListener('input', () => {
        const raw = String(inp.value).replace(/\D/g, '');
        pay.paid = Number(raw || 0);
        inp.value = raw ? Number(raw).toLocaleString('id-ID') : '';

        const t = total(), ch = pay.paid - t;
        const box = document.querySelector('.change-box');
        if (box) {
          box.classList.toggle('short', ch < 0);
          box.querySelector('span').textContent = ch < 0 ? 'Kurang' : 'Kembalian';
          box.querySelector('b').textContent    = rp(Math.abs(ch));
        }
        const fin = document.getElementById('btnFinish');
        if (fin) fin.disabled = pay.paid < t;
      });
      inp.focus();
    }

    const finish = document.getElementById('btnFinish');
    if (finish) finish.addEventListener('click', submitCheckout);
  }

  function submitCheckout() {
    const form = $('#checkoutForm');
    $('#fMethod').value = pay.method;
    $('#fPaid').value   = pay.method === 'qris' ? total() : pay.paid;
    $('#fCust').value   = ($('#custName').value || '').trim();

    $('#fItems').innerHTML = cart.map((i, idx) =>
      `<input type="hidden" name="items[${idx}][product_id]" value="${i.product_id}">` +
      `<input type="hidden" name="items[${idx}][qty]" value="${i.qty}">`).join('');

    document.getElementById('btnFinish').disabled = true;
    sessionStorage.removeItem(KEY);
    form.submit();
  }

  render();
})();