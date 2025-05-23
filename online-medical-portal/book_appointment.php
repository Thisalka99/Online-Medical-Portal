<?php
require_once 'auth.php'; // Handles session start and authentication
require_once 'db.php';   // Database connection

// Ensure the user is a patient
if ($_SESSION['role'] !== 'patient') {
    $_SESSION['message'] = "You do not have permission to access this page.";
    header("Location: dashboard.php");
    exit();
}

$errors = [];
$success_message = "";
$doctor_id_selected = "";
$appointment_date_selected = "";
$symptoms_selected = "";

// Fetch doctors for the dropdown
$doctors = [];
$sqlDoctors = "SELECT id, username FROM users WHERE role = 'doctor' ORDER BY username ASC";
$resultDoctors = $conn->query($sqlDoctors);
if ($resultDoctors && $resultDoctors->num_rows > 0) {
    while ($row = $resultDoctors->fetch_assoc()) {
        $doctors[] = $row;
    }
} else {
    $errors[] = "No doctors available at the moment. Please try again later.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $doctor_id_selected = trim($_POST['doctor_id']);
    $appointment_date_selected = trim($_POST['appointment_date']);
    $symptoms_selected = trim($_POST['symptoms']);
    $patient_id = $_SESSION['user_id'];

    // Validate input
    if (empty($doctor_id_selected)) {
        $errors[] = "Please select a doctor.";
    }
    if (empty($appointment_date_selected)) {
        $errors[] = "Appointment date and time are required.";
    } else {
        // Check if the date is in the future
        $current_datetime = new DateTime();
        $selected_datetime = new DateTime($appointment_date_selected);
        if ($selected_datetime <= $current_datetime) {
            $errors[] = "Appointment date and time must be in the future.";
        }
    }
    if (empty($symptoms_selected)) {
        $errors[] = "Please describe your symptoms.";
    }

    // If validation passes, insert new appointment
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, symptoms, status) VALUES (?, ?, ?, ?, 'pending')");
        if (!$stmt) {
            $errors[] = "Database error (prepare insert): " . $conn->error;
        } else {
            $stmt->bind_param("iiss", $patient_id, $doctor_id_selected, $appointment_date_selected, $symptoms_selected);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Appointment booked successfully!";
                header("Location: view_appointments.php");
                exit();
            } else {
                $errors[] = "Booking failed. Please try again. Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}
// No $conn->close(); here if it's used later in the HTML part (e.g., fetching doctors again on error)
// However, for this specific structure, doctors are fetched before POST handling.
// If there were errors, the form would redisplay.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book an Appointment</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h2>Book an Appointment</h2>
            <p><a href="dashboard.php" class="button-link">Back to Dashboard</a></p>
        </header>

        <?php if (!empty($success_message)): ?>
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

        <form action="book_appointment.php" method="post">
            <div>
                <label for="doctor_id">Select Doctor:</label>
                <select id="doctor_id" name="doctor_id" required>
                    <option value="">-- Select a Doctor --</option>
                    <?php foreach ($doctors as $doctor): ?>
                        <option value="<?php echo htmlspecialchars($doctor['id']); ?>" <?php echo ($doctor_id_selected == $doctor['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($doctor['username']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="appointment_date">Appointment Date & Time:</label>
                <input type="datetime-local" id="appointment_date" name="appointment_date" value="<?php echo htmlspecialchars($appointment_date_selected); ?>" required>
            </div>
            <div>
                <label for="symptoms">Symptoms:</label>
                <textarea id="symptoms" name="symptoms" rows="5" required><?php echo htmlspecialchars($symptoms_selected); ?></textarea>
            </div>
            <div>
                <button type="submit">Book Appointment</button>
            </div>
        </form>
        <footer>
            <p>&copy; <?php echo date("Y"); ?> Online Medical Portal</p>
        </footer>
    </div>
</body>
</html>
<?php $conn->close(); // Close connection after page is rendered ?>
