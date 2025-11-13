    <?php include "check_login.php"; ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Profil - Konekin</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body>
        <?php include "dashboard-sidebar.php"; ?>
        <div class="main-content">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h4>Profil Saya</h4>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Nama Lengkap:</strong> <?php echo $_SESSION['full_name']; ?>
                            </div>
                            <div class="mb-3">
                                <strong>Email:</strong> <?php echo $_SESSION['email']; ?>
                            </div>
                            <div class="mb-3">
                                <strong>Tipe Akun:</strong> 
                                <?php echo $_SESSION['user_type'] === 'umkm' ? 'UMKM/Bisnis' : 'Creative Worker'; ?>
                            </div>
                            <a href="dashboard.php" class="btn btn-primary">Kembali ke Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>