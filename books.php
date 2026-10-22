<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user  = current_user();
$loans = my_active_loans($user);

$term     = trim((string) ($_GET['q'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$sort     = (string) ($_GET['sort'] ?? 'title');
$onlyFree = !empty($_GET['available']);
$page     = max(1, (int) ($_GET['page'] ?? 1));

$where = []; $types = ''; $params = [];
if ($term !== '') {
    $like = '%' . addcslashes($term, '%_\\') . '%';
    $where[] = '(b.title LIKE ? OR b.author LIKE ? OR b.category LIKE ?)';
    $types .= 'sss'; array_push($params, $like, $like, $like);
}
if ($category !== '') { $where[] = 'b.category = ?'; $types .= 's'; $params[] = $category; }
if ($onlyFree)        { $where[] = 'b.available_copies > 0'; }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$order = ['title' => 'b.title ASC', 'newest' => 'b.created_at DESC, b.book_id DESC', 'rating' => 'avg_rating DESC, review_count DESC'][$sort] ?? 'b.title ASC';

$total = (int) fetch_value('SELECT COUNT(*) FROM books b' . $whereSql, $types, $params);
$pages = max(1, (int) ceil($total / BOOKS_PER_PAGE));
$page  = min($page, $pages);
$books = fetch_all(BOOK_LIST_SQL . $whereSql . ' GROUP BY b.book_id ORDER BY ' . $order . ' LIMIT ? OFFSET ?',
    $types . 'ii', array_merge($params, [BOOKS_PER_PAGE, ($page - 1) * BOOKS_PER_PAGE]));
$categories = fetch_all("SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category <> '' ORDER BY category");

page_header('Browse books', 'browse');
?>
<h1>Browse books</h1>
<form class="filters" method="get" role="search">
  <div class="field grow"><label for="q">Search</label>
    <input id="q" type="search" name="q" value="<?= e($term) ?>" placeholder="Title, author or category"></div>
  <div class="field"><label for="category">Category</label>
    <select id="category" name="category"><option value="">All categories</option>
      <?php foreach ($categories as $c): ?>
        <option<?= $c['category'] === $category ? ' selected' : '' ?>><?= e($c['category']) ?></option>
      <?php endforeach; ?></select></div>
  <div class="field"><label for="sort">Sort by</label>
    <select id="sort" name="sort">
      <option value="title"<?= $sort === 'title' ? ' selected' : '' ?>>Title</option>
      <option value="newest"<?= $sort === 'newest' ? ' selected' : '' ?>>Newest</option>
      <option value="rating"<?= $sort === 'rating' ? ' selected' : '' ?>>Highest rated</option></select></div>
  <label class="check"><input type="checkbox" name="available" value="1"<?= $onlyFree ? ' checked' : '' ?>> Available now</label>
  <button class="btn" type="submit">Apply</button>
</form>

<p class="result-count"><?= $total ?> <?= $total === 1 ? 'book' : 'books' ?> found<?= ($term !== '' || $category !== '' || $onlyFree) ? ' · <a href="' . e(url('books.php')) . '">Clear filters</a>' : '' ?></p>

<?php if (!$books): ?>
  <p class="empty">No books match your search. Try a shorter word or clear the filters.</p>
<?php else: ?>
  <div class="book-grid"><?php foreach ($books as $b) echo book_card($b, $user, $loans); ?></div>
  <?= pagination($page, $pages, array_filter(['q' => $term, 'category' => $category, 'sort' => $sort, 'available' => $onlyFree ? 1 : null], fn($v) => $v !== null && $v !== '')) ?>
<?php endif;
page_footer();
