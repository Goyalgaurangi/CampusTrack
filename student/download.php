<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
$student = require_role('student');
$query = db()->prepare("SELECT m.title, m.file_path FROM materials m
    JOIN enrollments e ON e.course_id=m.course_id
    WHERE m.id=? AND e.student_id=?");
$query->execute([(int) ($_GET['id'] ?? 0), (int) $student['id']]);
$material = $query->fetch();
if (!$material || filter_var($material['file_path'], FILTER_VALIDATE_URL)) {
    http_response_code(404);
    require __DIR__ . '/../errors/404.php';
    exit;
}

$uploadRoot = realpath(__DIR__ . '/../uploads');
$file = realpath(__DIR__ . '/../' . $material['file_path']);
if (!$uploadRoot || !$file || !str_starts_with($file, $uploadRoot . DIRECTORY_SEPARATOR) || !is_file($file)) {
    http_response_code(404);
    exit('Material file not found.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream';
$downloadName = preg_replace('/[^a-zA-Z0-9._ -]/', '_', (string) $material['title']) ?: 'course-material';
$extension = pathinfo($file, PATHINFO_EXTENSION);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . ($extension ? '.' . $extension : '') . '"');
header('X-Content-Type-Options: nosniff');
readfile($file);
exit;