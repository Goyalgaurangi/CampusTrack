<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
$student = require_role('student');
$pdo = db();
$studentId = (int) $student['id'];
$view = (string) ($_GET['view'] ?? 'dashboard');
if (!in_array($view, ['dashboard', 'attendance', 'schedule', 'grades', 'materials', 'report'], true)) {
    http_response_code(404);
    require __DIR__ . '/../errors/404.php';
    exit;
}

$coursesQuery = $pdo->prepare("SELECT c.id, c.code, c.name, c.credits, c.semester, u.name AS faculty_name,
        COUNT(a.id) AS class_count,
        COALESCE(SUM(a.status = 'Present'), 0) AS present_count,
        COALESCE(100 * SUM(a.status = 'Present') / NULLIF(COUNT(a.id), 0), 0) AS attendance_pct
    FROM enrollments e
    JOIN courses c ON c.id=e.course_id
    LEFT JOIN users u ON u.id=c.faculty_id
    LEFT JOIN attendance a ON a.course_id=c.id AND a.student_id=e.student_id
    WHERE e.student_id=?
    GROUP BY c.id, c.code, c.name, c.credits, c.semester, u.name ORDER BY c.code");
$coursesQuery->execute([$studentId]);
$courses = $coursesQuery->fetchAll();
$overallAttendance = attendance_percent($pdo, $studentId);
$lowAttendance = array_filter($courses, static fn(array $course): bool => (int) $course['class_count'] > 0 && (float) $course['attendance_pct'] < 75);

$pageTitle = match ($view) {
    'dashboard' => 'Dashboard',
    'schedule' => 'Class schedule',
    'report' => 'Report card',
    default => ucfirst($view),
};
require __DIR__ . '/../includes/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">STUDENT WORKSPACE</p><h1><?= $view === 'dashboard' ? 'Hello, ' . e($student['name']) : e($pageTitle) ?></h1><p class="muted"><?= $view === 'dashboard' ? 'Your courses, attendance, and grades in one place.' : 'Your academic information, kept up to date.' ?></p></div><?php if ($view === 'report'): ?><button class="button button-primary no-print" type="button" onclick="window.print()">Download / print PDF</button><?php endif; ?></div>

<?php if ($view === 'dashboard'): ?>
    <div class="student-hero">
        <div><span class="eyebrow">YOUR OVERALL ATTENDANCE</span><h2><span data-live-total><?= number_format($overallAttendance, 1) ?></span><small>%</small></h2><p>Across <?= count($courses) ?> enrolled courses</p></div>
        <div class="hero-decoration"><span class="hero-orbit orbit-one"></span><span class="hero-orbit orbit-two"></span><span class="hero-dot"></span></div>
    </div>
    <?php if ($lowAttendance): ?><section class="warning-banner"><span class="warning-symbol">!</span><div><strong>Attendance needs attention</strong><p>These courses are below the 75% attendance guideline: <?php foreach ($lowAttendance as $key => $course): ?><?= $key ? ', ' : '' ?><strong><?= e($course['code']) ?> (<?= number_format((float) $course['attendance_pct'], 1) ?>%)</strong><?php endforeach; ?>.</p></div></section><?php endif; ?>
    <div class="stat-grid student-stats"><article class="stat-card"><span class="stat-icon icon-blue">CR</span><span class="stat-label">Enrolled courses</span><strong><?= count($courses) ?></strong><small>Current semester</small></article><article class="stat-card"><span class="stat-icon icon-teal">AT</span><span class="stat-label">Overall attendance</span><strong><?= number_format($overallAttendance, 1) ?>%</strong><small>Keep your classes on track</small></article><article class="stat-card"><span class="stat-icon icon-violet">GR</span><span class="stat-label">Grades recorded</span><strong><?php $gradeCountQuery = $pdo->prepare('SELECT COUNT(*) FROM grades g JOIN enrollments e ON e.student_id=g.student_id AND e.course_id=g.course_id WHERE e.student_id=?'); $gradeCountQuery->execute([$studentId]); echo (int) $gradeCountQuery->fetchColumn(); ?></strong><small>Course assessments</small></article></div>
    <section class="panel"><div class="panel-heading"><div><h2>Your courses</h2><p>Attendance and course contacts</p></div><a class="text-link" href="index.php?view=attendance">View all attendance →</a></div>
        <?php if (!$courses): ?><div class="empty-state">You are not enrolled in any courses yet. Check with your administrator.</div><?php else: ?><div class="course-summary-list"><?php foreach ($courses as $course): ?><article class="course-summary"><div class="course-summary-title"><span class="course-code"><?= e($course['code']) ?></span><h3><?= e($course['name']) ?></h3><p><?= e($course['faculty_name'] ?: 'Faculty not assigned') ?></p></div><div class="attendance-meter"><div class="meter-label"><span>Attendance</span><strong data-live-course="<?= (int) $course['id'] ?>"><?= number_format((float) $course['attendance_pct'], 1) ?>%</strong></div><div class="meter-track"><span style="width: <?= max(0, min(100, (float) $course['attendance_pct'])) ?>%" class="<?= (float) $course['attendance_pct'] < 75 ? 'meter-low' : '' ?>"></span></div></div><span class="badge <?= (float) $course['attendance_pct'] < 75 ? 'badge-red' : 'badge-green' ?>"><?= (int) $course['present_count'] ?>/<?= (int) $course['class_count'] ?> classes</span></article><?php endforeach; ?></div><?php endif; ?>
    </section>
    <script>window.CAMPUS_TRACK_LIVE_ATTENDANCE = true;</script>

<?php elseif ($view === 'attendance'): ?>
    <section class="panel"><div class="panel-heading"><div><h2>Course attendance</h2><p>Live summary of class records for your enrolled courses.</p></div><span class="badge badge-blue"><?= number_format($overallAttendance, 1) ?>% overall</span></div>
        <?php if (!$courses): ?><div class="empty-state">No course attendance is available yet.</div><?php else: ?><div class="chart-frame"><canvas id="attendanceChart" aria-label="Attendance percentage by course" role="img" data-chart-labels="<?= e(json_encode(array_column($courses, 'code'), JSON_HEX_APOS | JSON_HEX_QUOT)) ?>" data-chart-values="<?= e(json_encode(array_map(static fn(array $course): float => round((float) $course['attendance_pct'], 1), $courses))) ?>"></canvas></div><div class="table-wrap"><table><thead><tr><th>Course</th><th>Present</th><th>Classes</th><th>Attendance</th><th>Standing</th></tr></thead><tbody><?php foreach ($courses as $course): ?><tr><td><strong><?= e($course['code']) ?></strong><small class="table-subtitle"><?= e($course['name']) ?></small></td><td><?= (int) $course['present_count'] ?></td><td><?= (int) $course['class_count'] ?></td><td><strong data-live-course="<?= (int) $course['id'] ?>"><?= number_format((float) $course['attendance_pct'], 1) ?>%</strong></td><td><span class="badge <?= (float) $course['attendance_pct'] < 75 ? 'badge-red' : 'badge-green' ?>"><?= (float) $course['attendance_pct'] < 75 ? 'Below 75%' : 'On track' ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
    <script>window.CAMPUS_TRACK_LIVE_ATTENDANCE = true;</script>

<?php elseif ($view === 'schedule'):
    $scheduleQuery = $pdo->prepare("SELECT c.code, c.name, s.day, s.start_time, s.end_time, s.room, u.name AS faculty_name
        FROM enrollments e JOIN courses c ON c.id=e.course_id
        JOIN schedules s ON s.course_id=c.id LEFT JOIN users u ON u.id=c.faculty_id
        WHERE e.student_id=? ORDER BY FIELD(s.day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), s.start_time");
    $scheduleQuery->execute([$studentId]);
    $schedule = $scheduleQuery->fetchAll();
?>
    <section class="panel"><div class="panel-heading"><div><h2>Weekly timetable</h2><p>Your enrolled classes by weekday and time</p></div></div>
        <?php if (!$schedule): ?><div class="empty-state">No timetable has been added for your courses yet.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Day</th><th>Time</th><th>Course</th><th>Room</th><th>Faculty</th></tr></thead><tbody><?php foreach ($schedule as $item): ?><tr><td><span class="day-pill"><?= e($item['day']) ?></span></td><td><?= e(date('g:i A', strtotime($item['start_time']))) ?> – <?= e(date('g:i A', strtotime($item['end_time']))) ?></td><td><strong><?= e($item['code']) ?></strong><small class="table-subtitle"><?= e($item['name']) ?></small></td><td><?= e($item['room']) ?></td><td><?= e($item['faculty_name'] ?: '—') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>

<?php elseif ($view === 'grades'):
    $gradesQuery = $pdo->prepare("SELECT c.code, c.name, g.internal, g.midterm, g.final, g.total, g.percentage, g.grade_letter FROM enrollments e JOIN courses c ON c.id=e.course_id LEFT JOIN grades g ON g.course_id=e.course_id AND g.student_id=e.student_id WHERE e.student_id=? ORDER BY c.code");
    $gradesQuery->execute([$studentId]);
    $gradeRows = $gradesQuery->fetchAll();
?>
    <section class="panel"><div class="panel-heading"><div><h2>Course grades</h2><p>Assessment marks and calculated course standing</p></div><a class="text-link" href="index.php?view=report">View report card →</a></div>
        <?php if (!$gradeRows): ?><div class="empty-state">No grade records are available yet.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Course</th><th>Internal</th><th>Midterm</th><th>Final</th><th>Total / 300</th><th>Percentage</th><th>Grade</th></tr></thead><tbody><?php foreach ($gradeRows as $grade): ?><tr><td><strong><?= e($grade['code']) ?></strong><small class="table-subtitle"><?= e($grade['name']) ?></small></td><td><?= $grade['internal'] !== null ? number_format((float) $grade['internal'], 1) : '—' ?></td><td><?= $grade['midterm'] !== null ? number_format((float) $grade['midterm'], 1) : '—' ?></td><td><?= $grade['final'] !== null ? number_format((float) $grade['final'], 1) : '—' ?></td><td><?= $grade['total'] !== null ? number_format((float) $grade['total'], 1) : '—' ?></td><td><?= $grade['percentage'] !== null ? number_format((float) $grade['percentage'], 1) . '%' : '—' ?></td><td><span class="badge badge-violet"><?= e($grade['grade_letter'] ?? 'Pending') ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>

<?php elseif ($view === 'materials'):
    $materialsQuery = $pdo->prepare("SELECT m.id, m.title, m.description, m.file_path, m.uploaded_on, c.code, c.name
        FROM materials m JOIN courses c ON c.id=m.course_id
        JOIN enrollments e ON e.course_id=c.id
        WHERE e.student_id=? ORDER BY m.uploaded_on DESC");
    $materialsQuery->execute([$studentId]);
    $materials = $materialsQuery->fetchAll();
?>
    <section class="panel"><div class="panel-heading"><div><h2>Course materials</h2><p>Resources shared by your faculty</p></div></div>
        <?php if (!$materials): ?><div class="empty-state">No materials have been shared with you yet.</div><?php else: ?><div class="material-list"><?php foreach ($materials as $material): $external = (bool) filter_var($material['file_path'], FILTER_VALIDATE_URL); $href = $external ? $material['file_path'] : 'download.php?id=' . (int) $material['id']; ?><article class="material-row"><span class="material-icon"><?= $external ? '↗' : '↧' ?></span><div><strong><?= e($material['title']) ?></strong><p><?= e($material['description']) ?></p><small><?= e($material['code'] . ' · ' . $material['name']) ?> · <?= e(date('M j, Y', strtotime($material['uploaded_on']))) ?></small></div><a class="button button-quiet" href="<?= e($href) ?>" <?= $external ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><?= $external ? 'Open link' : 'Download' ?></a></article><?php endforeach; ?></div><?php endif; ?>
    </section>

<?php elseif ($view === 'report'):
    $reportQuery = $pdo->prepare("SELECT c.code, c.name, c.credits, g.internal, g.midterm, g.final, g.total, g.percentage, g.grade_letter,
            COUNT(a.id) AS class_count, COALESCE(SUM(a.status='Present'),0) AS present_count,
            COALESCE(100 * SUM(a.status='Present') / NULLIF(COUNT(a.id), 0), 0) AS attendance_pct
        FROM enrollments e JOIN courses c ON c.id=e.course_id
        LEFT JOIN grades g ON g.course_id=c.id AND g.student_id=e.student_id
        LEFT JOIN attendance a ON a.course_id=c.id AND a.student_id=e.student_id
        WHERE e.student_id=? GROUP BY c.id, c.code, c.name, c.credits, g.internal, g.midterm, g.final, g.total, g.percentage, g.grade_letter ORDER BY c.code");
    $reportQuery->execute([$studentId]);
    $reportRows = $reportQuery->fetchAll();
    $graded = array_values(array_filter($reportRows, static fn(array $row): bool => $row['percentage'] !== null));
    $average = count($graded) ? array_sum(array_map(static fn(array $row): float => (float) $row['percentage'], $graded)) / count($graded) : 0;
    $cgpa = number_format($average / 25, 2);
?>
    <section class="panel report-card" id="report-card"><div class="report-header"><div class="brand"><span class="brand-mark">C</span><span>Campus<span class="brand-light">Track</span></span></div><div class="report-title"><p class="eyebrow">ACADEMIC RECORD</p><h2>Student report card</h2><p>Generated <?= e(date('F j, Y')) ?></p></div></div>
        <div class="report-student"><div><small>Student</small><strong><?= e($student['name']) ?></strong></div><div><small>Roll number</small><strong><?= e($student['identifier'] ?: '—') ?></strong></div><div><small>Department</small><strong><?= e($student['department'] ?: '—') ?></strong></div><div><small>Email</small><strong><?= e($student['email']) ?></strong></div></div>
        <div class="report-summary"><div><small>Overall attendance</small><strong><?= number_format($overallAttendance, 1) ?>%</strong></div><div><small>Course average</small><strong><?= number_format($average, 1) ?>%</strong></div><div><small>CGPA (4.0 scale)</small><strong><?= e($cgpa) ?></strong></div><div><small>Courses</small><strong><?= count($reportRows) ?></strong></div></div>
        <?php if (!$reportRows): ?><div class="empty-state">No course records are available.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Course</th><th>Internal</th><th>Midterm</th><th>Final</th><th>Total</th><th>Percent</th><th>Grade</th><th>Attendance</th></tr></thead><tbody><?php foreach ($reportRows as $row): ?><tr><td><strong><?= e($row['code']) ?></strong><small class="table-subtitle"><?= e($row['name']) ?></small></td><td><?= $row['internal'] !== null ? number_format((float) $row['internal'], 1) : '—' ?></td><td><?= $row['midterm'] !== null ? number_format((float) $row['midterm'], 1) : '—' ?></td><td><?= $row['final'] !== null ? number_format((float) $row['final'], 1) : '—' ?></td><td><?= $row['total'] !== null ? number_format((float) $row['total'], 1) : '—' ?></td><td><?= $row['percentage'] !== null ? number_format((float) $row['percentage'], 1) . '%' : '—' ?></td><td><?= e($row['grade_letter'] ?? 'Pending') ?></td><td><?= (int) $row['present_count'] ?>/<?= (int) $row['class_count'] ?> (<?= number_format((float) $row['attendance_pct'], 1) ?>%)</td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        <p class="report-footnote">This report is generated from the CampusTrack academic record. CGPA is the course-average percentage converted to a 4.0 scale.</p>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
