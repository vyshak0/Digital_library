<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
require_post();

$bookId = (int) ($_POST['book_id'] ?? 0);
$back   = url('book.php?id=' . $bookId);

try {
    if (($_POST['action'] ?? '') === 'delete') {
        exec_sql('DELETE FROM reviews WHERE user_id = ? AND book_id = ?', 'ii', [$user['user_id'], $bookId]);
        flash('success', 'Your review was deleted.');
    } else {
        $rating  = (int) ($_POST['rating'] ?? 0);
        $comment = trim((string) ($_POST['comment'] ?? ''));
        if ($rating < 1 || $rating > 5) throw new UserError('Choose a rating from 1 to 5 stars.');
        if (text_len($comment) > 1000) throw new UserError('Keep your review under 1,000 characters.');
        if (!fetch_value('SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND book_id = ?', 'ii', [$user['user_id'], $bookId])) {
            throw new UserError('Borrow this book before reviewing it.');
        }
        exec_sql('INSERT INTO reviews (user_id, book_id, rating, comment) VALUES (?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)',
                 'iiis', [$user['user_id'], $bookId, $rating, $comment === '' ? null : $comment]);
        flash('success', 'Thanks, your review is posted.');
    }
} catch (UserError $e) {
    flash('error', $e->getMessage());
} catch (Throwable $e) {
    error_log($e);
    flash('error', 'Something went wrong. Please try again.');
}
redirect($back);
