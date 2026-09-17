<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['student']);
$page_title = 'Accomplishment Reports';
require_once __DIR__ . '/../includes/header.php';

$stmt = $conn->prepare("SELECT ar.week_number, ar.report_title, ar.status, ar.submitted_at, ar.remarks
    FROM accomplishment_reports ar JOIN internships i ON i.internship_id=ar.internship_id
    WHERE i.student_id=? ORDER BY ar.week_number DESC");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result=$stmt->get_result();
?>
<section class="panel">
<div class="panel-head"><div><h2>Weekly Accomplishment Reports</h2><p>Submit and monitor weekly OJT accomplishments.</p></div>
<button class="btn primary" type="button" onclick="alert('Report form will be added in the next development step.')">+ New Report</button></div>
<div class="table-wrap"><table><thead><tr><th>Week</th><th>Title</th><th>Status</th><th>Submitted</th><th>Remarks</th></tr></thead><tbody>
<?php while($row=$result->fetch_assoc()): ?>
<tr><td>Week <?= (int)$row['week_number'] ?></td><td><?= htmlspecialchars($row['report_title']) ?></td>
<td><span class="badge <?= htmlspecialchars($row['status']) ?>"><?= htmlspecialchars(ucfirst($row['status'])) ?></span></td>
<td><?= htmlspecialchars($row['submitted_at']) ?></td><td><?= htmlspecialchars($row['remarks'] ?? '-') ?></td></tr>
<?php endwhile; ?>
</tbody></table></div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
