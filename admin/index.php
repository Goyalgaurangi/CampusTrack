<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = require_role('admin');
$pdo = db();
$view = (string) ($_GET['view'] ?? 'dashboard');
if (!in_array($view, ['dashboard', 'courses', 'users', 'assignments'], true)) {
    http_response_code(404);
    require __DIR__ . '/../errors/404.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'save_course') {
            $id = (int) ($_POST['id'] ?? 0);
            $code = strtoupper(post_string('code', 20));
            $name = post_string('name', 140);
            $credits = filter_var($_POST['credits'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
            $semester = filter_var($_POST['semester'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
            $facultyId = ($_POST['faculty_id'] ?? '') !== '' ? (int) $_POST['faculty_id'] : null;
            if ($code === '' || $name === '' || $credits === false || $semester === false) {
                throw new InvalidArgumentException('Enter a course code, name, valid credit count, and semester.');
            }
            if ($facultyId !== null) {
                $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'faculty'");
                $check->execute([$facultyId]);
                if (!$check->fetchColumn()) {
                    throw new InvalidArgumentException('Choose a valid faculty member.');
                }
            }
            if ($id > 0) {
                $statement = $pdo->prepare('UPDATE courses SET code = ?, name = ?, credits = ?, semester = ?, faculty_id = ? WHERE id = ?');
                $statement->execute([$code, $name, $credits, $semester, $facultyId, $id]);
            } else {
                $statement = $pdo->prepare('INSERT INTO courses (code, name, credits, semester, faculty_id) VALUES (?, ?, ?, ?, ?)');
                $statement->execute([$code, $name, $credits, $semester, $facultyId]);
            }
            flash('success', 'Course saved.');
            go('index.php?view=courses');
        }
        if ($action === 'delete_course') {
            $statement = $pdo->prepare('DELETE FROM courses WHERE id = ?');
            $statement->execute([(int) ($_POST['id'] ?? 0)]);
            flash('success', 'Course and its related records were deleted.');
            go('index.php?view=courses');
        }
        if ($action === 'save_user') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = post_string('name', 120);
            $email = strtolower(post_string('email', 190));
            $role = (string) ($_POST['role'] ?? '');
            $department = post_string('department', 120);
            $phone = post_string('phone', 30);
            $identifier = strtoupper(post_string('identifier', 40));
            $password = (string) ($_POST['password'] ?? '');
            if ($name === '' || !is_valid_email($email) || !in_array($role, ['admin', 'faculty', 'student'], true)) {
                throw new InvalidArgumentException('Enter a name, valid email, and account role.');
            }
            if ($id === 0 && $password === '') {
                $password = $role === 'admin' ? 'admin123' : ($role === 'faculty' ? 'faculty123' : 'student123');
            }
            if ($password !== '' && strlen($password) < 8) {
                throw new InvalidArgumentException('Use at least 8 characters for the password.');
            }
            if ($id === 0 && $identifier === '' && $role !== 'admin') {
                $prefix = $role === 'faculty' ? 'FAC' : 'STU';
                $identifier = $prefix . '-' . date('ymd') . '-' . random_int(100, 999);
            }
            if ($id > 0) {
                $oldQuery = $pdo->prepare('SELECT role, identifier FROM users WHERE id = ?');
                $oldQuery->execute([$id]);
                $old = $oldQuery->fetch();
                if (!$old) {
                    throw new InvalidArgumentException('That account no longer exists.');
                }
                if ($id === (int) $admin['id'] && $role !== 'admin') {
                    throw new InvalidArgumentException('You cannot change the role of the account you are currently using.');
                }
                if ($identifier === '') {
                    $identifier = $old['identifier'] ?? '';
                }
                $sql = 'UPDATE users SET name = ?, email = ?, role = ?, department = ?, phone = ?, identifier = ?';
                $values = [$name, $email, $role, $department, $phone, $identifier !== '' ? $identifier : null];
                if ($password !== '') {
                    $sql .= ', password_hash = ?';
                    $values[] = password_hash($password, PASSWORD_DEFAULT);
                }
                $sql .= ' WHERE id = ?';
                $values[] = $id;
                $pdo->prepare($sql)->execute($values);
            } else {
                $statement = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, department, phone, identifier) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $department, $phone, $identifier !== '' ? $identifier : null]);
            }
            flash('success', $id > 0 ? 'Account updated.' : 'Account created. The initial password follows the selected role’s demo default unless you set one.');
            go('index.php?view=users');
        }
        if ($action === 'delete_user') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === (int) $admin['id']) {
                throw new InvalidArgumentException('You cannot delete the account you are currently using.');
            }
            $pdo->prepare('DELETE FROM users WHERE id = ? AND role <> \'admin\'')->execute([$id]);
            flash('success', 'Account deleted. Its student records were removed; faculty assignments were cleared.');
            go('index.php?view=users');
        }
        if ($action === 'reset_password') {
            $password = (string) ($_POST['new_password'] ?? '');
            if (strlen($password) < 8) {
                throw new InvalidArgumentException('Use at least 8 characters for the new password.');
            }
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), (int) ($_POST['id'] ?? 0)]);
            flash('success', 'Password reset.');
            go('index.php?view=users');
        }
        if ($action === 'enroll_student') {
            $studentId = (int) ($_POST['student_id'] ?? 0);
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'student'");
            $check->execute([$studentId]);
            if (!$check->fetchColumn()) {
                throw new InvalidArgumentException('Choose a valid student.');
            }
            $pdo->prepare('INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (?, ?)')->execute([$studentId, $courseId]);
            flash('success', 'Student enrolled in the selected course.');
            go('index.php?view=assignments&course_id=' . $courseId);
        }
        if ($action === 'remove_enrollment') {
            $pdo->prepare('DELETE FROM enrollments WHERE student_id = ? AND course_id = ?')->execute([(int) ($_POST['student_id'] ?? 0), (int) ($_POST['course_id'] ?? 0)]);
            flash('success', 'Enrollment removed.');
            go('index.php?view=assignments&course_id=' . (int) ($_POST['course_id'] ?? 0));
        }
        throw new InvalidArgumentException('That action is not available.');
    } catch (InvalidArgumentException $exception) {
        flash('error', $exception->getMessage());
    } catch (PDOException $exception) {
        error_log('CampusTrack admin action failed: ' . $exception->getMessage());
        flash('error', 'Could not save these changes. Check for a duplicate email or course code and try again.');
    }
    go('index.php?view=' . rawurlencode($view));
}

$pageTitle = ucfirst($view);
require __DIR__ . '/../includes/header.php';
$editId = (int) ($_GET['edit'] ?? 0);
?>
<div class="page-heading"><div><p class="eyebrow">CAMPUS ADMINISTRATION</p><h1><?= $view === 'dashboard' ? 'Good day, ' . e($admin['name']) : e(ucfirst($view)) ?></h1><p class="muted"><?= $view === 'dashboard' ? 'A quick read on how your campus is doing today.' : 'Manage the information behind your academic workspace.' ?></p></div><?php if ($view === 'users'): ?><a class="button button-primary" href="index.php?view=users&new=1">+ Add account</a><?php endif; ?></div>

<?php if ($view === 'dashboard'):
    $studentCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
    $facultyCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='faculty'")->fetchColumn();
    $courseCount = (int) $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
    $today = $pdo->query("SELECT COUNT(*) AS total, SUM(status = 'Present') AS present FROM attendance WHERE attendance_date = CURDATE()")->fetch();
    $recent = $pdo->query("SELECT a.attendance_date, a.status, u.name AS student, c.code AS course_code FROM attendance a JOIN users u ON u.id=a.student_id JOIN courses c ON c.id=a.course_id ORDER BY a.attendance_date DESC, a.id DESC LIMIT 6")->fetchAll();
?>
    <div class="stat-grid">
        <article class="stat-card"><span class="stat-icon icon-blue">ST</span><span class="stat-label">Students</span><strong><?= $studentCount ?></strong><small>Active student accounts</small></article>
        <article class="stat-card"><span class="stat-icon icon-teal">FC</span><span class="stat-label">Faculty</span><strong><?= $facultyCount ?></strong><small>Teaching staff</small></article>
        <article class="stat-card"><span class="stat-icon icon-violet">CR</span><span class="stat-label">Courses</span><strong><?= $courseCount ?></strong><small>Available course catalog</small></article>
        <article class="stat-card"><span class="stat-icon icon-amber">AT</span><span class="stat-label">Attendance today</span><strong><?= (int) ($today['total'] ?? 0) > 0 ? round(100 * (int) $today['present'] / (int) $today['total']) . '%' : '—' ?></strong><small><?= (int) ($today['present'] ?? 0) ?> of <?= (int) ($today['total'] ?? 0) ?> marked present</small></article>
    </div>
    <section class="panel"><div class="panel-heading"><div><h2>Recent attendance entries</h2><p>Latest course records across campus</p></div><a class="text-link" href="index.php?view=courses">Manage courses →</a></div>
        <?php if (!$recent): ?><div class="empty-state">No attendance has been recorded yet.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Student</th><th>Course</th><th>Date</th><th>Status</th></tr></thead><tbody><?php foreach ($recent as $row): ?><tr><td><?= e($row['student']) ?></td><td><?= e($row['course_code']) ?></td><td><?= e($row['attendance_date']) ?></td><td><span class="badge <?= $row['status'] === 'Present' ? 'badge-green' : 'badge-red' ?>"><?= e($row['status']) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>

<?php elseif ($view === 'courses'):
    $faculty = $pdo->query("SELECT id, name FROM users WHERE role='faculty' ORDER BY name")->fetchAll();
    $courses = $pdo->query('SELECT c.*, u.name AS faculty_name, (SELECT COUNT(*) FROM enrollments e WHERE e.course_id=c.id) AS student_count FROM courses c LEFT JOIN users u ON u.id=c.faculty_id ORDER BY c.semester, c.code')->fetchAll();
    $editing = null;
    if ($editId) {
        $q = $pdo->prepare('SELECT * FROM courses WHERE id=?');
        $q->execute([$editId]);
        $editing = $q->fetch();
    }
?>
    <section class="panel"><div class="panel-heading"><div><h2><?= $editing ? 'Edit course' : 'Add a course' ?></h2><p>Course details and the assigned faculty member</p></div></div>
        <form method="post" class="form-grid" data-validate><?= csrf_field() ?><input type="hidden" name="action" value="save_course"><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
            <div><label for="code">Course code</label><input id="code" name="code" required maxlength="20" value="<?= e($editing['code'] ?? '') ?>" placeholder="CS210"></div>
            <div><label for="name">Course name</label><input id="name" name="name" required maxlength="140" value="<?= e($editing['name'] ?? '') ?>" placeholder="Web Application Development"></div>
            <div><label for="credits">Credits</label><input id="credits" name="credits" type="number" min="1" max="12" required value="<?= e($editing['credits'] ?? 3) ?>"></div>
            <div><label for="semester">Semester</label><input id="semester" name="semester" type="number" min="1" max="12" required value="<?= e($editing['semester'] ?? 1) ?>"></div>
            <div><label for="faculty_id">Faculty</label><select id="faculty_id" name="faculty_id"><option value="">Unassigned</option><?php foreach ($faculty as $person): ?><option value="<?= (int) $person['id'] ?>" <?= (int) ($editing['faculty_id'] ?? 0) === (int) $person['id'] ? 'selected' : '' ?>><?= e($person['name']) ?></option><?php endforeach; ?></select></div>
            <div class="form-actions"><button class="button button-primary" type="submit"><?= $editing ? 'Save course' : 'Add course' ?></button><?php if ($editing): ?><a class="button button-quiet" href="index.php?view=courses">Cancel edit</a><?php endif; ?></div>
        </form>
    </section>
    <section class="panel"><div class="panel-heading"><div><h2>Course catalog</h2><p><?= count($courses) ?> courses in the catalog</p></div></div><div class="table-wrap"><table><thead><tr><th>Course</th><th>Credits</th><th>Semester</th><th>Faculty</th><th>Enrolled</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($courses as $course): ?><tr><td><strong><?= e($course['code']) ?></strong><small class="table-subtitle"><?= e($course['name']) ?></small></td><td><?= (int) $course['credits'] ?></td><td><?= (int) $course['semester'] ?></td><td><?= e($course['faculty_name'] ?: 'Unassigned') ?></td><td><?= (int) $course['student_count'] ?></td><td class="action-cell"><a class="text-link" href="index.php?view=courses&edit=<?= (int) $course['id'] ?>">Edit</a><form method="post" data-confirm="Delete this course, its enrollments, attendance and grades? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="action" value="delete_course"><input type="hidden" name="id" value="<?= (int) $course['id'] ?>"><button class="text-link danger-link" type="submit">Delete</button></form></td></tr><?php endforeach; ?>
    </tbody></table></div></section>

<?php elseif ($view === 'users'):
    $editing = null;
    if ($editId) {
        $q = $pdo->prepare('SELECT id, name, email, role, department, phone, identifier FROM users WHERE id=?');
        $q->execute([$editId]);
        $editing = $q->fetch();
    }
    $userRows = $pdo->query('SELECT id, name, email, role, department, phone, identifier, created_at FROM users ORDER BY FIELD(role, "admin", "faculty", "student"), name')->fetchAll();
?>
    <?php if (isset($_GET['new']) || $editing): ?><section class="panel"><div class="panel-heading"><div><h2><?= $editing ? 'Edit account' : 'Create an account' ?></h2><p>Staff and student records are available immediately after saving.</p></div></div>
        <form method="post" class="form-grid" data-validate><?= csrf_field() ?><input type="hidden" name="action" value="save_user"><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
            <div><label for="name">Full name</label><input id="name" name="name" required maxlength="120" value="<?= e($editing['name'] ?? '') ?>"></div>
            <div><label for="email">Email</label><input id="email" name="email" type="email" required maxlength="190" value="<?= e($editing['email'] ?? '') ?>"></div>
            <div><label for="role">Role</label><select id="role" name="role" required><option value="">Select role</option><?php foreach (['admin', 'faculty', 'student'] as $role): ?><option value="<?= $role ?>" <?= ($editing['role'] ?? '') === $role ? 'selected' : '' ?>><?= e(ucfirst($role)) ?></option><?php endforeach; ?></select></div>
            <div><label for="department">Department</label><input id="department" name="department" maxlength="120" value="<?= e($editing['department'] ?? '') ?>"></div>
            <div><label for="phone">Phone</label><input id="phone" name="phone" maxlength="30" value="<?= e($editing['phone'] ?? '') ?>"></div>
            <div><label for="identifier">Roll / employee ID</label><input id="identifier" name="identifier" maxlength="40" value="<?= e($editing['identifier'] ?? '') ?>" placeholder="Generated if left blank"></div>
            <div class="form-span"><label for="password">Initial password <?= $editing ? '(leave blank to keep current)' : '(leave blank for role default)' ?></label><input id="password" name="password" type="password" minlength="8" autocomplete="new-password"></div>
            <div class="form-actions"><button class="button button-primary" type="submit"><?= $editing ? 'Save account' : 'Create account' ?></button><a class="button button-quiet" href="index.php?view=users">Cancel</a></div>
        </form>
    </section><?php endif; ?>
    <section class="panel"><div class="panel-heading"><div><h2>People</h2><p>Search, filter, update, and reset account access.</p></div><div class="toolbar"><select id="role-filter" aria-label="Filter by role"><option value="">All roles</option><option value="admin">Admin</option><option value="faculty">Faculty</option><option value="student">Student</option></select><select id="department-filter" aria-label="Filter by department"><option value="">All departments</option><?php foreach (array_unique(array_filter(array_column($userRows, 'department'))) as $department): ?><option value="<?= e($department) ?>"><?= e($department) ?></option><?php endforeach; ?></select><input id="user-search" type="search" placeholder="Search name, email…" aria-label="Search people"></div></div>
        <div class="table-wrap"><table><thead><tr><th>Name / ID</th><th>Email</th><th>Role</th><th>Department</th><th>Access</th><th>Actions</th></tr></thead><tbody id="users-table-body">
        <?php foreach ($userRows as $person): ?><tr data-user-row data-role="<?= e($person['role']) ?>"><td><strong><?= e($person['name']) ?></strong><small class="table-subtitle"><?= e($person['identifier'] ?: 'No ID assigned') ?></small></td><td><?= e($person['email']) ?></td><td><span class="badge badge-blue"><?= e(ucfirst($person['role'])) ?></span></td><td><?= e($person['department']) ?></td><td><form method="post" class="inline-reset" data-validate><?= csrf_field() ?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?= (int) $person['id'] ?>"><input name="new_password" type="password" minlength="8" required placeholder="New password" aria-label="New password for <?= e($person['name']) ?>"><button class="text-link" type="submit">Reset</button></form></td><td class="action-cell"><a class="text-link" href="index.php?view=users&edit=<?= (int) $person['id'] ?>">Edit</a><?php if ((int) $person['id'] !== (int) $admin['id']): ?><form method="post" data-confirm="Delete <?= e($person['name']) ?> and their student records? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?= (int) $person['id'] ?>"><button class="text-link danger-link" type="submit">Delete</button></form><?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table></div><p class="table-note" id="search-status"><?= count($userRows) ?> accounts loaded</p>
    </section>

<?php elseif ($view === 'assignments'):
    $courses = $pdo->query('SELECT id, code, name FROM courses ORDER BY code')->fetchAll();
    $students = $pdo->query("SELECT id, name, identifier FROM users WHERE role='student' ORDER BY name")->fetchAll();
    $selectedCourseId = (int) ($_GET['course_id'] ?? ($courses[0]['id'] ?? 0));
    $enrolled = [];
    if ($selectedCourseId) {
        $statement = $pdo->prepare("SELECT u.id, u.name, u.email, u.identifier FROM enrollments e JOIN users u ON u.id=e.student_id WHERE e.course_id=? AND u.role='student' ORDER BY u.name");
        $statement->execute([$selectedCourseId]);
        $enrolled = $statement->fetchAll();
    }
?>
    <div class="two-column">
        <section class="panel"><div class="panel-heading"><div><h2>Enroll a student</h2><p>Add a student to a course roster.</p></div></div>
            <form method="post" class="stack-form" data-validate><?= csrf_field() ?><input type="hidden" name="action" value="enroll_student">
                <label for="course_id">Course</label><select name="course_id" id="course_id" required><?php foreach ($courses as $course): ?><option value="<?= (int) $course['id'] ?>" <?= $selectedCourseId === (int) $course['id'] ? 'selected' : '' ?>><?= e($course['code'] . ' · ' . $course['name']) ?></option><?php endforeach; ?></select>
                <label for="student_id">Student</label><select name="student_id" id="student_id" required><?php foreach ($students as $student): ?><option value="<?= (int) $student['id'] ?>"><?= e($student['name'] . ' · ' . $student['identifier']) ?></option><?php endforeach; ?></select>
                <button class="button button-primary" type="submit">Enroll student</button>
            </form>
        </section>
        <section class="panel"><div class="panel-heading"><div><h2>Course roster</h2><p><?= count($enrolled) ?> students enrolled</p></div><select id="roster-course" aria-label="View roster for a course"><?php foreach ($courses as $course): ?><option value="<?= (int) $course['id'] ?>" <?= $selectedCourseId === (int) $course['id'] ? 'selected' : '' ?>><?= e($course['code']) ?></option><?php endforeach; ?></select></div>
            <?php if (!$enrolled): ?><div class="empty-state">No students are enrolled in this course yet.</div><?php else: ?><div class="roster-list"><?php foreach ($enrolled as $student): ?><div class="roster-row"><span class="avatar avatar-small"><?= e(strtoupper(substr($student['name'], 0, 1))) ?></span><span><strong><?= e($student['name']) ?></strong><small><?= e($student['identifier']) ?></small></span><form method="post" data-confirm="Remove this student from the selected course?"><?= csrf_field() ?><input type="hidden" name="action" value="remove_enrollment"><input type="hidden" name="course_id" value="<?= $selectedCourseId ?>"><input type="hidden" name="student_id" value="<?= (int) $student['id'] ?>"><button class="text-link danger-link" type="submit">Remove</button></form></div><?php endforeach; ?></div><?php endif; ?>
        </section>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>