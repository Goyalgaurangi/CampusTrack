<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_role('admin');
header('Content-Type: application/json; charset=utf-8');

$admin = require_role('admin');
$search = trim((string) ($_GET['q'] ?? ''));
$search = substr($search, 0, 100);
$role = (string) ($_GET['role'] ?? '');
$department = trim((string) ($_GET['department'] ?? ''));
$department = substr($department, 0, 120);
$sql = 'SELECT id, name, email, role, department, identifier FROM users WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (name LIKE ? OR email LIKE ? OR department LIKE ? OR identifier LIKE ?)';
    $needle = '%' . $search . '%';
    array_push($params, $needle, $needle, $needle, $needle);
}
if (in_array($role, ['admin', 'faculty', 'student'], true)) {
    $sql .= ' AND role = ?';
    $params[] = $role;
}
if ($department !== '') {
    $sql .= ' AND department = ?';
    $params[] = substr($department, 0, 120);
}
$sql .= ' ORDER BY FIELD(role, "admin", "faculty", "student"), name LIMIT 100';
$query = db()->prepare($sql);
$query->execute($params);
$people = $query->fetchAll();
$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$token = $escape(csrf_token());
$html = '';
foreach ($people as $person) {
    $html .= '<tr data-user-row data-role="' . $escape($person['role']) . '"><td><strong>' . $escape($person['name']) . '</strong><small class="table-subtitle">' . $escape($person['identifier'] ?: 'No ID assigned') . '</small></td>';
    $html .= '<td>' . $escape($person['email']) . '</td><td><span class="badge badge-blue">' . $escape(ucfirst($person['role'])) . '</span></td><td>' . $escape($person['department']) . '</td>';
    $html .= '<td><form method="post" action="index.php?view=users" class="inline-reset" data-validate><input type="hidden" name="csrf_token" value="' . $token . '"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="' . (int) $person['id'] . '"><input name="new_password" type="password" minlength="8" required placeholder="New password" aria-label="New password"><button class="text-link" type="submit">Reset</button></form></td>';
    $html .= '<td class="action-cell"><a class="text-link" href="index.php?view=users&amp;edit=' . (int) $person['id'] . '">Edit</a>';
    if ((int) $person['id'] !== (int) $admin['id']) {
        $html .= '<form method="post" action="index.php?view=users" data-confirm="Delete this account and its student records? This cannot be undone."><input type="hidden" name="csrf_token" value="' . $token . '"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="' . (int) $person['id'] . '"><button class="text-link danger-link" type="submit">Delete</button></form>';
    }
    $html .= '</td></tr>';
}
echo json_encode(['html' => $html, 'count' => count($people)], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);