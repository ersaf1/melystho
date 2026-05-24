<?php
require_once __DIR__ . '/config/helpers.php';
$config     = require __DIR__ . '/config/config.php';
$page_title = 'Beranda';
?>
<?php require __DIR__ . '/includes/header.php'; ?>
<?php require __DIR__ . '/includes/navbar.php'; ?>

<!-- ═══════════════════════════════════════════
     HERO SECTION
═══════════════════════════════════════════ -->
<section class="hero-section" id="beranda">
    <div class="container position-relative" style="z-index:1">
        <div class="row align-items-center g-5">
            <!-- Left: Text -->
            <div class="col-lg-6">
                <div class="hero-badge">
                    <i class="bi bi-patch-check-fill"></i>
                    Terpercaya &amp; Terverifikasi
                </div>
                <h1 class="hero-headline">
                    Kelola <span>Simpanan &amp; Pinjaman</span> Koperasi dengan Mudah
                </h1>
                <p class="hero-sub">
                    Platform digital koperasi yang aman, transparan, dan cepat. Pantau saldo, ajukan pinjaman, dan bayar angsuran kapan saja dari mana saja.
                </p>
                <div class="hero-cta">
                    <a href="/register.php" class="btn-hero-primary" id="heroDaftarBtn">
                        <i class="bi bi-person-plus-fill me-2"></i>Daftar Jadi Anggota
                    </a>
                    <a href="/login.php" class="btn-hero-secondary" id="heroLoginBtn">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login Anggota
                    </a>
                </div>
                <div class="hero-trust">
                    <div class="trust-item"><i class="bi bi-shield-fill-check"></i>Data Aman &amp; Terenkripsi</div>
                    <div class="trust-item"><i class="bi bi-graph-up-arrow"></i>Transparan &amp; Real-time</div>
                    <div class="trust-item"><i class="bi bi-lightning-charge-fill"></i>Proses Cepat</div>
                </div>
            </div>

            <!-- Right: Financial Card Mockup -->
            <div class="col-lg-6 d-flex justify-content-center">
                <div class="hero-card-mockup">
                    <div class="card-header-mock">
                        <div>
                            <div class="card-title-mock">Ringkasan Akun</div>
                            <div style="font-size:.7rem;color:rgba(255,255,255,.5);margin-top:1px">Anggota Aktif</div>
                        </div>
                        <span class="status-badge"><i class="bi bi-check-circle-fill me-1" style="font-size:.7rem"></i>Terverifikasi</span>
                    </div>

                    <div class="mock-stat">
                        <div class="mock-stat-icon green"><i class="bi bi-piggy-bank-fill"></i></div>
                        <div>
                            <div class="mock-stat-label">Total Simpanan</div>
                            <div class="mock-stat-value">Rp 4.750.000</div>
                        </div>
                    </div>
                    <div class="mock-stat">
                        <div class="mock-stat-icon amber"><i class="bi bi-cash-stack"></i></div>
                        <div>
                            <div class="mock-stat-label">Pinjaman Aktif</div>
                            <div class="mock-stat-value">Rp 10.000.000</div>
                        </div>
                    </div>
                    <div class="mock-stat">
                        <div class="mock-stat-icon blue"><i class="bi bi-calendar-check"></i></div>
                        <div>
                            <div class="mock-stat-label">Angsuran Bulan Ini</div>
                            <div class="mock-stat-value">Rp 1.050.000</div>
                        </div>
                    </div>

                    <div style="margin-top:.85rem;padding-top:.85rem;border-top:1px solid rgba(255,255,255,.12);display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:.7rem;color:rgba(255,255,255,.5)">Terakhir update</span>
                        <span style="font-size:.7rem;color:rgba(255,255,255,.7);font-weight:600"><?= date('d M Y'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════
     KEUNGGULAN SECTION
═══════════════════════════════════════════ -->
<section class="py-5 bg-section-alt" id="keunggulan">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-label mx-auto"><i class="bi bi-stars"></i>Keunggulan Kami</div>
            <h2 class="section-title">Mengapa Memilih Koperasi Kami?</h2>
            <p class="section-sub mx-auto mt-2">Kami hadir untuk memudahkan pengelolaan keuangan koperasi Anda secara digital, aman, dan terpercaya.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon primary"><i class="bi bi-person-check-fill"></i></div>
                    <h5>Daftar Mudah &amp; Cepat</h5>
                    <p>Proses pendaftaran anggota sepenuhnya online, cukup isi form dan upload dokumen dari rumah.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon green"><i class="bi bi-shield-check"></i></div>
                    <h5>Simpanan Tercatat Transparan</h5>
                    <p>Setiap transaksi simpanan tercatat real-time, bisa dipantau kapan saja melalui dashboard.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon amber"><i class="bi bi-send-check-fill"></i></div>
                    <h5>Ajukan Pinjaman Online</h5>
                    <p>Pengajuan pinjaman dilakukan secara online, proses persetujuan dilakukan oleh admin dengan cepat.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon blue"><i class="bi bi-bar-chart-line-fill"></i></div>
                    <h5>Pantau Riwayat Angsuran</h5>
                    <p>Lihat jadwal dan riwayat pembayaran angsuran secara lengkap dan terstruktur di satu tempat.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════
     SYARAT ANGGOTA
═══════════════════════════════════════════ -->
<section class="py-5 bg-section" id="syarat">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-5">
                <div class="section-label"><i class="bi bi-clipboard-check"></i>Persyaratan</div>
                <h2 class="section-title mb-3">Syarat Menjadi Anggota</h2>
                <p class="section-sub">Bergabung mudah! Penuhi persyaratan berikut dan mulai nikmati layanan koperasi digital kami.</p>
                <a href="/register.php" class="btn-primary-custom mt-3 d-inline-flex" id="syaratDaftarBtn">
                    <i class="bi bi-arrow-right-circle-fill"></i>Daftar Sekarang
                </a>
            </div>
            <div class="col-lg-7">
                <div class="row g-3">
                    <?php
                    $syarat = [
                        ['WNI Usia Minimal 18 Tahun', 'Calon anggota adalah Warga Negara Indonesia yang telah berusia 18 tahun atau sudah menikah.'],
                        ['Memiliki KTP yang Berlaku', 'Wajib melampirkan scan atau foto KTP asli yang masih berlaku untuk verifikasi identitas.'],
                        ['Mengisi Formulir Lengkap', 'Isi semua data diri, kontak, pekerjaan, dan informasi rekening secara lengkap dan akurat.'],
                        ['Menyetujui Aturan Koperasi', 'Membaca dan menyetujui anggaran dasar, anggaran rumah tangga, serta tata tertib koperasi.'],
                    ];
                    foreach ($syarat as $i => $item):
                    ?>
                    <div class="col-sm-6">
                        <div class="syarat-card">
                            <div class="syarat-num"><?= $i + 1; ?></div>
                            <div>
                                <h6><?= $item[0]; ?></h6>
                                <p><?= $item[1]; ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════
     PRODUK
═══════════════════════════════════════════ -->
<section class="py-5 bg-section-alt" id="produk">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-label green mx-auto"><i class="bi bi-box-seam"></i>Produk Kami</div>
            <h2 class="section-title">Simpanan &amp; Pinjaman</h2>
            <p class="section-sub mx-auto mt-2">Kami menyediakan berbagai produk simpanan dan pinjaman yang fleksibel untuk kebutuhan anggota.</p>
        </div>

        <div class="row g-4">
            <!-- Simpanan -->
            <div class="col-md-6 col-lg-3">
                <div class="product-card simpanan">
                    <span class="product-badge simpanan-badge">Simpanan</span>
                    <div class="feature-icon green mb-3"><i class="bi bi-wallet2"></i></div>
                    <h5>Simpanan Pokok</h5>
                    <p>Simpanan awal yang dibayarkan satu kali saat pertama kali menjadi anggota koperasi.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="product-card simpanan">
                    <span class="product-badge simpanan-badge">Simpanan</span>
                    <div class="feature-icon green mb-3"><i class="bi bi-piggy-bank"></i></div>
                    <h5>Simpanan Wajib</h5>
                    <p>Simpanan rutin yang dibayarkan setiap bulan sesuai ketentuan koperasi oleh seluruh anggota.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="product-card simpanan">
                    <span class="product-badge simpanan-badge">Simpanan</span>
                    <div class="feature-icon green mb-3"><i class="bi bi-safe2"></i></div>
                    <h5>Simpanan Sukarela</h5>
                    <p>Simpanan tambahan yang dapat disetor kapan saja sesuai kemampuan dan keinginan anggota.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="product-card pinjaman">
                    <span class="product-badge pinjaman-badge">Pinjaman</span>
                    <div class="feature-icon amber mb-3"><i class="bi bi-cash-coin"></i></div>
                    <h5>Pinjaman Anggota</h5>
                    <p>Fasilitas pinjaman dengan bunga ringan, tenor fleksibel, dan proses persetujuan yang transparan.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════
     ALUR PENGGUNAAN (STEPPER)
═══════════════════════════════════════════ -->
<section class="py-5 bg-section">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-label amber mx-auto"><i class="bi bi-signpost-2"></i>Cara Bergabung</div>
            <h2 class="section-title">Alur Penggunaan Layanan</h2>
            <p class="section-sub mx-auto mt-2">Lima langkah mudah untuk mulai menikmati layanan koperasi digital kami.</p>
        </div>

        <div class="stepper">
            <?php
            $steps = [
                ['bi-person-plus-fill',   'Daftar Anggota',     'Isi form pendaftaran online lengkap dengan dokumen KTP dan foto diri.'],
                ['bi-shield-check',       'Verifikasi Admin',   'Tim admin memverifikasi data dan dokumen yang Anda submitted.'],
                ['bi-piggy-bank-fill',    'Setor Simpanan',     'Lakukan setoran simpanan pokok, wajib, atau sukarela melalui aplikasi.'],
                ['bi-send-fill',          'Ajukan Pinjaman',    'Ajukan pinjaman online setelah akun terverifikasi dan simpanan cukup.'],
                ['bi-calendar2-check',    'Bayar Angsuran',     'Lakukan pembayaran angsuran rutin dan pantau perkembangan pinjaman.'],
            ];
            foreach ($steps as $i => $step):
            ?>
            <div class="step">
                <div class="step-circle">
                    <i class="bi <?= $step[0]; ?>"></i>
                </div>
                <div class="step-label">
                    <strong style="display:block;font-size:.8rem;color:var(--text-primary)"><?= $step[1]; ?></strong>
                    <span style="font-size:.72rem;color:var(--text-muted)"><?= $step[2]; ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════
     CTA BANNER
═══════════════════════════════════════════ -->
<section class="py-5" style="background:linear-gradient(135deg,var(--primary-dark),var(--primary))">
    <div class="container text-center">
        <h2 class="section-title mb-3" style="color:#fff">Siap Bergabung dengan Koperasi Kami?</h2>
        <p style="color:rgba(255,255,255,.75);max-width:500px;margin:0 auto 2rem;font-size:.95rem">
            Mulai perjalanan finansial Anda bersama ribuan anggota yang telah mempercayakan simpanan dan pinjamannya kepada kami.
        </p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="/register.php" class="btn-hero-primary" id="ctaDaftarBtn">
                <i class="bi bi-person-plus-fill me-2"></i>Daftar Sekarang — Gratis
            </a>
            <a href="/login.php" class="btn-hero-secondary" id="ctaLoginBtn">
                <i class="bi bi-box-arrow-in-right me-2"></i>Sudah Punya Akun
            </a>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
