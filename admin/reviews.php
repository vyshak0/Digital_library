<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    exec_sql('DELETE FROM reviews WHERE review_id = ?', 'i', [(int) ($_POST['review_id'] ?? 0)]);
    flash('success', 'Review deleted.');
    redirect(url('admin/reviews.php'));
}

$rows = fetch_all('SELECT r.review_id, r.rating, r.comment, r.created_at, u.name, b.title
                   FROM reviews r JOIN users u ON u.user_id = r.user_id JOIN books b ON b.book_id = r.book_id
                   ORDER BY r.created_at DESC LIMIT 200');

admin_header('Reviews', 'reviews');
?>
<?php if (!$rows): ?><p class="empty">No reviews have been posted yet.</p><?php else: ?>
<div class="table-wrap"><table>
  <thead><tr><th>Book</th><th>Reader</th><th>Rating</th><th>Comment</th><th>Posted</th><th><span class="sr">Actions</span></th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['title']) ?></td><td><?= e($r['name']) ?></td>
      <td><?= rating_stars((int) $r['rating']) ?></td>
      <td class="wrap"><?= e($r['comment'] ?? '') ?: '<span class="muted">No comment</span>' ?></td>
      <td><?= e(fmt_date($r['created_at'])) ?></td>
      <td class="actions"><form method="post" data-confirm="Delete this review by <?= e($r['name']) ?>?"><?= csrf_field() ?>
        <input type="hidden" name="review_id" value="<?= (int) $r['review_id'] ?>">
        <button class="btn danger small" type="submit">Delete</button></form></td>
    </tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php endif;
admin_footer();
