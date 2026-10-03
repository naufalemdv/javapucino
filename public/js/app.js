/* ============================================================
   Javapucino POS — helper UI global
   ============================================================ */
(function () {
  'use strict';

  /* Sidebar off-canvas (HP) */
  document.addEventListener('click', function (e) {
    const nav = e.target.closest('[data-act="nav"]');
    if (nav) { document.body.classList.toggle('nav-open'); return; }

    if (document.body.classList.contains('nav-open') && !e.target.closest('.rail')) {
      document.body.classList.remove('nav-open');
    }
  });

  /* Modal */
  const scrim = document.getElementById('scrim');
  const modal = document.getElementById('modal');

  window.openModal = function (html, wide) {
    if (!scrim) return;
    modal.className = 'modal' + (wide ? ' wide' : '');
    modal.innerHTML = html;
    scrim.classList.add('open');
  };
  window.closeModal = function () { scrim && scrim.classList.remove('open'); };

  if (scrim) {
    scrim.addEventListener('click', function (e) {
      if (e.target.id === 'scrim' || e.target.closest('[data-act="close"]')) window.closeModal();
    });
  }

    /* Revisi: popup konfirmasi keluar (kasir diingatkan tutup shift) */
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-act="do-logout"]')) {
      const f = document.getElementById('logout-form');
      if (f) f.submit();
      return;
    }

    const btn = e.target.closest('[data-act="logout"]');
    if (!btn) return;

    const isKasir = btn.dataset.kasir === '1';
    const shiftOpen = btn.dataset.shiftOpen === '1';

    let title = 'Keluar dari sistem?';
    let body = 'Anda akan keluar dari akun ini.';
    let extra = '';

    if (isKasir && shiftOpen) {
      title = 'Sudah tutup shift?';
      body = 'Shift Anda masih terbuka. Sebaiknya tutup shift dulu supaya rekonsiliasi kas tercatat sebelum keluar.';
      extra = '<a class="btn btn-black" href="' + btn.dataset.closeUrl + '">Tutup shift</a>';
    } else if (isKasir) {
      body = 'Shift Anda sudah ditutup. Anda bisa keluar sekarang.';
    }

    window.openModal(
      '<div class="modal-h">' +
        '<div><h3>' + title + '</h3></div>' +
        '<button class="x" type="button" data-act="close">✕</button>' +
      '</div>' +
      '<div class="modal-b"><p style="margin:0;line-height:1.6">' + body + '</p></div>' +
      '<div class="modal-f">' +
        '<button class="btn btn-line" type="button" data-act="close">Kembali</button>' +
        extra +
        '<button class="btn btn-red" type="button" data-act="do-logout">Keluar</button>' +
      '</div>'
    );
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      window.closeModal();
      document.body.classList.remove('nav-open', 'cart-open');
    }
  });

  /* Toast sederhana */
  window.toast = function (msg) {
    const old = document.getElementById('flash');
    if (old) old.remove();
    const el = document.createElement('div');
    el.className = 'flash';
    el.id = 'flash';
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 2600);
  };

  /* Format Rupiah */
  window.rp  = n => 'Rp ' + Math.round(n || 0).toLocaleString('id-ID');
  window.rp0 = n => Math.round(n || 0).toLocaleString('id-ID');

  /* Input uang: ketik angka, tampil dengan titik ribuan */
  document.querySelectorAll('input[data-money]').forEach(function (inp) {
    const target = inp.dataset.money ? document.getElementById(inp.dataset.money) : null;
    const sync = () => {
      const raw = String(inp.value).replace(/\D/g, '');
      if (target) target.value = raw || 0;
      inp.value = raw ? parseInt(raw, 10).toLocaleString('id-ID') : '';
    };
    sync();
    inp.addEventListener('input', sync);
  });

  /* Jam berjalan */
  const clock = document.getElementById('clock');
  if (clock) {
    setInterval(function () {
      const d = new Date();
      clock.textContent = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    }, 10000);
  }

  /* Konfirmasi form hapus */
  document.addEventListener('submit', function (e) {
    const msg = e.target.dataset.confirm;
    if (msg && !confirm(msg)) e.preventDefault();
  });

  /* Auto-submit form filter saat select berubah */
  document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', () => el.form.submit());
  });
})();
