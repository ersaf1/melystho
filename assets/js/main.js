/* KOPERASI SIMPAN PINJAM - MAIN JS */

const cleanupOverlays = () => {
  document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
  document.body.classList.remove('modal-open');
  document.body.style.overflow = '';
  document.body.style.paddingRight = '';
};

document.addEventListener('DOMContentLoaded', () => {
  cleanupOverlays();

  const navbarPublic = document.querySelector('.navbar-public');
  if (navbarPublic) {
    const onScroll = () => {
      navbarPublic.classList.toggle('scrolled', window.scrollY > 20);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  const sidebarEl = document.getElementById('appSidebar');
  const overlayEl = document.getElementById('sidebarOverlay');
  const toggleBtn = document.getElementById('sidebarToggle');

  const openSidebar = () => {
    sidebarEl?.classList.add('open');
    overlayEl?.classList.add('active');
  };
  const closeSidebar = () => {
    sidebarEl?.classList.remove('open');
    overlayEl?.classList.remove('active');
  };

  toggleBtn?.addEventListener('click', () => {
    sidebarEl?.classList.contains('open') ? closeSidebar() : openSidebar();
  });
  overlayEl?.addEventListener('click', closeSidebar);

  const currentPath = window.location.pathname;
  document.querySelectorAll('.sidebar-link').forEach(link => {
    if (link.getAttribute('href') === currentPath) {
      link.classList.add('active');
    }
  });

  document.querySelectorAll('.file-upload-area').forEach(area => {
    const input = area.querySelector('input[type=file]');
    const nameEl = area.querySelector('.file-name');
    const iconEl = area.querySelector('i');
    const textEl = area.querySelector('.upload-text');

    area.addEventListener('click', () => input?.click());
    area.addEventListener('dragover', e => {
      e.preventDefault();
      area.classList.add('dragover');
    });
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
        if (nameEl) {
          nameEl.textContent = file.name;
          nameEl.style.display = 'block';
        }
        if (iconEl) {
          iconEl.className = 'bi bi-check-circle-fill';
          iconEl.style.color = 'var(--accent-green)';
        }
        if (textEl) textEl.textContent = 'File terpilih:';
      } else {
        area.classList.remove('has-file');
        if (nameEl) {
          nameEl.textContent = '';
          nameEl.style.display = 'none';
        }
        if (iconEl) {
          iconEl.className = 'bi bi-cloud-arrow-up';
          iconEl.style.color = '';
        }
        if (textEl) textEl.textContent = 'Klik atau seret file ke sini';
      }
    });
  });

  document.querySelectorAll('form[data-loading]').forEach(form => {
    form.addEventListener('submit', () => {
      const btn = form.querySelector('[type=submit]');
      if (btn) {
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Memproses...';
        btn.disabled = true;
      }
    });
  });

  const confirmModal = document.getElementById('confirmModal');
  if (confirmModal) {
    confirmModal.addEventListener('show.bs.modal', event => {
      const btn = event.relatedTarget;
      if (!btn) return;
      const id = btn.getAttribute('data-id');
      const action = btn.getAttribute('data-action-type');
      const msg = btn.getAttribute('data-message');
      const url = btn.getAttribute('data-action');
      const iconEl = confirmModal.querySelector('.modal-icon');
      const typeMap = { approve: 'success', reject: 'danger', delete: 'danger', toggle: 'warning' };
      const iconMap = { approve: 'bi-check-circle', reject: 'bi-x-circle', delete: 'bi-trash', toggle: 'bi-arrow-repeat' };

      if (iconEl) {
        iconEl.className = `modal-icon ${typeMap[action] || 'warning'}`;
        iconEl.innerHTML = `<i class="bi ${iconMap[action] || 'bi-question-circle'}"></i>`;
      }

      const msgEl = confirmModal.querySelector('.confirm-message');
      if (msgEl) msgEl.textContent = msg || 'Lanjutkan aksi ini?';

      const idInput = document.getElementById('confirmId');
      const actInput = document.getElementById('confirmAction');
      if (idInput) idInput.value = id;
      if (actInput) actInput.value = action;

      const formEl = confirmModal.querySelector('form');
      if (formEl && url) formEl.setAttribute('action', url);
    });
  }

  document.querySelectorAll('[data-badge-status]').forEach(el => {
    const status = (el.getAttribute('data-badge-status') || '').toLowerCase();
    const map = {
      'menunggu verifikasi': 'badge-menunggu',
      'menunggu konfirmasi': 'badge-menunggu',
      'menunggu review': 'badge-menunggu',
      'belum dibayar': 'badge-menunggu',
      'disetujui': 'badge-disetujui',
      'diterima': 'badge-diterima',
      'dibayar': 'badge-diterima',
      'dicairkan': 'badge-dicairkan',
      'ditolak': 'badge-ditolak',
      'lunas': 'badge-lunas',
      'nonaktif': 'badge-nonaktif',
    };
    el.classList.add('badge-status', map[status] || 'badge-nonaktif');
  });

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

  const stepForm = document.getElementById('stepForm');
  if (stepForm) {
    let current = 0;
    const steps = stepForm.querySelectorAll('.form-step');
    const indicators = document.querySelectorAll('.step-ind-item');
    const nextBtns = stepForm.querySelectorAll('[data-step="next"]');
    const prevBtns = stepForm.querySelectorAll('[data-step="prev"]');
    const progressBar = document.getElementById('stepProgress');

    const showStep = idx => {
      steps.forEach((step, i) => step.classList.toggle('active', i === idx));
      indicators.forEach((ind, i) => {
        ind.classList.toggle('active', i === idx);
        ind.classList.toggle('done', i < idx);
      });
      if (progressBar) {
        const progress = Math.round(((idx + 1) / steps.length) * 100);
        progressBar.style.width = `${progress}%`;
        progressBar.setAttribute('aria-valuenow', progress);
      }
    };

    nextBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        const currentStep = steps[current];
        const inputs = currentStep.querySelectorAll('input[required], select[required], textarea[required]');
        let valid = true;
        inputs.forEach(input => {
          if (!input.value.trim()) {
            input.classList.add('is-invalid');
            valid = false;
          } else {
            input.classList.remove('is-invalid');
          }
        });
        if (!valid) return;
        if (current < steps.length - 1) {
          current++;
          showStep(current);
          window.scrollTo(0, 0);
        }
      });
    });

    prevBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        if (current > 0) {
          current--;
          showStep(current);
          window.scrollTo(0, 0);
        }
      });
    });

    showStep(0);
  }

  document.querySelectorAll('.alert[data-auto-dismiss]').forEach(alert => {
    const delay = parseInt(alert.getAttribute('data-auto-dismiss'), 10) || 4000;
    setTimeout(() => {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
      bsAlert?.close();
    }, delay);
  });

  document.querySelectorAll('[data-table-search]').forEach(input => {
    const selectors = (input.getAttribute('data-table-search') || '')
      .split(',')
      .map(selector => selector.trim())
      .filter(Boolean);

    const filterTables = () => {
      const keyword = input.value.trim().toLowerCase();
      selectors.forEach(selector => {
        const table = document.querySelector(selector);
        if (!table) return;
        table.querySelectorAll('tbody tr').forEach(row => {
          if (row.querySelector('.empty-state')) return;
          row.hidden = keyword !== '' && !row.textContent.toLowerCase().includes(keyword);
        });
      });
    };

    input.addEventListener('input', filterTables);
  });

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

window.addEventListener('pageshow', e => {
  if (e.persisted) {
    cleanupOverlays();
  }
});

document.addEventListener('hidden.bs.modal', cleanupOverlays);
