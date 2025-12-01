<?php
function saveSearchHistory($userId, $searchTerm) {
    global $conn;
    $searchTerm = mysqli_real_escape_string($conn, $searchTerm);
    $userId = (int)$userId;
    
    $sql = "INSERT INTO search_history (user_id, search_term, search_date) 
            VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $userId, $searchTerm);
    $stmt->execute();
}

function getSearchHistory($userId) {
    global $conn;
    $userId = (int)$userId;
    
    $sql = "SELECT search_term, COUNT(*) as count, MAX(search_date) as last_search 
            FROM search_history 
            WHERE user_id = ? 
            GROUP BY search_term 
            ORDER BY count DESC, last_search DESC 
            LIMIT 5";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}