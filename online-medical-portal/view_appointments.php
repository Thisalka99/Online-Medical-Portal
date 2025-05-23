<?php
require_once 'auth.php'; // Handles session start, authentication, and sets $current_user_id, $current_username, $current_user_role
require_once 'db.php';   // Database connection

// Handle actions for doctors (confirm, cancel, complete appointment)
if ($_SESSION['role'] === 'doctor' && isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $appointment_id_action = (int)$_GET['id'];
    $new_status = '';

    if ($action === 'confirm') {
        $new_status = 'confirmed';
    } elseif ($action === 'cancel') {
        $new_status = 'cancelled';
    } elseif ($action === 'complete') {
        $new_status = 'completed';
    }

    if (!empty($new_status)) {
        // Check if the appointment belongs to this doctor
        $check_stmt = $conn->prepare("SELECT id FROM appointments WHERE id = ? AND doctor_id = ?");
        if (!$check_stmt) {
             $_SESSION['message'] = "Database error (prepare check): " . $conn->error;
        } else {
            $check_stmt->bind_param("ii", $appointment_id_action, $current_user_id);
            $check_stmt->execute();
            $check_stmt->store_result();

            if ($check_stmt->num_rows > 0) {
                $update_stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ? AND doctor_id = ?");
                if (!$update_stmt) {
                    $_SESSION['message'] = "Database error (prepare update): " . $conn->error;
                } else {
                    $update_stmt->bind_param("sii", $new_status, $appointment_id_action, $current_user_id);
                    if ($update_stmt->execute()) {
                        $_SESSION['message'] = "Appointment status updated successfully to " . htmlspecialchars($new_status) . ".";
                    } else {
                        $_SESSION['message'] = "Failed to update appointment status. Error: " . $update_stmt->error;
                    }
                    $update_stmt->close();
                }
            } else {
                $_SESSION['message'] = "Appointment not found or you are not authorized to modify it.";
            }
            $check_stmt->close();
        }
    } else {
        $_SESSION['message'] = "Invalid action specified.";
    }
    // Redirect to remove GET parameters from URL and refresh the list
    header("Location: view_appointments.php");
    exit();
}

$page_title = "Appointments";
$appointments = [];
$errors = [];

if ($current_user_role === 'patient') {
    $page_title = "My Appointments";
    $stmt = $conn->prepare("SELECT a.id, a.appointment_date, a.symptoms, a.status, u.username as doctor_name 
                            FROM appointments a 
                            JOIN users u ON a.doctor_id = u.id 
                            WHERE a.patient_id = ? 
                            ORDER BY a.appointment_date DESC");
    if (!$stmt) {
        $errors[] = "Database error (prepare patient appointments): " . $conn->error;
    } else {
        $stmt->bind_param("i", $current_user_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $appointments[] = $row;
            }
        } else {
            $errors[] = "Error fetching appointments: " . $stmt->error;
        }
        $stmt->close();
    }
} elseif ($current_user_role === 'doctor') {
    $page_title = "Assigned Appointments";
    // Logic for doctors will be added in the Doctor Module step.
    // For now, let's show a message or an empty list.
    // $errors[] = "Doctor appointment viewing is under construction.";

    // Fetch appointments assigned to this doctor
    $stmt = $conn->prepare("SELECT a.id, a.appointment_date, a.symptoms, a.status, p.username as patient_name 
                            FROM appointments a 
                            JOIN users p ON a.patient_id = p.id 
                            WHERE a.doctor_id = ? 
                            ORDER BY a.appointment_date ASC"); // ASC for upcoming first
    if (!$stmt) {
        $errors[] = "Database error (prepare doctor appointments): " . $conn->error;
    } else {
        $stmt->bind_param("i", $current_user_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $appointments[] = $row;
            }
        } else {
            $errors[] = "Error fetching doctor's appointments: " . $stmt->error;
        }
        $stmt->close();
    }

} else {
    // Should not happen if auth.php is working correctly
    $_SESSION['message'] = "Invalid user role.";
    header("Location: dashboard.php");
    exit();
}

// $conn->close(); // Connection will be closed at the end of the script
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h2><?php echo htmlspecialchars($page_title); ?></h2>
            <p><a href="dashboard.php" class="button-link">Back to Dashboard</a></p>
        </header>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="message success-message">
                <p><?php echo htmlspecialchars($_SESSION['message']); ?></p>
                <?php unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="errors">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($appointments) && empty($errors)): ?>
            <p>No appointments found.</p>
        <?php elseif (!empty($appointments)): ?>
            <table>
                <thead>
                    <tr>
                        <?php if ($current_user_role === 'patient'): ?>
                            <th>Doctor Name</th>
                        <?php elseif ($current_user_role === 'doctor'): ?>
                            <th>Patient Name</th>
                        <?php endif; ?>
                        <th>Appointment Date & Time</th>
                        <th>Symptoms</th>
                        <th>Status</th>
                        <?php if ($current_user_role === 'doctor'): ?>
                            <th>Actions</th>
                        <?php else: ?>
                             <th>Prescription</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <?php if ($current_user_role === 'patient'): ?>
                                <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                            <?php elseif ($current_user_role === 'doctor'): ?>
                                <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars(date('Y-m-d h:i A', strtotime($appointment['appointment_date']))); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($appointment['symptoms'])); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($appointment['status'])); ?></td>
                            <?php if ($current_user_role === 'patient'): ?>
                                <td>
                                    <?php
                                    // Check for prescription for patient
                                    $prescription_link_patient = "N/A";
                                    $stmt_presc_patient = $conn->prepare("SELECT id FROM prescriptions WHERE appointment_id = ? AND patient_id = ?");
                                    if ($stmt_presc_patient) {
                                        $stmt_presc_patient->bind_param("ii", $appointment['id'], $current_user_id);
                                        $stmt_presc_patient->execute();
                                        $stmt_presc_patient->store_result();
                                        if ($stmt_presc_patient->num_rows > 0) {
                                            // Link for patient to view their prescription
                                            $prescription_link_patient = "<a href='view_prescription_details.php?appointment_id=" . $appointment['id'] . "'>View Prescription</a>";
                                        }
                                        $stmt_presc_patient->close();
                                    }
                                    echo $prescription_link_patient;
                                    ?>
                                </td>
                            <?php elseif ($current_user_role === 'doctor'): ?>
                                <td>
                                    <?php if ($appointment['status'] === 'pending'): ?>
                                        <a href="view_appointments.php?action=confirm&id=<?php echo $appointment['id']; ?>" class="button-link-small">Accept</a>
                                        <a href="view_appointments.php?action=cancel&id=<?php echo $appointment['id']; ?>" class="button-link-small cancel">Reject</a>
                                    <?php elseif ($appointment['status'] === 'confirmed'): ?>
                                        <a href="view_appointments.php?action=complete&id=<?php echo $appointment['id']; ?>" class="button-link-small">Complete</a>
                                        <a href="view_appointments.php?action=cancel&id=<?php echo $appointment['id']; ?>" class="button-link-small cancel">Cancel</a>
                                    <?php endif; ?>
                                    <br>
                                    <a href="view_patient_details.php?patient_id=<?php echo $appointment['patient_id']; ?>" class="button-link-small">View Patient Records</a>
                                    <br>
                                    <?php
                                    // Link for doctor to add/edit prescription
                                    $prescription_action_text = "Add Prescription";
                                    $stmt_presc_doctor = $conn->prepare("SELECT id FROM prescriptions WHERE appointment_id = ? AND doctor_id = ?");
                                    if ($stmt_presc_doctor) {
                                        $stmt_presc_doctor->bind_param("ii", $appointment['id'], $current_user_id);
                                        $stmt_presc_doctor->execute();
                                        $stmt_presc_doctor->store_result();
                                        if ($stmt_presc_doctor->num_rows > 0) {
                                            $prescription_action_text = "Edit Prescription";
                                        }
                                        $stmt_presc_doctor->close();
                                    }
                                    ?>
                                    <a href="prescription.php?appointment_id=<?php echo $appointment['id']; ?>" class="button-link-small"><?php echo $prescription_action_text; ?></a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <footer>
            <p>&copy; <?php echo date("Y"); ?> Online Medical Portal</p>
        </footer>
    </div>
</body>
</html>
<?php $conn->close(); ?>
