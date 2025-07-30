<?php
// add_expense.php
include 'db.php'; // Includes your database connection

// Set Content-Type header
header('Content-Type: application/json');

// Decode JSON input
$data = json_decode(file_get_contents("php://input"));

// Input validation
if (!$data || !isset($data->category, $data->description, $data->amount, $data->paid_to, $data->date)) {
    echo json_encode(["status" => "error", "message" => "Invalid or incomplete input data provided."]);
    exit;
}

// Extract data
$category = $data->category;
$description = $data->description;
$amount = $data->amount;
$paid_to = $data->paid_to;
$date = $data->date;

// Prepare SQL statement to prevent SQL Injection
// 'ssdss' means: s=string, s=string, d=double, s=string, s=string
// Adjust 'd' to 'i' if your 'amount' column is an INTEGER, or 's' if VARCHAR/TEXT.
$stmt = $conn->prepare("INSERT INTO expenses (category, description, amount, paid_to, date) VALUES (?, ?, ?, ?, ?)");

if ($stmt === false) {
    echo json_encode(["status" => "error", "message" => "Failed to prepare statement: " . $conn->error]);
    exit;
}

// Bind parameters
$bind_success = $stmt->bind_param("ssdss", $category, $description, $amount, $paid_to, $date);

if ($bind_success === false) {
    echo json_encode(["status" => "error", "message" => "Failed to bind parameters: " . $stmt->error]);
    exit;
}

// Execute the statement
if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Expense added successfully!"]);
} else {
    echo json_encode(["status" => "error", "message" => "Error adding expense: " . $stmt->error]);
}

// Close statement and connection
$stmt->close();
$conn->close();

// Absolutely no HTML or other output below this line!
?>