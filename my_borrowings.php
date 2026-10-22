<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$rows = fetch_all("SELECT l.loan_id, l.book_id, l.borrow_date, l.due_date, l.return_date, l.status,
                          (l.status = 'borrowed' AND l.due_date < NOW()) AS is_overdue, b.title, b.author
                   FROM borrowings l JOIN books b ON b.book_id = l.book_id
                   WHERE l.user_id = ? ORDER BY l.status = 'borrowed' DESC, l.borrow_date DESC", 'i', [$user['user_id']]);
$active  = array_filter($rows, fn($r) => $r['status'] === 'borrowed');
$history = array_filter($rows, fn($r) => $r['status'] === 'returned');

page_header('My borrowings', 'borrowing');
?>
<h1>My borrowings</h1>
<h2>On loan now <span class="muted">(<?= count($active) ?> of <?= MAX_ACTIVE_LOANS ?>)</span></h2>
<?php if (!$active): ?>
  <p class="empty">You have no books on loan. <a href="<?= e(url('books.php')) ?>">Browse the catalogue</a> to borrow one.</p>
<?php else: ?>
<div class="table-wrap"><table>
  <thead><tr><th>Book</th><th>Borrowed</th><th>Due</th><th><span class="sr">Actions</span></th></tr></thead>
  <tbody>
  <?php foreach ($active as $r): ?>
    <tr>
      <td><a href="<?= e(url('book.php?id=' . $r['book_id'])) ?>"><?= e($r['title']) ?></a><br><span class="muted"><?= e($r['author']) ?></span></td>
      <td><?= e(fmt_date($r['borrow_date'])) ?></td>
      <td><?= e(fmt_date($r['due_date'])) ?> <?= $r['is_overdue'] ? '<span class="badge bad">Overdue</span>' : '' ?></td>
      <td class="actions">
        <?php if (!$r['is_overdue']): ?><a class="btn small" href="<?= e(url('read.php?id=' . $r['book_id'])) ?>">Read</a><?php endif; ?>
        <form method="post" action="<?= e(url('borrow.php')) ?>"><?= csrf_field() ?>
          <input type="hidden" name="action" value="return"><input type="hidden" name="loan_id" value="<?= (int) $r['loan_id'] ?>">
          <button class="btn ghost small" type="submit">Return</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>

<h2>History</h2>
<?php if (!$history): ?>
  <p class="empty">Books you return will appear here.</p>
<?php else: ?>
<div class="table-wrap"><table>
  <thead><tr><th>Book</th><th>Borrowed</th><th>Returned</th></tr></thead>
  <tbody>
  <?php foreach ($history as $r): ?>
    <tr>
      <td><a href="<?= e(url('book.php?id=' . $r['book_id'])) ?>"><?= e($r['title']) ?></a><br><span class="muted"><?= e($r['author']) ?></span></td>
      <td><?= e(fmt_date($r['borrow_date'])) ?></td>
      <td><?= e(fmt_date($r['return_date'])) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif;
page_footer();
