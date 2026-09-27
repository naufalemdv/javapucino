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
