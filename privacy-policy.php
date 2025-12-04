<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kebijakan Privasi - Konekin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3E7FD5;
            --primary-dark: #2A5EA8;
        }
        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        .navbar-brand {
            font-weight: bold;
            color: var(--primary-color) !important;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        .policy-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 3rem;
            margin: 2rem 0;
        }
        .policy-header {
            border-bottom: 3px solid var(--primary-color);
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }
        .policy-section {
            margin-bottom: 2.5rem;
        }
        .policy-section h3 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1.4rem;
        }
        .policy-section h4 {
            color: #333;
            font-weight: 600;
            margin-top: 1.5rem;
            margin-bottom: 0.8rem;
            font-size: 1.1rem;
        }
        .policy-section p, .policy-section li {
            color: #555;
            line-height: 1.8;
            margin-bottom: 0.8rem;
        }
        .policy-section ul {
            padding-left: 1.5rem;
        }
        .policy-section ul li {
            margin-bottom: 0.5rem;
        }
        .highlight-box {
            background: #f0f7ff;
            border-left: 4px solid var(--primary-color);
            padding: 1.5rem;
            margin: 1.5rem 0;
            border-radius: 5px;
        }
        .last-updated {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 0.8rem 1.2rem;
            border-radius: 5px;
            margin-bottom: 2rem;
            font-size: 0.9rem;
        }
        .section-number {
            color: var(--primary-color);
            font-weight: bold;
            margin-right: 0.5rem;
        }
        .back-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
            color: var(--primary-dark);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-link me-2"></i>Konekin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#how-it-works">Cara Kerja</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#features">Keunggulan</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#about">Tentang Kami</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Masuk</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary btn-sm ms-2" href="register-choice.php">Daftar</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row">
            <div class="col-12">
                <a href="javascript:history.back()" class="back-link">
                    <i class="fas fa-arrow-left me-2"></i>Kembali
                </a>
            </div>
        </div>

        <div class="policy-container">
            <div class="policy-header">
                <h1 class="text-center mb-3" style="color: var(--primary-color);">
                    <i class="fas fa-shield-alt me-3"></i>Kebijakan Privasi
                </h1>
                <p class="text-center text-muted">Konekin - Platform Kolaborasi UMKM & Creative Workers</p>
            </div>

            <div class="last-updated">
                <i class="fas fa-calendar-alt me-2"></i>
                <strong>Terakhir diperbarui:</strong> Desember 2025
            </div>

            <div class="highlight-box">
                <p class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Penting:</strong> Kebijakan Privasi ini menjelaskan bagaimana Konekin mengumpulkan, menggunakan, dan melindungi informasi pribadi Anda. Dengan menggunakan layanan kami, Anda menyetujui praktik yang dijelaskan dalam kebijakan ini.
                </p>
            </div>

            <!-- Section 1 -->
            <div class="policy-section">
                <h3><span class="section-number">1.</span>Pengumpulan Informasi</h3>
                <p>Kami mengumpulkan informasi yang Anda berikan secara langsung saat mendaftar, membuat profil, menggunakan layanan, atau berkomunikasi dengan kami, termasuk:</p>
                <ul>
                    <li><strong>Data identitas</strong> (nama, email, foto profil)</li>
                    <li><strong>Informasi profesional</strong> (riwayat pekerjaan, pendidikan, keahlian)</li>
                    <li><strong>Data kontak</strong> dan jaringan profesional</li>
                    <li><strong>Konten</strong> yang Anda unggah atau bagikan</li>
                    <li><strong>Data transaksi dan pembayaran</strong> (untuk layanan berbayar)</li>
                </ul>
            </div>

            <!-- Section 2 -->
            <div class="policy-section">
                <h3><span class="section-number">2.</span>Penggunaan Informasi</h3>
                <p>Informasi yang kami kumpulkan digunakan untuk:</p>
                <ul>
                    <li>Menyediakan, mempersonalisasi, dan meningkatkan layanan</li>
                    <li>Memfasilitasi koneksi profesional dan peluang kerja</li>
                    <li>Mengkomunikasikan informasi penting tentang layanan</li>
                    <li>Menjaga keamanan dan keamanan platform</li>
                    <li>Melakukan analitik dan pengembangan produk</li>
                    <li>Mematuhi kewajiban hukum</li>
                </ul>
            </div>

            <!-- Section 3 -->
            <div class="policy-section">
                <h3><span class="section-number">3.</span>Berbagi Informasi</h3>
                <p>Kami dapat membagikan informasi Anda dalam keadaan berikut:</p>
                <ul>
                    <li><strong>Dengan pengguna lain:</strong> Profil Anda dapat dilihat oleh pengguna lain sesuai dengan pengaturan privasi Anda</li>
                    <li><strong>Dengan klien/freelancer:</strong> Untuk memfasilitasi proyek dan kolaborasi</li>
                    <li><strong>Penyedia layanan pihak ketiga:</strong> Yang mendukung operasi kami (dengan perlindungan kerahasiaan)</li>
                    <li><strong>Kepatuhan hukum:</strong> Saat diperlukan oleh hukum atau proses hukum</li>
                    <li><strong>Perubahan bisnis:</strong> Dalam merger, akuisisi, atau penjualan aset</li>
                </ul>
            </div>

            <!-- Section 4 -->
            <div class="policy-section">
                <h3><span class="section-number">4.</span>Keamanan Data</h3>
                <p>Kami menerapkan langkah-langkah keamanan teknis dan organisasi yang wajar untuk melindungi data pribadi Anda dari akses, pengungkapan, atau penggunaan yang tidak sah.</p>
                <div class="highlight-box">
                    <p class="mb-0">
                        <i class="fas fa-lock me-2"></i>
                        Kami menggunakan enkripsi SSL/TLS untuk melindungi data Anda selama transmisi dan menyimpan informasi sensitif dengan enkripsi di server kami.
                    </p>
                </div>
            </div>

            <!-- Section 5 -->
            <div class="policy-section">
                <h3><span class="section-number">5.</span>Hak Anda</h3>
                <p>Anda memiliki hak untuk:</p>
                <ul>
                    <li>Mengakses dan memperbaiki data pribadi Anda</li>
                    <li>Menghapus akun dan data Anda</li>
                    <li>Membatasi atau menolak pemrosesan data tertentu</li>
                    <li>Mengekspor data Anda</li>
                    <li>Menarik persetujuan untuk pemrosesan data</li>
                </ul>
                <p>Untuk menggunakan hak-hak ini, silakan hubungi kami melalui email di <a href="mailto:info@konekin.id" style="color: var(--primary-color);">info@konekin.id</a></p>
            </div>

            <!-- Section 6 -->
            <div class="policy-section">
                <h3><span class="section-number">6.</span>Cookie dan Teknologi Pelacakan</h3>
                <p>Kami menggunakan cookie dan teknologi serupa untuk mengingat preferensi, menganalisis lalu lintas, dan menyesuaikan pengalaman pengguna. Anda dapat mengatur browser Anda untuk menolak cookie, namun beberapa fitur mungkin tidak berfungsi dengan baik.</p>
            </div>

            <!-- Section 7 -->
            <div class="policy-section">
                <h3><span class="section-number">7.</span>Perubahan Kebijakan</h3>
                <p>Kami dapat memperbarui kebijakan privasi ini dari waktu ke waktu. Perubahan signifikan akan dikomunikasikan melalui platform atau email. Penggunaan berkelanjutan layanan kami setelah perubahan berarti Anda menyetujui kebijakan yang diperbarui.</p>
            </div>

            <!-- Contact Section -->
            <div class="highlight-box">
                <h4 class="mb-3"><i class="fas fa-envelope me-2"></i>Hubungi Kami</h4>
                <p class="mb-2">Jika Anda memiliki pertanyaan tentang Kebijakan Privasi ini, silakan hubungi kami:</p>
                <p class="mb-1"><i class="fas fa-envelope me-2"></i>Email: <a href="mailto:info@konekin.id" style="color: var(--primary-color);">info@konekin.id</a></p>
                <p class="mb-0"><i class="fas fa-phone me-2"></i>Telepon: (021) 1234-5678</p>
            </div>

            <div class="text-center mt-4">
                <a href="terms-conditions.php" class="btn btn-outline-primary">
                    <i class="fas fa-file-contract me-2"></i>Lihat Syarat & Ketentuan
                </a>
            </div>
        </div>
    </div>

    <footer class="bg-white py-4 mt-5 border-top">
        <div class="container text-center text-muted">
            <p class="mb-0">&copy; 2025 Konekin. All rights reserved.</p>
            <div class="mt-2">
                <a href="privacy-policy.php" class="text-muted me-3">Kebijakan Privasi</a>
                <a href="terms-conditions.php" class="text-muted">Syarat & Ketentuan</a>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>