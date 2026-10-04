<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
$faculty = require_role('faculty');
$pdo = db();
$view = (string) ($_GET['view'] ?? 'dashboard');
if (!in_array($view, ['dashboard', 'attendance', 'grades', 'materials'], true)) {
    http_response_code(404);
    require __DIR__ . '/../errors/404.php';
    exit;
}

$courseQuery = $pdo->prepare('SELECT id, code, name, semester FROM courses WHERE faculty_id = ? ORDER BY code');
$courseQuery->execute([(int) $faculty['id']]);
$courses = $courseQuery->fetchAll();
$courseIds = array_map(static fn(array $course): int => (int) $course['id'], $courses);
$selectedCourseId = (int) ($_REQUEST['course_id'] ?? ($courseIds[0] ?? 0));
if ($selectedCourseId && !in_array($selectedCourseId, $courseIds, true)) {
    http_response_code(403);
    require __DIR__ . '/../errors/403.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'save_attendance') {
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $date = (string) ($_POST['attendance_date'] ?? '');
            if (!in_array($courseId, $courseIds, true) || !DateTime::createFromFormat('Y-m-d', $date)) {
                throw new InvalidArgumentException('Choose one of your courses and a valid date.');
            }
            $rosterQuery = $pdo->prepare("SELECT u.id FROM enrollments e JOIN users u ON u.id=e.student_id WHERE e.course_id=? AND u.role='student'");
            $rosterQuery->execute([$courseId]);
            $studentIds = array_map('intval', $rosterQuery->fetchAll(PDO::FETCH_COLUMN));
            $save = $pdo->prepare('INSERT INTO attendance (student_id, course_id, attendance_date, status) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)');
            $pdo->beginTransaction();
            foreach ($studentIds as $studentId) {
                $status = (string) ($_POST['status'][$studentId] ?? '');
                if (!in_array($status, ['Present', 'Absent'], true)) {
                    throw new InvalidArgumentException('Choose present or absent for every enrolled student.');
                }
                $save->execute([$studentId, $courseId, $date, $status]);
            }
            $pdo->commit();
            flash('success', 'Attendance saved for ' . count($studentIds) . ' students. Saving again updates the same course and date.');
            go('index.php?view=attendance&course_id=' . $courseId . '&date=' . rawurlencode($date));
        }
        if ($action === 'save_grades') {
            $courseId = (int) ($_POST['course_id'] ?? 0);
            if (!in_array($courseId, $courseIds, true)) {
                throw new InvalidArgumentException('You can only edit grades for your assigned courses.');
            }
            $rosterQuery = $pdo->prepare("SELECT u.id FROM enrollments e JOIN users u ON u.id=e.student_id WHERE e.course_id=? AND u.role='student'");
            $rosterQuery->execute([$courseId]);
            $save = $pdo->prepare('INSERT INTO grades (student_id, course_id, internal, midterm, final, total, percentage, grade_letter) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE internal=VALUES(internal), midterm=VALUES(midterm), final=VALUES(final), total=VALUES(total), percentage=VALUES(percentage), grade_letter=VALUES(grade_letter)');
            $pdo->beginTransaction();
            foreach ($rosterQuery->fetchAll(PDO::FETCH_COLUMN) as $studentIdValue) {
                $studentId = (int) $studentIdValue;
                $marks = [];
                foreach (['internal', 'midterm', 'final'] as $field) {
                    $raw = $_POST[$field][$studentId] ?? null;
                    if (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > 100) {
                        throw new InvalidArgumentException('Enter a mark from 0 to 100 for every student.');
                    }
                    $marks[] = round((float) $raw, 2);
                }
                $total = array_sum($marks);
                $percentage = round($total / 3, 2);
                $save->execute([$studentId, $courseId, $marks[0], $marks[1], $marks[2], $total, $percentage, letter_grade($percentage)]);
            }
            $pdo->commit();
            flash('success', 'Grades and calculated totals were saved.');
            go('index.php?view=grades&course_id=' . $courseId);
        }
        if ($action === 'add_material') {
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $title = post_string('title', 160);
            $description = post_string('description', 2000);
            $type = (string) ($_POST['material_type'] ?? 'file');
            if (!in_array($courseId, $courseIds, true) || $title === '') {
                throw new InvalidArgumentException('Choose your course and enter a material title.');
            }
            if ($type === 'link') {
                $url = trim((string) ($_POST['material_url'] ?? ''));
                $parts = parse_url($url);
                if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
                    throw new InvalidArgumentException('Enter a valid http or https link.');
                }
                $path = $url;
            } else {
                if (!isset($_FILES['material_file']) || $_FILES['material_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new InvalidArgumentException('Choose a file under 5 MB to upload.');
                }
                $file = $_FILES['material_file'];
                if ($file['size'] > 5 * 1024 * 1024) {
                    throw new InvalidArgumentException('The file must be 5 MB or smaller.');
                }
                $originalName = basename((string) $file['name']);
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $allowed = [
                    'pdf' => ['application/pdf'],
                    'png' => ['image/png'],
                    'jpg' => ['image/jpeg'],
                    'jpeg' => ['image/jpeg'],
                    'doc' => ['application/msword', 'application/octet-stream'],
                    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
                    'ppt' => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
                    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
                ];
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                if (!isset($allowed[$extension]) || !in_array($mime, $allowed[$extension], true)) {
                    throw new InvalidArgumentException('Allowed file types: PDF, PNG, JPG, DOC, DOCX, PPT, and PPTX.');
                }
                $folder = __DIR__ . '/../uploads/course-' . $courseId;
                if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
                    throw new RuntimeException('The upload folder could not be created.');
                }
                $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
                if (!move_uploaded_file($file['tmp_name'], $folder . '/' . $storedName)) {
                    throw new RuntimeException('The file could not be stored. Check the uploads folder permissions.');
                }
                $path = 'uploads/course-' . $courseId . '/' . $storedName;
            }
            $statement = $pdo->prepare('INSERT INTO materials (course_id, title, description, file_path) VALUES (?, ?, ?, ?)');
            $statement->execute([$courseId, $title, $description, $path]);
            flash('success', 'Course material added.');
            go('index.php?view=materials&course_id=' . $courseId);
        }
        if ($action === 'delete_material') {
            $materialId = (int) ($_POST['material_id'] ?? 0);
            $statement = $pdo->prepare('SELECT m.file_path, m.course_id FROM materials m JOIN courses c ON c.id=m.course_id WHERE m.id=? AND c.faculty_id=?');
            $statement->execute([$materialId, (int) $faculty['id']]);
            $material = $statement->fetch();
            if ($material) {
                $pdo->prepare('DELETE FROM materials WHERE id=?')->execute([$materialId]);
                if (!filter_var($material['file_path'], FILTER_VALIDATE_URL)) {
                    $absolute = realpath(__DIR__ . '/../' . $material['file_path']);
                    $uploadRoot = realpath(__DIR__ . '/../uploads');
                    if ($absolute && $uploadRoot && str_starts_with($absolute, $uploadRoot . DIRECTORY_SEPARATOR)) {
                        @unlink($absolute);
                    }
                }
            }
            flash('success', 'Material removed.');
            go('index.php?view=materials');
        }
        throw new InvalidArgumentException('That action is not available.');
    } catch (InvalidArgumentException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('CampusTrack faculty action failed: ' . $exception->getMessage());
        flash('error', 'The change could not be saved. Check the entered values and try again.');
    }
    go('index.php?view=' . rawurlencode($view) . ($selectedCourseId ? '&course_id=' . $selectedCourseId : ''));
}

$pageTitle = ucfirst($view);
require __DIR__ . '/../includes/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">FACULTY WORKSPACE</p><h1><?= $view === 'dashboard' ? 'Welcome, ' . e($faculty['name']) : e(ucfirst($view)) ?></h1><p class="muted"><?= $view === 'dashboard' ? 'Your teaching schedule and course activity at a glance.' : 'Manage the courses assigned to you.' ?></p></div></div>

<?php if ($view === 'dashboard'):
    $courseSummary = $pdo->prepare("SELECT c.id, c.code, c.name,
            COUNT(DISTINCT e.student_id) AS students,
            COUNT(DISTINCT CASE WHEN a.attendance_date=CURDATE() THEN a.student_id END) AS marked_today,
            (SELECT ROUND(100 * AVG(att.status='Present'), 1) FROM attendance att WHERE att.course_id=c.id) AS attendance_rate,
            (SELECT ROUND(AVG(g.percentage), 1) FROM grades g WHERE g.course_id=c.id) AS grade_average
        FROM courses c LEFT JOIN enrollments e ON e.course_id=c.id
        LEFT JOIN attendance a ON a.course_id=c.id AND a.student_id=e.student_id
        WHERE c.faculty_id=? GROUP BY c.id, c.code, c.name ORDER BY c.code");
    $courseSummary->execute([(int) $faculty['id']]);
    $summaryRows = $courseSummary->fetchAll();
?>
    <?php if (!$summaryRows): ?><section class="panel empty-state"><h2>No courses assigned yet</h2><p>Ask an administrator to assign a course to your account.</p></section><?php else: ?>
    <div class="stat-grid"><article class="stat-card"><span class="stat-icon icon-blue">CR</span><span class="stat-label">Assigned courses</span><strong><?= count($summaryRows) ?></strong><small>Your active course load</small></article><article class="stat-card"><span class="stat-icon icon-teal">ST</span><span class="stat-label">Enrolled students</span><strong><?= array_sum(array_map(static fn(array $r): int => (int) $r['students'], $summaryRows)) ?></strong><small>Across assigned courses</small></article><article class="stat-card"><span class="stat-icon icon-violet">AT</span><span class="stat-label">Marked today</span><strong><?= array_sum(array_map(static fn(array $r): int => (int) $r['marked_today'], $summaryRows)) ?></strong><small>Attendance records for today</small></article></div>
    <section class="panel"><div class="panel-heading"><div><h2>Your courses</h2><p>Open a course to manage its class records</p></div><a class="text-link" href="index.php?view=attendance">Mark attendance →</a></div><div class="course-grid"><?php foreach ($summaryRows as $course): ?><article class="course-card"><span class="course-code"><?= e($course['code']) ?></span><h3><?= e($course['name']) ?></h3><p><?= (int) $course['students'] ?> enrolled students</p><div class="course-insights"><span>Attendance average <strong><?= $course['attendance_rate'] !== null ? number_format((float) $course['attendance_rate'], 1) . '%' : '—' ?></strong></span><span>Grade average <strong><?= $course['grade_average'] !== null ? number_format((float) $course['grade_average'], 1) . '%' : '—' ?></strong></span></div><div class="course-card-actions"><a class="button button-quiet" href="index.php?view=attendance&course_id=<?= (int) $course['id'] ?>">Attendance</a><a class="button button-quiet" href="index.php?view=grades&course_id=<?= (int) $course['id'] ?>">Grades</a></div></article><?php endforeach; ?></div></section>
    <?php endif; ?>

<?php elseif ($view === 'attendance'):
    $selectedDate = (string) ($_GET['date'] ?? date('Y-m-d'));
    if (!DateTime::createFromFormat('Y-m-d', $selectedDate)) {
        $selectedDate = date('Y-m-d');
    }
    $roster = [];
    if ($selectedCourseId) {
        $rosterQuery = $pdo->prepare("SELECT u.id, u.name, u.identifier, a.status FROM enrollments e JOIN users u ON u.id=e.student_id LEFT JOIN attendance a ON a.student_id=u.id AND a.course_id=e.course_id AND a.attendance_date=? WHERE e.course_id=? AND u.role='student' ORDER BY u.name");
        $rosterQuery->execute([$selectedDate, $selectedCourseId]);
        $roster = $rosterQuery->fetchAll();
    }
?>
    <section class="panel"><div class="panel-heading"><div><h2>Attendance register</h2><p>Choose a course and class date, then mark each enrolled student.</p></div></div>
        <?php if (!$courses): ?><div class="empty-state">No courses have been assigned to you yet.</div><?php else: ?>
        <form method="get" class="filter-row"><input type="hidden" name="view" value="attendance"><div><label for="attendance-course">Course</label><select id="attendance-course" name="course_id"><?php foreach ($courses as $course): ?><option value="<?= (int) $course['id'] ?>" <?= $selectedCourseId === (int) $course['id'] ? 'selected' : '' ?>><?= e($course['code'] . ' · ' . $course['name']) ?></option><?php endforeach; ?></select></div><div><label for="attendance-date">Class date</label><input id="attendance-date" name="date" type="date" value="<?= e($selectedDate) ?>" required></div><button class="button button-quiet" type="submit">Load roster</button></form>
        <?php if (!$roster): ?><div class="empty-state">There are no enrolled students for this course.</div><?php else: ?>
        <form method="post" data-validate><?= csrf_field() ?><input type="hidden" name="action" value="save_attendance"><input type="hidden" name="course_id" value="<?= $selectedCourseId ?>"><input type="hidden" name="attendance_date" value="<?= e($selectedDate) ?>">
            <div class="table-wrap"><table><thead><tr><th>Student</th><th>Identifier</th><th>Present</th><th>Absent</th></tr></thead><tbody><?php foreach ($roster as $student): ?><tr><td><strong><?= e($student['name']) ?></strong></td><td><?= e($student['identifier']) ?></td><td><label class="radio-option"><input type="radio" name="status[<?= (int) $student['id'] ?>]" value="Present" required <?= ($student['status'] ?? 'Present') === 'Present' ? 'checked' : '' ?>><span>Present</span></label></td><td><label class="radio-option"><input type="radio" name="status[<?= (int) $student['id'] ?>]" value="Absent" required <?= ($student['status'] ?? '') === 'Absent' ? 'checked' : '' ?>><span>Absent</span></label></td></tr><?php endforeach; ?></tbody></table></div>
            <div class="panel-footer"><span class="muted"><?= count($roster) ?> students · Re-saving a date updates existing records.</span><button class="button button-primary" type="submit">Save attendance</button></div>
        </form><?php endif; ?><?php endif; ?>
    </section>

<?php elseif ($view === 'grades'):
    $roster = [];
    if ($selectedCourseId) {
        $rosterQuery = $pdo->prepare("SELECT u.id, u.name, u.identifier, g.internal, g.midterm, g.final, g.total, g.percentage, g.grade_letter FROM enrollments e JOIN users u ON u.id=e.student_id LEFT JOIN grades g ON g.student_id=u.id AND g.course_id=e.course_id WHERE e.course_id=? AND u.role='student' ORDER BY u.name");
        $rosterQuery->execute([$selectedCourseId]);
        $roster = $rosterQuery->fetchAll();
    }
?>
    <section class="panel"><div class="panel-heading"><div><h2>Gradebook</h2><p>Each assessment is out of 100; totals, percentages, and letter grades are calculated on save.</p></div></div>
        <?php if (!$courses): ?><div class="empty-state">No courses have been assigned to you yet.</div><?php else: ?><form method="get" class="filter-row"><input type="hidden" name="view" value="grades"><div><label for="grade-course">Course</label><select id="grade-course" name="course_id"><?php foreach ($courses as $course): ?><option value="<?= (int) $course['id'] ?>" <?= $selectedCourseId === (int) $course['id'] ? 'selected' : '' ?>><?= e($course['code'] . ' · ' . $course['name']) ?></option><?php endforeach; ?></select></div><button class="button button-quiet" type="submit">Open gradebook</button></form>
        <?php if (!$roster): ?><div class="empty-state">There are no enrolled students for this course.</div><?php else: ?><form method="post" data-validate><?= csrf_field() ?><input type="hidden" name="action" value="save_grades"><input type="hidden" name="course_id" value="<?= $selectedCourseId ?>"><div class="table-wrap"><table><thead><tr><th>Student</th><th>Internal / 100</th><th>Midterm / 100</th><th>Final / 100</th><th>Total / 300</th><th>Grade</th></tr></thead><tbody>
        <?php foreach ($roster as $student): ?><tr><td><strong><?= e($student['name']) ?></strong><small class="table-subtitle"><?= e($student['identifier']) ?></small></td><?php foreach (['internal', 'midterm', 'final'] as $field): ?><td><input class="mark-input" type="number" name="<?= $field ?>[<?= (int) $student['id'] ?>]" min="0" max="100" step="0.01" required value="<?= e($student[$field] ?? 0) ?>" aria-label="<?= e(ucfirst($field) . ' mark for ' . $student['name']) ?>"></td><?php endforeach; ?><td><?= e($student['total'] !== null ? number_format((float) $student['total'], 1) : '—') ?></td><td><span class="badge badge-violet"><?= e($student['grade_letter'] ?? '—') ?></span></td></tr><?php endforeach; ?>
        </tbody></table></div><div class="panel-footer"><span class="muted">Percent = total ÷ 3. A: 90+, B: 80+, C: 70+, D: 60+, F: below 60.</span><button class="button button-primary" type="submit">Save grades</button></div></form><?php endif; ?><?php endif; ?>
    </section>

<?php elseif ($view === 'materials'):
    $materials = [];
    if ($selectedCourseId) {
        $materialQuery = $pdo->prepare('SELECT id, title, description, file_path, uploaded_on FROM materials WHERE course_id=? ORDER BY uploaded_on DESC');
        $materialQuery->execute([$selectedCourseId]);
        $materials = $materialQuery->fetchAll();
    }
?>
    <div class="two-column materials-layout">
        <section class="panel"><div class="panel-heading"><div><h2>Add course material</h2><p>Upload a document or add a web link for your students.</p></div></div>
        <?php if (!$courses): ?><div class="empty-state">No courses have been assigned to you yet.</div><?php else: ?><form method="post" enctype="multipart/form-data" class="stack-form" data-validate><?= csrf_field() ?><input type="hidden" name="action" value="add_material">
            <label for="material-course">Course</label><select name="course_id" id="material-course" required><?php foreach ($courses as $course): ?><option value="<?= (int) $course['id'] ?>" <?= $selectedCourseId === (int) $course['id'] ? 'selected' : '' ?>><?= e($course['code'] . ' · ' . $course['name']) ?></option><?php endforeach; ?></select>
            <label for="title">Title</label><input id="title" name="title" required maxlength="160">
            <label for="description">Description</label><textarea id="description" name="description" rows="3" maxlength="2000"></textarea>
            <label for="material_type">Material type</label><select id="material_type" name="material_type" data-material-type><option value="file">Upload a file</option><option value="link">Add a link</option></select>
            <div data-material-file><label for="material_file">File (PDF, images, Office docs; max 5 MB)</label><input id="material_file" type="file" name="material_file" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx,.ppt,.pptx" required></div>
            <div data-material-url hidden><label for="material_url">Web address</label><input id="material_url" name="material_url" type="url" placeholder="https://example.edu/resource" disabled></div>
            <button class="button button-primary" type="submit">Add to course</button>
        </form><?php endif; ?>
        </section>
        <section class="panel"><div class="panel-heading"><div><h2>Shared materials</h2><p>Course resources your students can open</p></div><?php if ($courses): ?><select id="materials-course" aria-label="View materials for a course"><?php foreach ($courses as $course): ?><option value="<?= (int) $course['id'] ?>" <?= $selectedCourseId === (int) $course['id'] ? 'selected' : '' ?>><?= e($course['code']) ?></option><?php endforeach; ?></select><?php endif; ?></div>
            <?php if (!$materials): ?><div class="empty-state">No materials are shared for this course yet.</div><?php else: ?><div class="material-list"><?php foreach ($materials as $material): ?><article class="material-row"><span class="material-icon"><?= filter_var($material['file_path'], FILTER_VALIDATE_URL) ? '↗' : '↧' ?></span><div><strong><?= e($material['title']) ?></strong><p><?= e($material['description']) ?></p><small>Added <?= e(date('M j, Y', strtotime($material['uploaded_on']))) ?></small></div><form method="post" data-confirm="Remove this course material?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_material"><input type="hidden" name="material_id" value="<?= (int) $material['id'] ?>"><button class="text-link danger-link" type="submit">Remove</button></form></article><?php endforeach; ?></div><?php endif; ?>
        </section>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
