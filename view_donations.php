<?php
// view_donations.php
include 'db.php'; // Includes your database connection

// Set Content-Type header
header('Content-Type: application/json');

$donations = []; // Array to hold fetched data

// SQL query to select all donations
// Ordering by date and ID ensures consistent display
$sql = "SELECT id, donor_name, donor_email, amount, method, date FROM donations ORDER BY date DESC, id DESC";
$result = $conn->query($sql);

if ($result) {
    // Fetch rows and add to the array
    while ($row = $result->fetch_assoc()) {
        $donations[] = $row;
    }
    $result->free(); // Free result set
} else {
    // Handle query execution error
    echo json_encode(["status" => "error", "message" => "Failed to retrieve donations: " . $conn->error]);
    exit;
}

// Output the data as JSON
echo json_encode($donations);

$conn->close(); // Close the database connection

// Absolutely no HTML or other output below this line!
?>