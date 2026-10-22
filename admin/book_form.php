<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$id   = (int) ($_GET['id'] ?? 0);
$book = $id ? fetch_one('SELECT * FROM books WHERE book_id = ?', 'i', [$id]) : null;
if ($id && !$book) {
    flash('error', 'That book no longer exists.');
    redirect(url('admin/books.php'));
}
$v = $book ?? ['title' => '', 'author' => '', 'category' => '', 'description' => '', 'total_copies' => 1, 'file_path' => null];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $v['title']       = trim((string) ($_POST['title'] ?? ''));
    $v['author']      = trim((string) ($_POST['author'] ?? ''));
    $v['category']    = trim((string) ($_POST['category'] ?? ''));
    $v['description'] = trim((string) ($_POST['description'] ?? ''));
    $v['total_copies'] = filter_var($_POST['total_copies'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 999]]);

    if ($v['title'] === '' || text_len($v['title']) > 150)   $errors['title'] = 'Enter a title (up to 150 characters).';
    if ($v['author'] === '' || text_len($v['author']) > 100) $errors['author'] = 'Enter an author (up to 100 characters).';
    if (text_len($v['category']) > 60)                       $errors['category'] = 'Keep the category under 60 characters.';
    if (text_len($v['description']) > 2000)                  $errors['description'] = 'Keep the description under 2,000 characters.';
    if ($v['total_copies'] === false)                         $errors['total_copies'] = 'Enter a whole number from 0 to 999.';

    $onLoan = $id ? (int) fetch_value("SELECT COUNT(*) FROM borrowings WHERE book_id = ? AND status = 'borrowed'", 'i', [$id]) : 0;
    if ($v['total_copies'] !== false && $v['total_copies'] < $onLoan) {
        $errors['total_copies'] = $onLoan . ' ' . ($onLoan === 1 ? 'copy is' : 'copies are') . ' on loan, so the total cannot be lower than ' . $onLoan . '.';
    }

    $newFile = null;
    $hasUpload = isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE;
    if ($hasUpload && !$errors) {
        [$newFile, $err] = store_upload($_FILES['file']);
        if ($err) $errors['file'] = $err;
    }

    if (!$errors) {
        $old = $book['file_path'] ?? null;
        $file = $old;
        if ($newFile) $file = $newFile;
        elseif (!empty($_POST['remove_file'])) $file = null;
        $cat = $v['category'] === '' ? null : $v['category'];
        $desc = $v['description'] === '' ? null : $v['description'];

        if ($id) {
            exec_sql('UPDATE books SET title = ?, author = ?, category = ?, description = ?, file_path = ?,
                      total_copies = ?, available_copies = ? - ? WHERE book_id = ?',
                'sssssiiii', [$v['title'], $v['author'], $cat, $desc, $file, $v['total_copies'], $v['total_copies'], $onLoan, $id]);
            if ($old && $old !== $file) delete_book_file($old, $id);
            flash('success', 'Saved changes to “' . $v['title'] . '”.');
        } else {
            exec_sql('INSERT INTO books (title, author, category, description, file_path, total_copies, available_copies)
                      VALUES (?, ?, ?, ?, ?, ?, ?)',
                'sssssii', [$v['title'], $v['author'], $cat, $desc, $file, $v['total_copies'], $v['total_copies']]);
            flash('success', 'Added “' . $v['title'] . '” to the catalogue.');
        }
        redirect(url('admin/books.php'));
    }
    $v['file_path'] = $book['file_path'] ?? null;
}

admin_header($id ? 'Edit book' : 'Add a book', 'books');

$input = function (string $name, string $label, string $value, string $type = 'text', string $extra = '') use ($errors) {
    echo '<div class="field' . (isset($errors[$name]) ? ' has-error' : '') . '"><label for="' . $name . '">' . $label . '</label>'
       . '<input id="' . $name . '" name="' . $name . '" type="' . $type . '" value="' . e($value) . '" ' . $extra
       . (isset($errors[$name]) ? ' aria-invalid="true" aria-describedby="' . $name . '-err"' : '') . '>'
       . (isset($errors[$name]) ? '<p class="error" id="' . $name . '-err">' . e($errors[$name]) . '</p>' : '') . '</div>';
};
?>
<form class="stack wide" method="post" enctype="multipart/form-data" novalidate><?= csrf_field() ?>
  <?php
  $input('title', 'Title', (string) $v['title'], 'text', 'required maxlength="150"');
  $input('author', 'Author', (string) $v['author'], 'text', 'required maxlength="100"');
  $input('category', 'Category', (string) $v['category'], 'text', 'maxlength="60" list="cats"');
  ?>
  <datalist id="cats"><?php foreach (fetch_all("SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category <> '' ORDER BY category") as $c): ?><option value="<?= e($c['category']) ?>"><?php endforeach; ?></datalist>
  <div class="field<?= isset($errors['description']) ? ' has-error' : '' ?>"><label for="description">Description</label>
    <textarea id="description" name="description" rows="4" maxlength="2000"><?= e($v['description']) ?></textarea>
    <?php if (isset($errors['description'])): ?><p class="error"><?= e($errors['description']) ?></p><?php endif; ?></div>
  <?php $input('total_copies', 'Copies the library owns', (string) $v['total_copies'], 'number', 'min="0" max="999" required'); ?>
  <div class="field<?= isset($errors['file']) ? ' has-error' : '' ?>"><label for="file">Digital file (PDF or EPUB, up to <?= MAX_UPLOAD_MB ?> MB)</label>
    <input id="file" name="file" type="file" accept=".pdf,.epub,application/pdf,application/epub+zip">
    <?php if (isset($errors['file'])): ?><p class="error"><?= e($errors['file']) ?></p><?php endif; ?>
    <?php if ($v['file_path']): ?>
      <p class="note">Current file: <?= e($v['file_path']) ?> · Uploading a new file replaces it.
        <label class="check"><input type="checkbox" name="remove_file" value="1"> Remove the file</label></p>
    <?php endif; ?></div>
  <div class="row"><button class="btn" type="submit"><?= $id ? 'Save changes' : 'Add book' ?></button>
    <a class="btn ghost" href="<?= e(url('admin/books.php')) ?>">Cancel</a></div>
</form>
<?php admin_footer();
