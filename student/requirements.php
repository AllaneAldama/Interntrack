<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['student']);
$page_title = 'Requirements';
require_once __DIR__ . '/../includes/header.php';

$stmt = $conn->prepare("SELECT r.requirement_name, r.status, r.remarks, r.submitted_at
    FROM requirements r JOIN internships i ON i.internship_id=r.internship_id
    WHERE i.student_id=? ORDER BY r.requirement_id");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
?>
<section class="panel">
<div class="panel-head"><div><h2>Internship Requirements</h2><p>Track the status of your required documents.</p></div></div>
<div class="table-wrap"><table><thead><tr><th>Requirement</th><th>Status</th><th>Submitted</th><th>Remarks</th></tr></thead><tbody>
<?php while ($row=$result->fetch_assoc()): ?>
<tr><td><?= htmlspecialchars($row['requirement_name']) ?></td>
<td><span class="badge <?= htmlspecialchars($row['status']) ?>"><?= htmlspecialchars(ucfirst($row['status'])) ?></span></td>
<td><?= htmlspecialchars($row['submitted_at'] ?? '-') ?></td>
<td><?= htmlspecialchars($row['remarks'] ?? '-') ?></td></tr>
<?php endwhile; ?>
</tbody></table></div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
