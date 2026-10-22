<?php
/** Raised for problems the reader should see (not for bugs). */
class UserError extends Exception {}

function borrow_book(int $userId, int $bookId): string
{
    $db = db();
    $db->begin_transaction();
    try {
        // Lock the book row so two people cannot take the last copy at once.
        $book = fetch_one('SELECT title, available_copies FROM books WHERE book_id = ? FOR UPDATE', 'i', [$bookId]);
        if (!$book) throw new UserError('That book is no longer in the catalogue.');

        if (fetch_value("SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND book_id = ? AND status = 'borrowed'", 'ii', [$userId, $bookId])) {
            throw new UserError('You already have this book on loan.');
        }
        $active = (int) fetch_value("SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND status = 'borrowed'", 'i', [$userId]);
        if ($active >= MAX_ACTIVE_LOANS) {
            throw new UserError('You can hold ' . MAX_ACTIVE_LOANS . ' books at a time. Return one to borrow another.');
        }
        if ((int) $book['available_copies'] < 1) {
            throw new UserError('All copies of this book are on loan right now.');
        }

        exec_sql("INSERT INTO borrowings (user_id, book_id, borrow_date, due_date, status)
                  VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), 'borrowed')", 'iii', [$userId, $bookId, LOAN_DAYS]);
        exec_sql('UPDATE books SET available_copies = available_copies - 1 WHERE book_id = ?', 'i', [$bookId]);
        $db->commit();
        return $book['title'];
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

/** $onlyUserId restricts the return to that reader's own loan (admins pass null). */
function return_loan(int $loanId, ?int $onlyUserId = null): string
{
    $db = db();
    $db->begin_transaction();
    try {
        $loan = fetch_one("SELECT l.loan_id, l.book_id, l.user_id, b.title
                           FROM borrowings l JOIN books b ON b.book_id = l.book_id
                           WHERE l.loan_id = ? AND l.status = 'borrowed' FOR UPDATE", 'i', [$loanId]);
        if (!$loan || ($onlyUserId !== null && (int) $loan['user_id'] !== $onlyUserId)) {
            throw new UserError('That loan is not active.');
        }
        exec_sql("UPDATE borrowings SET status = 'returned', return_date = NOW() WHERE loan_id = ?", 'i', [$loanId]);
        exec_sql('UPDATE books SET available_copies = LEAST(total_copies, available_copies + 1) WHERE book_id = ?', 'i', [$loan['book_id']]);
        $db->commit();
        return $loan['title'];
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
