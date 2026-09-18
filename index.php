<?php
session_start();
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];

    if (empty($username) || empty($password) || empty($role)) {
        $error = "Semua field harus diisi.";
    } else {
        $table = $role == 'admin' ? 'admin' : ($role == 'karyawan' ? 'karyawan' : 'nasabah');
        $id_column = $role == 'admin' ? 'id_admin' : ($role == 'karyawan' ? 'id_karyawan' : 'id_nasabah');

        try {
            $stmt = $pdo->prepare("SELECT $id_column, password FROM $table WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && $password === $user['password']) {
                $_SESSION['user_id'] = $user[$id_column];
                $_SESSION['role'] = $role;
                header("Location: dashboard_$role.php");
                exit;
            } else {
                $error = "Username atau password salah.";
            }
        } catch (PDOException $e) {
            $error = "Gagal login: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Koperasi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #28a745, #ffc107); /* hijau ke kuning */
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            border-radius: 15px;
            background: #fff;
            padding: 30px;
        }
        .logo {
            width: 80px;
            margin-bottom: 15px;
        }
        .btn-custom {
            background: #28a745;
            color: #fff;
            font-weight: bold;
            transition: 0.3s;
        }
        .btn-custom:hover {
            background: #218838;
            color: #fff;
        }
        h2 {
            color: #28a745;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card shadow">
                    <div class="text-center">
                        <img src="assets/img/logo.jpg" alt="Logo" class="logo">
                        <h2 class="mb-4">Login Koperasi</h2>
                    </div>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" name="username" id="username" class="form-control" required>
                            <div class="invalid-feedback">Username wajib diisi.</div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" name="password" id="password" class="form-control" required>
                            <div class="invalid-feedback">Password wajib diisi.</div>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select name="role" id="role" class="form-select" required>
                                <option value="">Pilih Role</option>
                                <option value="admin">Admin</option>
                                <option value="karyawan">Karyawan</option>
                                <option value="nasabah">Nasabah</option>
                            </select>
                            <div class="invalid-feedback">Role wajib dipilih.</div>
                        </div>
                        <button type="submit" class="btn btn-custom w-100">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
