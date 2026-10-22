<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $id = (int) ($_POST['book_id'] ?? 0);
    $book = fetch_one('SELECT title, file_path FROM books WHERE book_id = ?', 'i', [$id]);
    if (!$book) {
        flash('error', 'That book no longer exists.');
    } elseif ((int) fetch_value("SELECT COUNT(*) FROM borrowings WHERE book_id = ? AND status = 'borrowed'", 'i', [$id]) > 0) {
        flash('error', '“' . $book['title'] . '” is on loan. Wait for it to be returned (or return it from Borrowings) before deleting.');
    } else {
        exec_sql('DELETE FROM books WHERE book_id = ?', 'i', [$id]);
        delete_book_file($book['file_path'], $id);
        flash('success', 'Deleted “' . $book['title'] . '”.');
    }
    redirect(url('admin/books.php'));
}

$term = trim((string) ($_GET['q'] ?? ''));
$like = '%' . addcslashes($term, '%_\\') . '%';
$books = $term === ''
    ? fetch_all('SELECT * FROM books ORDER BY title')
    : fetch_all('SELECT * FROM books WHERE title LIKE ? OR author LIKE ? OR category LIKE ? ORDER BY title', 'sss', [$like, $like, $like]);

admin_header('Manage books', 'books');
?>
<div class="toolbar">
  <form method="get" role="search" class="inline"><label class="sr" for="q">Search books</label>
    <input id="q" type="search" name="q" value="<?= e($term) ?>" placeholder="Search title, author, category">
    <button class="btn ghost" type="submit">Search</button></form>
  <a class="btn" href="<?= e(url('admin/book_form.php')) ?>">Add a book</a>
</div>
<?php if (!$books): ?><p class="empty">No books found.</p><?php else: ?>
<div class="table-wrap"><table>
  <thead><tr><th>Title</th><th>Category</th><th>Copies</th><th>File</th><th><span class="sr">Actions</span></th></tr></thead>
  <tbody><?php foreach ($books as $b): ?>
    <tr>
      <td><strong><?= e($b['title']) ?></strong><br><span class="muted"><?= e($b['author']) ?></span></td>
      <td><?= e($b['category']) ?></td>
      <td><?= (int) $b['available_copies'] ?> / <?= (int) $b['total_copies'] ?></td>
      <td><?= $b['file_path'] ? strtoupper(pathinfo($b['file_path'], PATHINFO_EXTENSION)) : '<span class="muted">None</span>' ?></td>
      <td class="actions">
        <a class="btn ghost small" href="<?= e(url('admin/book_form.php?id=' . $b['book_id'])) ?>">Edit</a>
        <form method="post" data-confirm="Delete “<?= e($b['title']) ?>”? This also removes its reviews and loan history."><?= csrf_field() ?>
          <input type="hidden" name="book_id" value="<?= (int) $b['book_id'] ?>">
          <button class="btn danger small" type="submit">Delete</button></form>
      </td>
    </tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php endif;
admin_footer();
