<?php
session_start();
require_once 'db.php';

$errors = [];
$username = "";

// Display and unset any session messages
if (isset($_SESSION['message'])) {
    $success_message = $_SESSION['message'];
    unset($_SESSION['message']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Validate input
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    // If validation passes, attempt to log in
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        if (!$stmt) {
            $errors[] = "Database error (prepare): " . $conn->error;
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    // Password is correct, start session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];

                    header("Location: dashboard.php");
                    exit();
                } else {
                    $errors[] = "Invalid username or password.";
                }
            } else {
                $errors[] = "Invalid username or password.";
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h2>Login</h2>
        <?php if (isset($success_message)): ?>
            <div class="success-message">
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="errors">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form action="login.php" method="post">
            <div>
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <button type="submit">Login</button>
            </div>
            <p>Don't have an account? <a href="register.php">Register here</a>.</p>
        </form>
    </div>
</body>
</html>
