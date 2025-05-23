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
$success_message = "";
$appointment_id = null;
$appointment_details = null;
$patient_name = "";
$appointment_date = "";
$prescription_text = "";

// Validate and fetch appointment_id from GET request
if (isset($_GET['appointment_id']) && filter_var($_GET['appointment_id'], FILTER_VALIDATE_INT)) {
    $appointment_id = (int)$_GET['appointment_id'];
} else {
    $_SESSION['message'] = "Invalid or missing appointment ID.";
    header("Location: view_appointments.php");
    exit();
}

// Fetch appointment details to confirm it belongs to the logged-in doctor and get patient info
$stmt_app = $conn->prepare("SELECT a.id as app_id, a.appointment_date, a.patient_id, p.username as patient_name 
                            FROM appointments a 
                            JOIN users p ON a.patient_id = p.id 
                            WHERE a.id = ? AND a.doctor_id = ?");
if (!$stmt_app) {
    $errors[] = "Database error (prepare appointment fetch): " . $conn->error;
} else {
    $stmt_app->bind_param("ii", $appointment_id, $_SESSION['user_id']);
    if ($stmt_app->execute()) {
        $result_app = $stmt_app->get_result();
        if ($result_app->num_rows === 1) {
            $appointment_details = $result_app->fetch_assoc();
            $patient_name = $appointment_details['patient_name'];
            $appointment_date = $appointment_details['appointment_date'];
            // Now fetch existing prescription if any
            $stmt_presc_fetch = $conn->prepare("SELECT prescription_text FROM prescriptions WHERE appointment_id = ? AND doctor_id = ?");
            if($stmt_presc_fetch) {
                $stmt_presc_fetch->bind_param("ii", $appointment_id, $_SESSION['user_id']);
                $stmt_presc_fetch->execute();
                $result_presc_fetch = $stmt_presc_fetch->get_result();
                if($result_presc_fetch->num_rows === 1){
                    $existing_prescription = $result_presc_fetch->fetch_assoc();
                    $prescription_text = $existing_prescription['prescription_text'];
                }
                $stmt_presc_fetch->close();
            } else {
                 $errors[] = "Database error (prepare prescription fetch): " . $conn->error;
            }
        } else {
            $_SESSION['message'] = "Appointment not found or you are not authorized to manage its prescription.";
            header("Location: view_appointments.php");
            exit();
        }
    } else {
        $errors[] = "Error fetching appointment details: " . $stmt_app->error;
    }
    $stmt_app->close();
}


// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ensure appointment_id from POST matches the one from GET (and session)
    if (isset($_POST['appointment_id']) && (int)$_POST['appointment_id'] === $appointment_id) {
        $posted_prescription_text = trim($_POST['prescription_text']);

        // Validate input
        if (empty($posted_prescription_text)) {
            $errors[] = "Prescription text cannot be empty.";
        }
        // Re-assign to display in form if error
        $prescription_text = $posted_prescription_text;


        if (empty($errors) && $appointment_details) {
            $patient_id_for_prescription = $appointment_details['patient_id'];
            $doctor_id_for_prescription = $_SESSION['user_id'];

            // Check if prescription already exists
            $stmt_check = $conn->prepare("SELECT id FROM prescriptions WHERE appointment_id = ?");
            if(!$stmt_check){
                 $errors[] = "Database error (prepare check existing): " . $conn->error;
            } else {
                $stmt_check->bind_param("i", $appointment_id);
                $stmt_check->execute();
                $stmt_check->store_result();
                $prescription_exists = $stmt_check->num_rows > 0;
                $stmt_check->close();

                if ($prescription_exists) {
                    $stmt_update = $conn->prepare("UPDATE prescriptions SET prescription_text = ?, doctor_id = ?, patient_id = ? WHERE appointment_id = ?");
                    if (!$stmt_update) {
                         $errors[] = "Database error (prepare update): " . $conn->error;
                    } else {
                        $stmt_update->bind_param("siii", $posted_prescription_text, $doctor_id_for_prescription, $patient_id_for_prescription, $appointment_id);
                        if ($stmt_update->execute()) {
                            $success_message = "Prescription updated successfully!";
                        } else {
                            $errors[] = "Failed to update prescription. Error: " . $stmt_update->error;
                        }
                        $stmt_update->close();
                    }
                } else {
                    $stmt_insert = $conn->prepare("INSERT INTO prescriptions (appointment_id, doctor_id, patient_id, prescription_text) VALUES (?, ?, ?, ?)");
                     if (!$stmt_insert) {
                         $errors[] = "Database error (prepare insert): " . $conn->error;
                    } else {
                        $stmt_insert->bind_param("iiis", $appointment_id, $doctor_id_for_prescription, $patient_id_for_prescription, $posted_prescription_text);
                        if ($stmt_insert->execute()) {
                            $success_message = "Prescription saved successfully!";
                        } else {
                            $errors[] = "Failed to save prescription. Error: " . $stmt_insert->error;
                        }
                        $stmt_insert->close();
                    }
                }
                 if(empty($errors) && !empty($success_message)){
                    $_SESSION['message'] = $success_message;
                    header("Location: view_appointments.php"); // Redirect on success
                    exit();
                }
            }
        }
    } else {
        $errors[] = "Form submission error: Appointment ID mismatch.";
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add/Edit Prescription</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h2>Add/Edit Prescription</h2>
            <p><a href="view_appointments.php" class="button-link">Back to Appointments</a></p>
        </header>

        <?php if (!empty($success_message) && empty($errors)): // Show success only if no new errors occurred ?>
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

        <?php if ($appointment_details): ?>
            <div class="appointment-info">
                <p><strong>Patient:</strong> <?php echo htmlspecialchars($patient_name); ?></p>
                <p><strong>Appointment Date:</strong> <?php echo htmlspecialchars(date('Y-m-d h:i A', strtotime($appointment_date))); ?></p>
            </div>

            <form action="prescription.php?appointment_id=<?php echo $appointment_id; ?>" method="post">
                <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
                <div>
                    <label for="prescription_text">Prescription Details:</label>
                    <textarea id="prescription_text" name="prescription_text" rows="10" required><?php echo htmlspecialchars($prescription_text); ?></textarea>
                </div>
                <div>
                    <button type="submit">Save Prescription</button>
                </div>
            </form>
        <?php elseif(empty($errors)): // Only show if no major errors like DB connection failed initially ?>
            <p>Loading appointment details...</p>
             <?php if(empty($appointment_details) && empty($errors)) $errors[] = "Could not load appointment details to proceed."; ?>
        <?php endif; ?>
        
        <?php if (!empty($errors) && !$appointment_details): // If errors and no appointment details ?>
             <p>Could not load appointment details. Please ensure the appointment ID is correct and try again.</p>
        <?php endif; ?>


        <footer>
            <p>&copy; <?php echo date("Y"); ?> Online Medical Portal</p>
        </footer>
    </div>
</body>
</html>
