/* =============================================
   KOPERASI SIMPAN PINJAM — MAIN JS
   ============================================= */

/* ─── FIX HANGING BACKDROPS/MODALS ─── */
const cleanupOverlays = () => {
  document.querySelectorAll('.modal-backdrop').forEach(e => e.remove());
  document.body.classList.remove('modal-open');
  document.body.style.overflow = 'auto';
  document.body.style.paddingRight = '';
};

document.addEventListener('DOMContentLoaded', () => {
  
  cleanupOverlays(); // Bersihkan overlay sisa setiap kali halaman dimuat

  /* ─── NAVBAR SCROLL EFFECT ─── */
  const navbarPublic = document.querySelector('.navbar-public');
  if (navbarPublic) {
    const onScroll = () => {
      navbarPublic.classList.toggle('scrolled', window.scrollY > 20);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ─── SIDEBAR TOGGLE (Mobile) ─── */
  const sidebarEl  = document.getElementById('appSidebar');
  const overlayEl  = document.getElementById('sidebarOverlay');
  const toggleBtn  = document.getElementById('sidebarToggle');

  const openSidebar  = () => { sidebarEl?.classList.add('open');    overlayEl?.classList.add('active'); };
  const closeSidebar = () => { sidebarEl?.classList.remove('open'); overlayEl?.classList.remove('active'); };

  toggleBtn?.addEventListener('click', () => {
    sidebarEl?.classList.contains('open') ? closeSidebar() : openSidebar();
  });
  overlayEl?.addEventListener('click', closeSidebar);

  /* ─── ACTIVE SIDEBAR LINK ─── */
  const currentPath = window.location.pathname;
  document.querySelectorAll('.sidebar-link').forEach(link => {
    if (link.getAttribute('href') === currentPath) {
      link.classList.add('active');
    }
  });

  /* ─── FILE UPLOAD PREVIEW ─── */
  document.querySelectorAll('.file-upload-area').forEach(area => {
    const input    = area.querySelector('input[type=file]');
    const nameEl   = area.querySelector('.file-name');
    const iconEl   = area.querySelector('i');
    const textEl   = area.querySelector('.upload-text');

    area.addEventListener('click', () => input?.click());
    area.addEventListener('dragover', e => { e.preventDefault(); area.classList.add('dragover'); });
    area.addEventListener('dragleave', () => area.classList.remove('dragover'));
    area.addEventListener('drop', e => {
      e.preventDefault();
      area.classList.remove('dragover');
      if (e.dataTransfer.files[0] && input) {
        const dt = new DataTransfer();
        dt.items.add(e.dataTransfer.files[0]);
        input.files = dt.files;
        input.dispatchEvent(new Event('change'));
      }
    });

    input?.addEventListener('change', () => {
      const file = input.files[0];
      if (file) {
        area.classList.add('has-file');
        if (nameEl) nameEl.textContent = file.name;
        if (iconEl) { iconEl.className = 'bi bi-check-circle-fill'; iconEl.style.color = 'var(--accent-green)'; }
        if (textEl) textEl.textContent = 'File terpilih:';
      } else {
        area.classList.remove('has-file');
        if (nameEl) nameEl.textContent = '';
        if (iconEl) { iconEl.className = 'bi bi-cloud-arrow-up'; iconEl.style.color = ''; }
        if (textEl) textEl.textContent = 'Klik atau seret file ke sini';
      }
    });
  });

  /* ─── SUBMIT BUTTON LOADING STATE ─── */
  document.querySelectorAll('form[data-loading]').forEach(form => {
    form.addEventListener('submit', () => {
      const btn = form.querySelector('[type=submit]');
      if (btn) {
        const orig = btn.innerHTML;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status"></span>Memproses...`;
        btn.disabled = true;
      }
    });
  });

  /* ─── CONFIRM MODAL (generic) ─── */
  const confirmModal = document.getElementById('confirmModal');
  if (confirmModal) {
    confirmModal.addEventListener('show.bs.modal', event => {
      const btn = event.relatedTarget;
      if (!btn) return;
      const id      = btn.getAttribute('data-id');
      const action  = btn.getAttribute('data-action-type');
      const msg     = btn.getAttribute('data-message');
      const url     = btn.getAttribute('data-action');
      const iconEl  = confirmModal.querySelector('.modal-icon');
      const typeMap = { approve: 'success', reject: 'danger', delete: 'danger', toggle: 'warning' };

      if (iconEl) {
        iconEl.className = `modal-icon ${typeMap[action] || 'warning'}`;
        const icnMap = { approve: 'bi-check-circle', reject: 'bi-x-circle', delete: 'bi-trash', toggle: 'bi-arrow-repeat' };
        iconEl.innerHTML = `<i class="bi ${icnMap[action] || 'bi-question-circle'}"></i>`;
      }

      const msgEl = confirmModal.querySelector('.confirm-message');
      if (msgEl) msgEl.textContent = msg || 'Lanjutkan aksi ini?';

      const idInput  = document.getElementById('confirmId');
      const actInput = document.getElementById('confirmAction');
      if (idInput)  idInput.value  = id;
      if (actInput) actInput.value = action;

      const formEl = confirmModal.querySelector('form');
      if (formEl && url) formEl.setAttribute('action', url);
    });
  }

  /* ─── BADGE STATUS HELPER ─── */
  document.querySelectorAll('[data-badge-status]').forEach(el => {
    const status = (el.getAttribute('data-badge-status') || '').toLowerCase();
    const map = {
      'menunggu verifikasi': 'badge-menunggu',
      'menunggu konfirmasi': 'badge-menunggu',
      'menunggu review': 'badge-menunggu',
      'disetujui': 'badge-disetujui',
      'diterima': 'badge-diterima',
      'dicairkan': 'badge-dicairkan',
      'ditolak': 'badge-ditolak',
      'lunas': 'badge-lunas',
      'nonaktif': 'badge-nonaktif',
    };
    el.classList.add('badge-status', map[status] || 'badge-nonaktif');
  });

  /* ─── PASSWORD TOGGLE ─── */
  document.querySelectorAll('.input-icon-right[data-toggle-pw]').forEach(icon => {
    icon.addEventListener('click', () => {
      const target = document.getElementById(icon.getAttribute('data-toggle-pw'));
      if (!target) return;
      const isPass = target.type === 'password';
      target.type = isPass ? 'text' : 'password';
      icon.className = `bi ${isPass ? 'bi-eye-slash' : 'bi-eye'} input-icon-right`;
      icon.setAttribute('data-toggle-pw', icon.getAttribute('data-toggle-pw'));
    });
  });

  /* ─── MULTISTEP FORM ─── */
  const stepForm = document.getElementById('stepForm');
  if (stepForm) {
    let current = 0;
    const steps      = stepForm.querySelectorAll('.form-step');
    const indicators = document.querySelectorAll('.step-ind-item');
    const nextBtns   = stepForm.querySelectorAll('[data-step="next"]');
    const prevBtns   = stepForm.querySelectorAll('[data-step="prev"]');
    const progressBar= document.getElementById('stepProgress');

    const showStep = (idx) => {
      steps.forEach((s, i) => s.classList.toggle('active', i === idx));
      indicators.forEach((ind, i) => {
        ind.classList.toggle('active', i === idx);
        ind.classList.toggle('done',   i < idx);
      });
      if (progressBar) {
        progressBar.style.width = `${Math.round(((idx + 1) / steps.length) * 100)}%`;
        progressBar.setAttribute('aria-valuenow', Math.round(((idx + 1) / steps.length) * 100));
      }
    };

    nextBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        const currentStep = steps[current];
        const inputs = currentStep.querySelectorAll('input[required], select[required], textarea[required]');
        let valid = true;
        inputs.forEach(inp => {
          if (!inp.value.trim()) { inp.classList.add('is-invalid'); valid = false; }
          else inp.classList.remove('is-invalid');
        });
        if (!valid) return;
        if (current < steps.length - 1) { current++; showStep(current); window.scrollTo(0, 0); }
      });
    });

    prevBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        if (current > 0) { current--; showStep(current); window.scrollTo(0, 0); }
      });
    });

    showStep(0);
  }

  /* ─── AUTO-DISMISS ALERTS ─── */
  document.querySelectorAll('.alert[data-auto-dismiss]').forEach(alert => {
    const delay = parseInt(alert.getAttribute('data-auto-dismiss')) || 4000;
    setTimeout(() => {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
      bsAlert?.close();
    }, delay);
  });
});

/* ─── FIX SAFARI/BFCACHE BACK BUTTON ─── */
window.addEventListener('pageshow', (e) => {
  if (e.persisted) {
    cleanupOverlays();
  }
});

  /* ─── SMOOTH SCROLL for anchor links ─── */
  document.querySelectorAll('a[href^="#"]').forEach(link => {
    link.addEventListener('click', e => {
      const target = document.querySelector(link.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

});
