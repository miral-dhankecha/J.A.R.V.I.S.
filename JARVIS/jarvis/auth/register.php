<?php
session_start();
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if ($name === "" || $username === "" || $password === "") {
        $error = "Sabhi fields bharna zaroori hai.";
    } else {
        $usernameEsc = mysqli_real_escape_string($conn, $username);
        $check = mysqli_query($conn, "SELECT id FROM users WHERE username='$usernameEsc'");

        if (mysqli_num_rows($check) > 0) {
            $error = "Ye username pehle se registered hai. Login karo.";
        } else {
            $nameEsc = mysqli_real_escape_string($conn, $name);
            $hashedPass = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (name, username, password) VALUES ('$nameEsc','$usernameEsc','$hashedPass')";
            if (mysqli_query($conn, $sql)) {
                $_SESSION['user_id'] = mysqli_insert_id($conn);
                $_SESSION['user_name'] = $name;
                header("Location: ../dashboard.php");
                exit();
            } else {
                jarvis_log_error("Register failed: " . mysqli_error($conn));
                $error = "Registration failed. Try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>J.A.R.V.I.S - Register</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-box">
        <h1 class="logo-text">J.A.R.V.I.S</h1>
        <h2>Register</h2>
        <?php if ($error): ?><p class="error-msg"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
        <form method="POST" action="register.php">
            <input type="text" name="name" placeholder="Your Name" required>
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Register</button>
        </form>
        <p>Already have an account? <a href="login.php">Login here</a></p>
    </div>
</body>
</html>
