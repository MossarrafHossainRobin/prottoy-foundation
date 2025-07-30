<?php
// view_expenses.php
include 'db.php'; // Includes your database connection

// Set Content-Type header
header('Content-Type: application/json');

$expenses = []; // Array to hold fetched data

// SQL query to select all expenses
// Ordering by date and ID ensures consistent display
$sql = "SELECT id, category, description, amount, paid_to, date FROM expenses ORDER BY date DESC, id DESC";
$result = $conn->query($sql);

if ($result) {
    // Fetch rows and add to the array
    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
    }
    $result->free(); // Free result set
} else {
    // Handle query execution error
    echo json_encode(["status" => "error", "message" => "Failed to retrieve expenses: " . $conn->error]);
    exit;
}

// Output the data as JSON
echo json_encode($expenses);

$conn->close(); // Close the database connection

// Absolutely no HTML or other output below this line!
?>