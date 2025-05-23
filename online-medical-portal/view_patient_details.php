<?php
require_once 'auth.php'; // Handles session start, authentication
require_once 'db.php';   // Database connection

// Ensure the user is a doctor
if ($_SESSION['role'] !== 'doctor') {
    $_SESSION['message'] = "You do not have permission to access this page.";
    header("Location: dashboard.php");
    exit();
}

$errors = [];
$patient_id = null;
$patient_details = null;
$medical_records = [];

// Validate and fetch patient_id from GET request
if (isset($_GET['patient_id']) && filter_var($_GET['patient_id'], FILTER_VALIDATE_INT)) {
    $patient_id = (int)$_GET['patient_id'];
} else {
    $_SESSION['message'] = "Invalid or missing patient ID.";
    header("Location: view_appointments.php"); // Or dashboard
    exit();
}

// Fetch patient details (excluding sensitive info)
$stmt_patient = $conn->prepare("SELECT id, username, created_at FROM users WHERE id = ? AND role = 'patient'");
if (!$stmt_patient) {
    $errors[] = "Database error (prepare patient details): " . $conn->error;
} else {
    $stmt_patient->bind_param("i", $patient_id);
    if ($stmt_patient->execute()) {
        $result_patient = $stmt_patient->get_result();
        if ($result_patient->num_rows === 1) {
            $patient_details = $result_patient->fetch_assoc();
        } else {
            $errors[] = "Patient not found.";
        }
    } else {
        $errors[] = "Error fetching patient details: " . $stmt_patient->error;
    }
    $stmt_patient->close();
}

// Fetch medical records for this patient, if patient details were found
if ($patient_details && empty($errors)) {
    $stmt_records = $conn->prepare("SELECT id, file_name, file_path, description, uploaded_at FROM medical_records WHERE patient_id = ? ORDER BY uploaded_at DESC");
    if (!$stmt_records) {
        $errors[] = "Database error (prepare medical records): " . $conn->error;
    } else {
        $stmt_records->bind_param("i", $patient_id);
        if ($stmt_records->execute()) {
            $result_records = $stmt_records->get_result();
            while ($row = $result_records->fetch_assoc()) {
                $medical_records[] = $row;
            }
        } else {
            $errors[] = "Error fetching medical records: " . $stmt_records->error;
        }
        $stmt_records->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h2>Patient Details</h2>
            <p><a href="view_appointments.php" class="button-link">Back to Appointments</a> | <a href="dashboard.php" class="button-link">Dashboard</a></p>
        </header>

        <?php if (!empty($errors)): ?>
            <div class="errors">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($patient_details && empty($errors)): ?>
            <div class="patient-info section">
                <h3>Patient Information</h3>
                <p><strong>Username:</strong> <?php echo htmlspecialchars($patient_details['username']); ?></p>
                <p><strong>Registered On:</strong> <?php echo htmlspecialchars(date('Y-m-d', strtotime($patient_details['created_at']))); ?></p>
            </div>

            <div class="medical-records section">
                <h3>Medical Records</h3>
                <?php if (empty($medical_records)): ?>
                    <p>No medical records found for this patient.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th>Description</th>
                                <th>Uploaded At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($medical_records as $record): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['file_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['description'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($record['uploaded_at']))); ?></td>
                                    <td>
                                        <a href="<?php echo htmlspecialchars($record['file_path']); ?>" target="_blank" class="button-link-small">View Record</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php elseif(empty($errors)): // Should only show if no patient details but also no errors ?>
            <p>Patient details could not be loaded.</p>
        <?php endif; ?>

        <footer>
            <p>&copy; <?php echo date("Y"); ?> Online Medical Portal</p>
        </footer>
    </div>
</body>
</html>
