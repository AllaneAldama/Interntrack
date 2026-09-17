<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['student']);
$page_title = 'Attendance';
require_once __DIR__ . '/../includes/header.php';

$stmt = $conn->prepare("SELECT a.attendance_date, a.time_in, a.time_out, a.hours_rendered, a.status
    FROM attendance a JOIN internships i ON i.internship_id=a.internship_id
    WHERE i.student_id=? ORDER BY a.attendance_date DESC");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result=$stmt->get_result();
?>
<section class="panel">
<div class="panel-head"><div><h2>Attendance Records</h2><p>Monitor your rendered internship hours.</p></div></div>
<div class="table-wrap"><table><thead><tr><th>Date</th><th>Time In</th><th>Time Out</th><th>Hours</th><th>Status</th></tr></thead><tbody>
<?php while($row=$result->fetch_assoc()): ?>
<tr><td><?= htmlspecialchars($row['attendance_date']) ?></td><td><?= htmlspecialchars($row['time_in'] ?? '-') ?></td>
<td><?= htmlspecialchars($row['time_out'] ?? '-') ?></td><td><?= htmlspecialchars($row['hours_rendered']) ?></td>
<td><span class="badge"><?= htmlspecialchars(ucfirst($row['status'])) ?></span></td></tr>
<?php endwhile; ?>
</tbody></table></div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
