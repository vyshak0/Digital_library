<?php
require_once __DIR__ . '/includes/bootstrap.php';

$id   = (int) ($_GET['id'] ?? 0);
$book = fetch_one(BOOK_LIST_SQL . ' WHERE b.book_id = ? GROUP BY b.book_id', 'i', [$id]);
if (!$book) fail_page(404, 'Book not found', 'This book is not in the catalogue.');

$user    = current_user();
$loan    = $user ? (my_active_loans($user)[$id] ?? null) : null;
$everHad = $user ? (bool) fetch_value('SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND book_id = ?', 'ii', [$user['user_id'], $id]) : false;
$mine    = $user ? fetch_one('SELECT rating, comment FROM reviews WHERE user_id = ? AND book_id = ?', 'ii', [$user['user_id'], $id]) : null;
$reviews = fetch_all('SELECT r.review_id, r.user_id, r.rating, r.comment, r.created_at, u.name
                      FROM reviews r JOIN users u ON u.user_id = r.user_id
                      WHERE r.book_id = ? ORDER BY r.created_at DESC', 'i', [$id]);

page_header($book['title'], 'browse');
?>
<p class="crumb"><a href="<?= e(url('books.php')) ?>">Browse books</a></p>
<div class="detail">
  <?= cover($book, 'large') ?>
  <div class="detail-body">
    <h1><?= e($book['title']) ?></h1>
    <p class="byline">by <?= e($book['author']) ?><?= $book['category'] ? ' · ' . e($book['category']) : '' ?></p>
    <p><?= stars((float) $book['avg_rating'], (int) $book['review_count']) ?> <?= availability_badge($book) ?></p>
    <?php if ($book['description']): ?><p class="desc"><?= nl2br(e($book['description'])) ?></p><?php endif; ?>

    <div class="detail-actions">
    <?php if ($loan): ?>
      <a class="btn" href="<?= e(url('read.php?id=' . $id)) ?>">Read now</a>
      <form method="post" action="<?= e(url('borrow.php')) ?>"><?= csrf_field() ?>
        <input type="hidden" name="action" value="return"><input type="hidden" name="loan_id" value="<?= (int) $loan['loan_id'] ?>">
        <input type="hidden" name="back" value="<?= e(url('book.php?id=' . $id)) ?>">
        <button class="btn ghost" type="submit">Return book</button></form>
      <p class="note<?= $loan['is_overdue'] ? ' bad' : '' ?>"><?= $loan['is_overdue'] ? 'Overdue since ' : 'Due ' ?><?= e(fmt_date($loan['due_date'])) ?>.<?= $loan['is_overdue'] ? ' Return it to keep borrowing.' : '' ?></p>
    <?php elseif (!$user): ?>
      <a class="btn" href="<?= e(url('login.php') . '?next=' . urlencode(url('book.php?id=' . $id))) ?>">Sign in to borrow</a>
    <?php elseif ((int) $book['available_copies'] > 0): ?>
      <form method="post" action="<?= e(url('borrow.php')) ?>"><?= csrf_field() ?>
        <input type="hidden" name="action" value="borrow"><input type="hidden" name="book_id" value="<?= $id ?>">
        <input type="hidden" name="back" value="<?= e(url('book.php?id=' . $id)) ?>">
        <button class="btn" type="submit">Borrow for <?= LOAN_DAYS ?> days</button></form>
    <?php else: ?>
      <button class="btn" type="button" disabled>All copies on loan</button>
    <?php endif; ?>
    </div>
  </div>
</div>

<section class="reviews">
  <h2>Reviews</h2>
  <?php if ($user && $everHad): ?>
    <form class="review-form" method="post" action="<?= e(url('review.php')) ?>"><?= csrf_field() ?>
      <input type="hidden" name="action" value="save"><input type="hidden" name="book_id" value="<?= $id ?>">
      <fieldset class="rating-input"><legend><?= $mine ? 'Update your review' : 'Rate this book' ?></legend>
        <div class="stars-row">
        <?php for ($i = 5; $i >= 1; $i--): ?>
          <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>"<?= ($mine['rating'] ?? 0) == $i ? ' checked' : '' ?> required>
          <label for="star<?= $i ?>" title="<?= $i ?> out of 5"><span class="sr"><?= $i ?> star<?= $i > 1 ? 's' : '' ?></span>★</label>
        <?php endfor; ?>
        </div>
      </fieldset>
      <div class="field"><label for="comment">Your thoughts (optional)</label>
        <textarea id="comment" name="comment" rows="3" maxlength="1000"><?= e($mine['comment'] ?? '') ?></textarea></div>
      <button class="btn" type="submit"><?= $mine ? 'Update review' : 'Post review' ?></button>
    </form>
  <?php elseif ($user): ?>
    <p class="note">Borrow this book to leave a review.</p>
  <?php else: ?>
    <p class="note"><a href="<?= e(url('login.php') . '?next=' . urlencode(url('book.php?id=' . $id))) ?>">Sign in</a> and borrow this book to leave a review.</p>
  <?php endif; ?>

  <?php if (!$reviews): ?>
    <p class="empty">No reviews yet. Be the first to share what you thought.</p>
  <?php endif; ?>
  <?php foreach ($reviews as $r): ?>
    <article class="review">
      <header><strong><?= e($r['name']) ?></strong> <?= rating_stars((int) $r['rating']) ?> <time><?= e(fmt_date($r['created_at'])) ?></time></header>
      <?php if ($r['comment'] !== null && $r['comment'] !== ''): ?><p><?= nl2br(e($r['comment'])) ?></p><?php endif; ?>
      <?php if ($user && (int) $r['user_id'] === (int) $user['user_id']): ?>
        <form method="post" action="<?= e(url('review.php')) ?>" data-confirm="Delete your review?"><?= csrf_field() ?>
          <input type="hidden" name="action" value="delete"><input type="hidden" name="book_id" value="<?= $id ?>">
          <button class="link-btn danger" type="submit">Delete my review</button></form>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</section>
<?php page_footer();
