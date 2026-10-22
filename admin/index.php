<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$stats = [
    'Total books'     => (int) fetch_value('SELECT COUNT(*) FROM books'),
    'Total users'     => (int) fetch_value('SELECT COUNT(*) FROM users'),
    'Borrowed books'  => (int) fetch_value("SELECT COUNT(*) FROM borrowings WHERE status = 'borrowed'"),
    'Overdue returns' => (int) fetch_value("SELECT COUNT(*) FROM borrowings WHERE status = 'borrowed' AND due_date < NOW()"),
    'Reviews'         => (int) fetch_value('SELECT COUNT(*) FROM reviews'),
];
$copies = fetch_one('SELECT COALESCE(SUM(total_copies), 0) AS total, COALESCE(SUM(available_copies), 0) AS free FROM books');
$recent = fetch_all("SELECT l.borrow_date, l.due_date, l.status, (l.status = 'borrowed' AND l.due_date < NOW()) AS is_overdue, u.name, b.title
                     FROM borrowings l JOIN users u ON u.user_id = l.user_id JOIN books b ON b.book_id = l.book_id
                     ORDER BY l.borrow_date DESC LIMIT 8");
$empty = fetch_all('SELECT book_id, title, author, total_copies FROM books WHERE available_copies = 0 ORDER BY title LIMIT 10');

admin_header('Dashboard', 'dashboard');
?>
<div class="stats">
  <?php foreach ($stats as $label => $n): ?>
    <div class="stat<?= $label === 'Overdue returns' && $n > 0 ? ' alert' : '' ?>"><strong><?= $n ?></strong><span><?= e($label) ?></span></div>
  <?php endforeach; ?>
  <div class="stat"><strong><?= (int) $copies['free'] ?> / <?= (int) $copies['total'] ?></strong><span>Copies available</span></div>
</div>

<h2>Recent borrowings</h2>
<?php if (!$recent): ?><p class="empty">No one has borrowed a book yet.</p><?php else: ?>
<div class="table-wrap"><table>
  <thead><tr><th>Reader</th><th>Book</th><th>Borrowed</th><th>Status</th></tr></thead>
  <tbody><?php foreach ($recent as $r): ?>
    <tr><td><?= e($r['name']) ?></td><td><?= e($r['title']) ?></td><td><?= e(fmt_date($r['borrow_date'])) ?></td>
      <td><?= $r['is_overdue'] ? '<span class="badge bad">Overdue</span>' : ($r['status'] === 'borrowed' ? '<span class="badge warn">Borrowed</span>' : '<span class="badge ok">Returned</span>') ?></td></tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php endif; ?>

<h2>Needs restocking</h2>
<?php if (!$empty): ?><p class="empty">Every title has at least one copy available.</p><?php else: ?>
<ul class="plain"><?php foreach ($empty as $b): ?>
  <li><a href="<?= e(url('admin/book_form.php?id=' . $b['book_id'])) ?>"><?= e($b['title']) ?></a> <span class="muted">by <?= e($b['author']) ?> · all <?= (int) $b['total_copies'] ?> on loan</span></li>
<?php endforeach; ?></ul>
<?php endif;
admin_footer();
