<?php
// add_donation.php
include 'db.php'; // Includes your database connection

// Set Content-Type header to ensure the client (React app) knows it's JSON
header('Content-Type: application/json');

// Decode JSON input from the request body
$data = json_decode(file_get_contents("php://input"));

// Input validation
if (!$data || !isset($data->donor_name, $data->amount, $data->method, $data->date)) {
    echo json_encode(["status" => "error", "message" => "Invalid or incomplete input data provided."]);
    exit;
}

// Extract data, providing default for optional fields like email
$donor_name = $data->donor_name;
$donor_email = $data->donor_email ?? ''; // Use null coalescing operator for optional email
$amount = $data->amount;
$method = $data->method;
$date = $data->date;

// Prepare SQL statement to prevent SQL Injection
// 'ssdss' means: s=string, s=string, d=double, s=string, s=string
// Adjust 'd' to 'i' if your 'amount' column is an INTEGER, or 's' if VARCHAR/TEXT.
$stmt = $conn->prepare("INSERT INTO donations (donor_name, donor_email, amount, method, date) VALUES (?, ?, ?, ?, ?)");

if ($stmt === false) {
    echo json_encode(["status" => "error", "message" => "Failed to prepare statement: " . $conn->error]);
    exit;
}

// Bind parameters to the prepared statement
$bind_success = $stmt->bind_param("ssdss", $donor_name, $donor_email, $amount, $method, $date);

if ($bind_success === false) {
    echo json_encode(["status" => "error", "message" => "Failed to bind parameters: " . $stmt->error]);
    exit;
}

// Execute the statement
if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Donation added successfully!"]);
} else {
    echo json_encode(["status" => "error", "message" => "Error adding donation: " . $stmt->error]);
}

// Close statement and connection
$stmt->close();
$conn->close();

// Absolutely no HTML or other output below this line!
?>