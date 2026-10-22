<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
require_post();

$back = safe_next($_POST['back'] ?? null) ?? url('my_borrowings.php');
$action = $_POST['action'] ?? '';

try {
    if ($action === 'borrow') {
        $title = borrow_book((int) $user['user_id'], (int) ($_POST['book_id'] ?? 0));
        flash('success', '“' . $title . '” is yours until ' . date('j M Y', strtotime('+' . LOAN_DAYS . ' days')) . '. You can read it now.');
    } elseif ($action === 'return') {
        $title = return_loan((int) ($_POST['loan_id'] ?? 0), (int) $user['user_id']);
        flash('success', 'You returned “' . $title . '”.');
    } else {
        flash('error', 'Unknown action.');
    }
} catch (UserError $e) {
    flash('error', $e->getMessage());
} catch (Throwable $e) {
    error_log($e);
    flash('error', 'Something went wrong. Please try again.');
}
redirect($back);
