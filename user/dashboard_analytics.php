<?php
function getWeeklyFocusScore($conn, $user_id) {
    $query = "SELECT 
        AVG(focus_score) as avg_focus,
        AVG(CASE 
            WHEN date >= DATE_SUB(CURRENT_DATE, INTERVAL 14 DAY) 
            AND date < DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
            THEN focus_score 
            END) as last_week_focus
        FROM user_study_sessions 
        WHERE user_id = ? 
        AND date >= DATE_SUB(CURRENT_DATE, INTERVAL 14 DAY)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    return [
        'current_score' => round($data['avg_focus'] ?? 0),
        'previous_score' => round($data['last_week_focus'] ?? 0)
    ];
}

function getComprehensionLevel($conn, $user_id) {
    $query = "SELECT 
        AVG(score) as avg_score,
        COUNT(*) as total_assessments,
        MAX(completion_date) as latest_assessment
        FROM quiz_results 
        WHERE user_id = ? 
        AND completion_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    $avg_score = $data['avg_score'] ?? 0;
    $total_assessments = $data['total_assessments'] ?? 0;
    
    if ($avg_score >= 90) {
        return ['level' => 'Expert', 'percentage' => 95];
    } elseif ($avg_score >= 80) {
        return ['level' => 'Advanced', 'percentage' => 85];
    } elseif ($avg_score >= 70) {
        return ['level' => 'Intermediate', 'percentage' => 70];
    } else {
        return ['level' => 'Beginner', 'percentage' => 50];
    }
}
