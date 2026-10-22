<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user  = current_user();
$loans = my_active_loans($user);
$featured = fetch_all(BOOK_LIST_SQL . ' GROUP BY b.book_id ORDER BY review_count DESC, avg_rating DESC, b.created_at DESC LIMIT 6');
$cats = fetch_all("SELECT category, COUNT(*) AS n FROM books WHERE category IS NOT NULL AND category <> '' GROUP BY category ORDER BY n DESC, category LIMIT 8");

page_header('Home');
?>
<section class="hero">
  <h1>Find your next read, borrow it in one click</h1>
  <p class="lead">Search the catalogue, borrow for <?= LOAN_DAYS ?> days, read in your browser and tell others what you thought.</p>
  <form class="search" method="get" action="<?= e(url('books.php')) ?>" role="search">
    <label class="sr" for="q">Search books</label>
    <input id="q" type="search" name="q" placeholder="Search by title, author or category">
    <button class="btn" type="submit">Search</button>
  </form>
  <?php if ($cats): ?>
  <p class="chips"><?php foreach ($cats as $c): ?>
    <a href="<?= e(url('books.php') . '?category=' . urlencode($c['category'])) ?>"><?= e($c['category']) ?></a>
  <?php endforeach; ?></p>
  <?php endif; ?>
</section>

<section>
  <div class="section-head"><h2>Featured books</h2><a href="<?= e(url('books.php')) ?>">See the full catalogue</a></div>
  <?php if (!$featured): ?>
    <p class="empty">The catalogue is empty. An admin can add the first book from the admin panel.</p>
  <?php else: ?>
    <div class="book-grid"><?php foreach ($featured as $b) echo book_card($b, $user, $loans); ?></div>
  <?php endif; ?>
</section>
<?php page_footer();
