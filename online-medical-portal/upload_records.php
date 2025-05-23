<?php
require_once 'auth.php'; // Handles session start, authentication
require_once 'db.php';   // Database connection

// Ensure the user is a patient
if ($_SESSION['role'] !== 'patient') {
    $_SESSION['message'] = "You do not have permission to access this page.";
    header("Location: dashboard.php");
    exit();
}

$errors = [];
$success_message = "";
$patient_id = $_SESSION['user_id'];
$upload_dir = 'uploads/';

// Ensure upload directory exists and is writable
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) { // octal; correct permission string
        $errors[] = "Failed to create upload directory. Please contact support.";
    }
}
if (!is_writable($upload_dir)) {
    $errors[] = "Upload directory is not writable. Please contact support.";
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['medical_file'])) {
    $file_description = trim($_POST['file_description']); // Optional

    // File upload handling
    $uploaded_file = $_FILES['medical_file'];

    // Check for upload errors
    if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
        switch ($uploaded_file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = "File is too large.";
                break;
            case UPLOAD_ERR_NO_FILE:
                $errors[] = "No file was selected for upload.";
                break;
            default:
                $errors[] = "An error occurred during file upload. Error code: " . $uploaded_file['error'];
        }
    } elseif (empty($errors)) { // Proceed if directory is OK and no initial upload errors
        $file_name_original = basename($uploaded_file["name"]);
        $file_tmp_name = $uploaded_file["tmp_name"];
        $file_size = $uploaded_file["size"];
        $file_type = mime_content_type($file_tmp_name); // More reliable than $_FILES['type']

        // Validation
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
        $max_file_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Invalid file type. Allowed types: PDF, JPG, JPEG, PNG.";
        }
        if ($file_size > $max_file_size) {
            $errors[] = "File is too large. Maximum size is 5MB.";
        }

        if (empty($errors)) {
            // Create a unique filename
            $unique_filename = uniqid($patient_id . '_', true) . '.' . strtolower(pathinfo($file_name_original, PATHINFO_EXTENSION));
            $file_path = $upload_dir . $unique_filename;

            if (move_uploaded_file($file_tmp_name, $file_path)) {
                // Insert into database
                $stmt = $conn->prepare("INSERT INTO medical_records (patient_id, file_name, file_path, description) VALUES (?, ?, ?, ?)");
                if (!$stmt) {
                    $errors[] = "Database error (prepare insert): " . $conn->error;
                    // Optionally, delete the uploaded file if DB insert fails
                    unlink($file_path);
                } else {
                    $stmt->bind_param("isss", $patient_id, $file_name_original, $file_path, $file_description);
                    if ($stmt->execute()) {
                        $success_message = "Medical record uploaded successfully!";
                        // Clear description for the form
                        $file_description = "";
                    } else {
                        $errors[] = "Failed to save record to database. Error: " . $stmt->error;
                        // Optionally, delete the uploaded file if DB insert fails
                        unlink($file_path);
                    }
                    $stmt->close();
                }
            } else {
                $errors[] = "Failed to move uploaded file to the destination directory.";
            }
        }
    }
}

// Fetch previously uploaded records for this patient
$uploaded_records = [];
$stmt_select = $conn->prepare("SELECT id, file_name, file_path, description, uploaded_at FROM medical_records WHERE patient_id = ? ORDER BY uploaded_at DESC");
if (!$stmt_select) {
    $errors[] = "Database error (prepare select records): " . $conn->error;
} else {
    $stmt_select->bind_param("i", $patient_id);
    if ($stmt_select->execute()) {
        $result_records = $stmt_select->get_result();
        while ($row = $result_records->fetch_assoc()) {
            $uploaded_records[] = $row;
        }
    } else {
        $errors[] = "Error fetching medical records: " . $stmt_select->error;
    }
    $stmt_select->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Medical Record</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h2>Upload Medical Record</h2>
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

        <form action="upload_records.php" method="post" enctype="multipart/form-data">
            <div>
                <label for="medical_file">Select File (PDF, JPG, PNG - Max 5MB):</label>
                <input type="file" id="medical_file" name="medical_file" accept=".pdf,.jpg,.jpeg,.png" required>
            </div>
            <div>
                <label for="file_description">File Description (Optional):</label>
                <input type="text" id="file_description" name="file_description" value="<?php echo isset($file_description) ? htmlspecialchars($file_description) : ''; ?>">
            </div>
            <div>
                <button type="submit">Upload Record</button>
            </div>
        </form>

        <h3>My Uploaded Records</h3>
        <?php if (empty($uploaded_records) && count($errors) == 0): // only show "no records" if there weren't other errors ?>
            <p>No medical records uploaded yet.</p>
        <?php elseif (!empty($uploaded_records)): ?>
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
                    <?php foreach ($uploaded_records as $record): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($record['file_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['description'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($record['uploaded_at']))); ?></td>
                            <td>
                                <a href="<?php echo htmlspecialchars($record['file_path']); ?>" target="_blank" class="button-link-small">View/Download</a>
                                <!-- Add delete functionality if needed -->
                            </td>
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
