<?php
session_start();
require_once 'db.php';

$errors = [];
$username = "";
$role = ""; // To retain selected role on error

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];

    // Validate input
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }
    if (empty($confirm_password)) {
        $errors[] = "Confirm Password is required.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    if (empty($role)) {
        $errors[] = "Role is required.";
    } elseif (!in_array($role, ['patient', 'doctor'])) {
        $errors[] = "Invalid role selected.";
    }

    // Check if username already exists
    if (empty($errors) && !empty($username)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        if (!$stmt) {
            $errors[] = "Database error (prepare): " . $conn->error;
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors[] = "Username already exists.";
            }
            $stmt->close();
        }
    }

    // If validation passes, insert new user
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        if (!$stmt) {
            $errors[] = "Database error (prepare insert): " . $conn->error;
        } else {
            $stmt->bind_param("sss", $username, $hashed_password, $role);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Registration successful. Please login.";
                header("Location: login.php");
                exit();
            } else {
                $errors[] = "Registration failed. Please try again. Error: " . $stmt->error;
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
    <title>User Registration</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h2>Register</h2>
        <?php if (!empty($errors)): ?>
            <div class="errors">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form action="register.php" method="post">
            <div>
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <div>
                <label for="role">Role:</label>
                <select id="role" name="role" required>
                    <option value="">-- Select Role --</option>
                    <option value="patient" <?php echo ($role === 'patient') ? 'selected' : ''; ?>>Patient</option>
                    <option value="doctor" <?php echo ($role === 'doctor') ? 'selected' : ''; ?>>Doctor</option>
                </select>
            </div>
            <div>
                <button type="submit">Register</button>
            </div>
            <p>Already have an account? <a href="login.php">Login here</a>.</p>
        </form>
    </div>
</body>
</html>
