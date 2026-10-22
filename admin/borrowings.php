<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    try {
        $title = return_loan((int) ($_POST['loan_id'] ?? 0));
        flash('success', 'Marked “' . $title . '” as returned.');
    } catch (UserError $e) {
        flash('error', $e->getMessage());
    }
    redirect(url('admin/borrowings.php') . (isset($_POST['filter']) && in_array($_POST['filter'], ['borrowed', 'overdue', 'returned'], true) ? '?status=' . $_POST['filter'] : ''));
}

$filter = in_array($_GET['status'] ?? '', ['borrowed', 'overdue', 'returned'], true) ? $_GET['status'] : 'all';
$where = ['borrowed' => "WHERE l.status = 'borrowed'", 'overdue' => "WHERE l.status = 'borrowed' AND l.due_date < NOW()",
          'returned' => "WHERE l.status = 'returned'", 'all' => ''][$filter];
$rows = fetch_all("SELECT l.loan_id, l.borrow_date, l.due_date, l.return_date, l.status,
                          (l.status = 'borrowed' AND l.due_date < NOW()) AS is_overdue, u.name, u.email, b.title
                   FROM borrowings l JOIN users u ON u.user_id = l.user_id JOIN books b ON b.book_id = l.book_id
                   $where ORDER BY l.status = 'borrowed' DESC, l.due_date ASC LIMIT 200");

admin_header('Borrowings', 'borrowings');
?>
<nav class="tabs" aria-label="Filter">
  <?php foreach (['all' => 'All', 'borrowed' => 'On loan', 'overdue' => 'Overdue', 'returned' => 'Returned'] as $k => $label): ?>
    <a href="?status=<?= $k ?>"<?= $k === $filter ? ' class="current" aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>
<?php if (!$rows): ?><p class="empty">No borrowings in this view.</p><?php else: ?>
<div class="table-wrap"><table>
  <thead><tr><th>Reader</th><th>Book</th><th>Borrowed</th><th>Due</th><th>Status</th><th><span class="sr">Actions</span></th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['name']) ?><br><span class="muted"><?= e($r['email']) ?></span></td>
      <td><?= e($r['title']) ?></td>
      <td><?= e(fmt_date($r['borrow_date'])) ?></td>
      <td><?= e(fmt_date($r['due_date'])) ?></td>
      <td><?= $r['is_overdue'] ? '<span class="badge bad">Overdue</span>' : ($r['status'] === 'borrowed' ? '<span class="badge warn">On loan</span>' : '<span class="badge ok">Returned ' . e(fmt_date($r['return_date'])) . '</span>') ?></td>
      <td class="actions"><?php if ($r['status'] === 'borrowed'): ?>
        <form method="post" data-confirm="Mark “<?= e($r['title']) ?>” as returned by <?= e($r['name']) ?>?"><?= csrf_field() ?>
          <input type="hidden" name="loan_id" value="<?= (int) $r['loan_id'] ?>"><input type="hidden" name="filter" value="<?= e($filter) ?>">
          <button class="btn ghost small" type="submit">Mark returned</button></form>
      <?php endif; ?></td>
    </tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php endif;
admin_footer();
