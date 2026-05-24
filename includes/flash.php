<?php if (!empty($flash)): ?>
    <div class="alert alert-<?= e($flash['type']); ?>-custom alert-dismissible fade show mb-4" role="alert" data-auto-dismiss="5000">
        <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'danger' ? 'x-circle' : 'info-circle'); ?>"></i>
        <span><?= e($flash['message']); ?></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
