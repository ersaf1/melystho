<?php
$config = $config ?? (require __DIR__ . '/../config/config.php');
$appName = e($config['app']['name']);
?>
<footer class="site-footer" id="kontak">
    <div class="container">
        <div class="row g-4 pb-4">
            <!-- Brand -->
            <div class="col-lg-4 col-md-6">
                <div class="footer-brand">
                    <span style="width:32px;height:32px;background:linear-gradient(135deg,var(--accent-green),#15803d);border-radius:6px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-bank2" style="color:#fff;font-size:.95rem;"></i>
                    </span>
                    <?= $appName; ?>
                </div>
                <p>Solusi simpanan dan pinjaman koperasi yang aman, transparan, dan mudah dikelola secara digital.</p>
                <div class="footer-social">
                    <a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                    <a href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                    <a href="#" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-2 col-md-3 col-6">
                <h6>Navigasi</h6>
                <a href="/">Beranda</a>
                <a href="/#syarat">Syarat Anggota</a>
                <a href="/#produk">Produk</a>
                <a href="/#keunggulan">Keunggulan</a>
                <a href="/register.php">Daftar Anggota</a>
                <a href="/login.php">Login</a>
            </div>

            <!-- Products -->
            <div class="col-lg-2 col-md-3 col-6">
                <h6>Produk</h6>
                <a href="#">Simpanan Pokok</a>
                <a href="#">Simpanan Wajib</a>
                <a href="#">Simpanan Sukarela</a>
                <a href="#">Pinjaman Anggota</a>
            </div>

            <!-- Contact -->
            <div class="col-lg-4 col-md-6">
                <h6>Kontak</h6>
                <p class="d-flex align-items-start gap-2 mb-2">
                    <i class="bi bi-geo-alt mt-1" style="color:var(--accent-green);flex-shrink:0"></i>
                    Jl. Koperasi Maju No. 1, Kota, Provinsi, 00000
                </p>
                <p class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-telephone" style="color:var(--accent-green)"></i>
                    (021) 1234-5678
                </p>
                <p class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-envelope" style="color:var(--accent-green)"></i>
                    info@koperasi.example
                </p>
                <p class="d-flex align-items-center gap-2 mb-0">
                    <i class="bi bi-clock" style="color:var(--accent-green)"></i>
                    Sen–Jum: 08.00 – 16.00 WIB
                </p>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>&copy; <?= date('Y'); ?> <?= $appName; ?>. Hak Cipta Dilindungi.</span>
            <span>Dibuat dengan <i class="bi bi-heart-fill" style="color:#ef4444;font-size:.75rem"></i> untuk anggota koperasi</span>
        </div>
    </div>
</footer>

<?php if (!empty($extra_js)): ?>
    <?= $extra_js; ?>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/main.js"></script>
</body>
</html>
