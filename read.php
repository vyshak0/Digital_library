<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$id   = (int) ($_GET['id'] ?? 0);
$book = fetch_one('SELECT book_id, title, author, file_path FROM books WHERE book_id = ?', 'i', [$id]);
if (!$book) fail_page(404, 'Book not found', 'This book is not in the catalogue.');

// Readers need an active loan that is not overdue. Admins can open any file to check uploads.
$allowed = is_admin() || (bool) fetch_value(
    "SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND book_id = ? AND status = 'borrowed' AND due_date >= NOW()",
    'ii', [$user['user_id'], $id]);
if (!$allowed) {
    fail_page(403, 'Borrow this book to read it', 'You need an active loan for “' . $book['title'] . '”. Borrow it from its page, or return any overdue copy first.');
}

$file = $book['file_path'] ? UPLOAD_DIR . '/' . basename($book['file_path']) : null;
$hasFile = $file && is_file($file);
$ext = $hasFile ? strtolower(pathinfo($file, PATHINFO_EXTENSION)) : '';

// The file itself: streamed only after the checks above, never linked directly.
if ($hasFile && isset($_GET['raw'])) {
    header('Content-Type: ' . ($ext === 'pdf' ? 'application/pdf' : 'application/epub+zip'));
    header('Content-Disposition: ' . ($ext === 'pdf' ? 'inline' : 'attachment') . '; filename="book-' . $id . '.' . $ext . '"');
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($file);
    exit;
}

page_header('Reading ' . $book['title'], 'browse');
?>
<p class="crumb"><a href="<?= e(url('book.php?id=' . $id)) ?>">← Back to book page</a></p>
<h1><?= e($book['title']) ?></h1>
<p class="byline">by <?= e($book['author']) ?></p>
<?php if (!$hasFile): ?>
  <p class="empty">No digital file is attached to this book yet. Ask an admin to upload one.</p>
<?php elseif ($ext === 'pdf'): ?>
  <iframe class="reader" title="<?= e($book['title']) ?>" src="<?= e(url('read.php?id=' . $id . '&raw=1')) ?>"></iframe>
<?php else: ?>
  <p class="lead">Browsers cannot open EPUB files directly. Download it and open it in an EPUB reader app.</p>
  <p><a class="btn" href="<?= e(url('read.php?id=' . $id . '&raw=1')) ?>">Download EPUB</a></p>
<?php endif;
page_footer();
