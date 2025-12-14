<?php
session_start();
require_once 'database/db.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = "Username and password are required.";
    } else {
        // 1. Check if the user is an Admin
        $stmt_admin = $conn->prepare("SELECT id, username, password FROM admins WHERE username = ?");
        $stmt_admin->bind_param("s", $username);
        $stmt_admin->execute();
        $result_admin = $stmt_admin->get_result();

        if ($result_admin->num_rows === 1) {
            $admin = $result_admin->fetch_assoc();
            // NOTE: This is a plain-text password comparison, matching your existing login scripts.
            // For production, you should use password_hash() and password_verify().
            if ($password === $admin['password']) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                header("Location: admin/admin_dashboard.php");
                exit;
            }
        }

        // 2. If not an Admin, check if the user is a Judge
        $stmt_judge = $conn->prepare("SELECT id, username, password FROM judges WHERE username = ?");
        $stmt_judge->bind_param("s", $username);
        $stmt_judge->execute();
        $result_judge = $stmt_judge->get_result();

        if ($result_judge->num_rows === 1) {
            $judge = $result_judge->fetch_assoc();
            if ($password === $judge['password']) {
                // Judge is authenticated, but needs to select a competition.
                // Store temporary auth info and redirect to selection page.
                $_SESSION['judge_auth_id'] = $judge['id'];
                $_SESSION['judge_auth_username'] = $judge['username'];
                header("Location: judge/select_competition.php");
                exit;
            }
        }

        // 3. If neither, set an error message
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Pageant Evaluation System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-container">
    <div class="login-box">
        <h2>System Login</h2>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="POST" action="login.php">
            <label>Username</label>
            <input type="text" name="username" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <button type="submit" class="btn">Login</button>
        </form>
    </div>
</div>
</body>
</html>