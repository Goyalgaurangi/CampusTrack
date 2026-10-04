<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
$student = require_role('student');
header('Content-Type: application/json; charset=utf-8');
$query = db()->prepare("SELECT c.id, c.code, COUNT(a.id) AS total, COALESCE(SUM(a.status='Present'), 0) AS present,
        COALESCE(100 * SUM(a.status='Present') / NULLIF(COUNT(a.id), 0), 0) AS percentage
    FROM enrollments e JOIN courses c ON c.id=e.course_id
    LEFT JOIN attendance a ON a.course_id=c.id AND a.student_id=e.student_id
    WHERE e.student_id=? GROUP BY c.id, c.code ORDER BY c.code");
$query->execute([(int) $student['id']]);
$courses = $query->fetchAll();
$total = array_sum(array_map(static fn(array $course): int => (int) $course['total'], $courses));
$present = array_sum(array_map(static fn(array $course): int => (int) $course['present'], $courses));
echo json_encode([
    'overall' => $total ? round(100 * $present / $total, 1) : 0,
    'courses' => array_map(static fn(array $course): array => [
        'id' => (int) $course['id'],
        'code' => $course['code'],
        'percentage' => round((float) $course['percentage'], 1),
    ], $courses),
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);