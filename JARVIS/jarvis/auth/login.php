<?php
session_start();
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $usernameEsc = mysqli_real_escape_string($conn, $username);
    $res = mysqli_query($conn, "SELECT * FROM users WHERE username='$usernameEsc'");

    if ($res && mysqli_num_rows($res) > 0) {
        $user = mysqli_fetch_assoc($res);
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            header("Location: ../dashboard.php");
            exit();
        } else {
            $error = "Galat password.";
        }
    } else {
        $error = "User nahi mila. Pehle register karo.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>J.A.R.V.I.S - Login</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-box">
        <h1 class="logo-text">J.A.R.V.I.S</h1>
        <h2>Login</h2>
        <?php if ($error): ?><p class="error-msg"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
        <form method="POST" action="login.php">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <p>New here? <a href="register.php">Register here</a></p>
    </div>
</body>
</html>
