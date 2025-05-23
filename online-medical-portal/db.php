<?php
// Database credentials - replace with your actual credentials or use environment variables
define('DB_HOST', 'localhost'); // Or your database host
define('DB_USER', 'root');      // Your database username
define('DB_PASS', '');          // Your database password
define('DB_NAME', 'medical_portal'); // Your database name

// Attempt to connect to MySQL database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sqlCreateDB = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if ($conn->query($sqlCreateDB) === TRUE) {
    // echo "Database created successfully or already exists.<br>";
} else {
    die("Error creating database: " . $conn->error . "<br>");
}

// Select the database
$conn->select_db(DB_NAME);

// SQL to create tables
$sqlUsers = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$sqlAppointments = "CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT,
    appointment_date DATETIME NOT NULL,
    symptoms TEXT,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id),
    FOREIGN KEY (doctor_id) REFERENCES users(id)
)";

$sqlMedicalRecords = "CREATE TABLE IF NOT EXISTS medical_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id)
)";

$sqlPrescriptions = "CREATE TABLE IF NOT EXISTS prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    doctor_id INT NOT NULL,
    patient_id INT NOT NULL,
    prescription_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id),
    FOREIGN KEY (doctor_id) REFERENCES users(id),
    FOREIGN KEY (patient_id) REFERENCES users(id)
)";

// Execute create table queries
if ($conn->query($sqlUsers) === TRUE) {
    // echo "Table 'users' created successfully or already exists.<br>";
} else {
    die("Error creating table 'users': " . $conn->error . "<br>");
}

if ($conn->query($sqlAppointments) === TRUE) {
    // echo "Table 'appointments' created successfully or already exists.<br>";
} else {
    die("Error creating table 'appointments': " . $conn->error . "<br>");
}

if ($conn->query($sqlMedicalRecords) === TRUE) {
    // echo "Table 'medical_records' created successfully or already exists.<br>";
} else {
    die("Error creating table 'medical_records': " . $conn->error . "<br>");
}

if ($conn->query($sqlPrescriptions) === TRUE) {
    // echo "Table 'prescriptions' created successfully or already exists.<br>";
} else {
    die("Error creating table 'prescriptions': " . $conn->error . "<br>");
}

// The connection $conn can be used by other PHP scripts by including this file.
// For example: require_once 'db.php';
// No need to close the connection here if other scripts will use it.
// $conn->close();
?>
