<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $searchTerm = $data['term'];
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    if ($userId) {
        // Check if search term exists for this user
        $stmt = $conn->prepare("SELECT id, click_count FROM search_history WHERE user_id = ? AND search_term = ?");
        $stmt->bind_param("is", $userId, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Update existing record
            $row = $result->fetch_assoc();
            $stmt = $conn->prepare("UPDATE search_history SET click_count = click_count + 1, last_searched = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param("i", $row['id']);
        } else {
            // Insert new record
            $stmt = $conn->prepare("INSERT INTO search_history (user_id, search_term) VALUES (?, ?)");
            $stmt->bind_param("is", $userId, $searchTerm);
        }
        $stmt->execute();
    }
}