<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function go(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function post_string(string $key, int $maxLength = 255): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function is_valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function letter_grade(float $percentage): string
{
    return match (true) {
        $percentage >= 90 => 'A',
        $percentage >= 80 => 'B',
        $percentage >= 70 => 'C',
        $percentage >= 60 => 'D',
        default => 'F',
    };
}

function attendance_percent(PDO $pdo, int $studentId, ?int $courseId = null): float
{
    $sql = "SELECT COALESCE(100 * SUM(status = 'Present') / NULLIF(COUNT(*), 0), 0)
            FROM attendance WHERE student_id = ?";
    $values = [$studentId];
    if ($courseId !== null) {
        $sql .= ' AND course_id = ?';
        $values[] = $courseId;
    }
    $query = $pdo->prepare($sql);
    $query->execute($values);
    return round((float) $query->fetchColumn(), 1);
}