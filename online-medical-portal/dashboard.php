<?php
// auth.php will start the session and redirect if not logged in.
require_once 'auth.php'; // Ensures user is logged in and sets $current_user_id, $current_username, $current_user_role
require_once 'db.php';   // For any database interactions if needed (though username and role are from session)

// $current_username and $current_user_role are available from auth.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h2>Dashboard</h2>
            <p>Welcome, <?php echo htmlspecialchars($current_username); ?>!</p>
            <p>Your Role: <?php echo htmlspecialchars(ucfirst($current_user_role)); ?></p>
        </header>

        <nav class="dashboard-nav">
            <ul>
                <?php if ($current_user_role == 'patient'): ?>
                    <li><a href="book_appointment.php" class="button">Book New Appointment</a></li>
                    <li><a href="view_appointments.php" class="button">View My Appointments</a></li>
                    <li><a href="upload_records.php" class="button">Upload Medical Records</a></li>
                    <li><a href="prescription.php" class="button">View My Prescriptions</a></li> 
                <?php elseif ($current_user_role == 'doctor'): ?>
                    <li><a href="view_appointments.php" class="button">View Assigned Appointments</a></li>
                    <li><a href="prescription.php" class="button">Manage Prescriptions</a></li> 
                <?php endif; ?>
                <li><a href="logout.php" class="button logout-button">Logout</a></li>
            </ul>
        </nav>

        <main>
            <!-- Informational message or other content can go here -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="message success-message">
                    <p><?php echo htmlspecialchars($_SESSION['message']); ?></p>
                    <?php unset($_SESSION['message']); ?>
                </div>
            <?php endif; ?>
            <p>Select an option from the navigation menu to proceed.</p>
        </main>

        <footer>
            <p>&copy; <?php echo date("Y"); ?> Online Medical Portal</p>
        </footer>
    </div>
</body>
</html>
