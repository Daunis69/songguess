<?php
session_start();

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "Please fill in both fields.";
    } else {
        $usersFile = 'users.json';
        if (!file_exists($usersFile)) {
            $error = "No users found. Please sign up first.";
        } else {
            $users = json_decode(file_get_contents($usersFile), true);
            $found = false;
            foreach ($users as $user) {
                if ($user['username'] === $username) {
                    $found = true;
                    if (password_verify($password, $user['password'])) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        header("Location: game.php");
                        exit;
                    } else {
                        $error = "Incorrect username or password.";
                    }
                    break;
                }
            }
            if (!$found) {
                $error = "Incorrect username or password.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Songuess</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="container">
        <h2>Login</h2>
        <form method="post" action="login.php">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <?php if (!empty($error)): ?>
            <div class="error" style="color:red;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div class="link">
            <span>Don't have an account?<a href="signup.php"> Sign up</a></span>
        </div>
    </div>
</body>
</html>