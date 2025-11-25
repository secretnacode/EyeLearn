<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die('Unauthorized access');
}

header('Content-Type: application/json');

$conn = new mysqli(getenv('MYSQLHOST') ?: 'tramway.proxy.rlwy.net', getenv('MYSQLUSER') ?: 'root', getenv('MYSQLPASSWORD') ?: 'niCcpkrZKKhLDhXeTbcbhIYSFJBfNibP', getenv('MYSQLDATABASE') ?: 'railway', intval(getenv('MYSQLPORT') ?: 10241));
if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed']));
}

$query = "
    SELECT 
        fq.id,
        fq.title,
        fq.module_id,
        m.title as module_title,
        COUNT(qq.id) as question_count,
        fq.created_at
    FROM final_quizzes fq
    JOIN modules m ON fq.module_id = m.id
    LEFT JOIN final_quiz_questions qq ON fq.id = qq.quiz_id
    GROUP BY fq.id
    ORDER BY fq.created_at DESC
";

$result = $conn->query($query);
$quizzes = [];

while ($row = $result->fetch_assoc()) {
    $quizzes[] = $row;
}

echo json_encode($quizzes);