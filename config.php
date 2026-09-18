<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

define('DB_HOST', 'localhost');
define('DB_NAME', 'koperasi');
define('DB_USER', 'root');
define('DB_PASS', '');
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'aulamustikanryh03@gmail.com');
define('SMTP_PASS', 'xfuriwosakslbrnh');
define('SMTP_PORT', 587);
define('REPORT_DIR', 'reports/');

// Inisialisasi koneksi PDO
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Fungsi untuk mengirim email
function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_USER, 'Koperasi Cilacap');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Gagal mengirim email: {$mail->ErrorInfo}";
    }
}
?>