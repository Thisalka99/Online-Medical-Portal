<?php
require_once 'auth.php'; // Handles session start, authentication
require_once 'db.php';   // Database connection

// This page is primarily for patients, but a doctor could also be linked here.
// For now, we'll focus on the patient access.
if ($_SESSION['role'] !== 'patient') {
    // If a doctor tries to access, they should use prescription.php for editing
    // or view_appointments.php for a summary.
    // For simplicity, redirect non-patients.
    $_SESSION['message'] = "This page is for patients to view their prescriptions.";
    header("Location: dashboard.php");
    exit();
}

$errors = [];
$prescription_details = null;
$appointment_id = null;

// Validate and fetch appointment_id from GET request
if (isset($_GET['appointment_id']) && filter_var($_GET['appointment_id'], FILTER_VALIDATE_INT)) {
    $appointment_id = (int)$_GET['appointment_id'];
} else {
    $_SESSION['message'] = "Invalid or missing appointment ID.";
    header("Location: view_appointments.php");
    exit();
}

// Fetch prescription details, ensuring it belongs to the logged-in patient
$stmt = $conn->prepare("SELECT pr.prescription_text, pr.created_at as prescription_date, 
                               a.appointment_date, 
                               d.username as doctor_name 
                        FROM prescriptions pr 
                        JOIN appointments a ON pr.appointment_id = a.id 
                        JOIN users d ON pr.doctor_id = d.id 
                        WHERE pr.appointment_id = ? AND a.patient_id = ?");
if (!$stmt) {
    $errors[] = "Database error (prepare prescription details): " . $conn->error;
} else {
    $stmt->bind_param("ii", $appointment_id, $_SESSION['user_id']);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $prescription_details = $result->fetch_assoc();
        } else {
            // This can happen if a patient tries to view a prescription not belonging to them
            // or for an appointment that doesn't have a prescription yet.
            $errors[] = "Prescription not found or you are not authorized to view it.";
        }
    } else {
        $errors[] = "Error fetching prescription details: " . $stmt->error;
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Prescription</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h2>Prescription Details</h2>
            <p><a href="view_appointments.php" class="button-link">Back to My Appointments</a> | <a href="dashboard.php" class="button-link">Dashboard</a></p>
        </header>

        <?php if (!empty($errors)): ?>
            <div class="errors">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($prescription_details && empty($errors)): ?>
            <div class="prescription-info section">
                <p><strong>Doctor:</strong> <?php echo htmlspecialchars($prescription_details['doctor_name']); ?></p>
                <p><strong>Appointment Date:</strong> <?php echo htmlspecialchars(date('Y-m-d h:i A', strtotime($prescription_details['appointment_date']))); ?></p>
                <p><strong>Prescription Date:</strong> <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($prescription_details['prescription_date']))); ?></p>
                
                <h3>Prescription:</h3>
                <div class="prescription-text">
                    <?php echo nl2br(htmlspecialchars($prescription_details['prescription_text'])); ?>
                </div>
            </div>
            <!-- Placeholder for future "Download as PDF" button 
            <div style="margin-top: 20px;">
                <button type="button" onclick="alert('PDF download functionality not yet implemented.')">Download as PDF</button>
            </div>
            -->
        <?php elseif(empty($errors)): ?>
            <p>Prescription details could not be loaded. If you believe this is an error, please contact support.</p>
        <?php endif; ?>

        <footer>
            <p>&copy; <?php echo date("Y"); ?> Online Medical Portal</p>
        </footer>
    </div>
</body>
</html>
