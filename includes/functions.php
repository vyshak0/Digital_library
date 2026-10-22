<?php
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Character count that works whether or not PHP's mbstring extension is enabled. */
function text_len(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : (int) preg_match_all('/./su', $s);
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

/** Only follow ?next= values that stay on this site. */
function safe_next(?string $next): ?string
{
    if ($next === null || $next === '') return null;
    if (!preg_match('#^/(?!/)[^\\\\\r\n]*$#', $next)) return null;
    if (BASE_URL !== '' && !str_starts_with($next, BASE_URL . '/')) return null;
    return $next;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function fail_page(int $code, string $title, string $message): never
{
    http_response_code($code);
    page_header($title);
    echo '<div class="narrow"><h1>' . e($title) . '</h1><p class="lead">' . e($message) . '</p>'
       . '<p><a class="btn" href="' . e(url('index.php')) . '">Back to the library</a></p></div>';
    page_footer();
    exit;
}

function fmt_date(?string $dt): string
{
    return $dt ? date('j M Y', strtotime($dt)) : '–';
}

function stars(float $avg, int $count = 0): string
{
    if ($count === 0) return '<span class="stars none">No ratings yet</span>';
    $r = (int) round($avg);
    return '<span class="stars" title="' . number_format($avg, 1) . ' out of 5" aria-label="Rated '
        . number_format($avg, 1) . ' out of 5"><span aria-hidden="true">' . str_repeat('★', $r) . str_repeat('☆', 5 - $r)
        . '</span> <span class="count">' . number_format($avg, 1) . ' (' . $count . ')</span></span>';
}

/** Stars for one person's rating (no average or count). */
function rating_stars(int $r): string
{
    return '<span class="stars" aria-label="Rated ' . $r . ' out of 5"><span aria-hidden="true">'
        . str_repeat('★', $r) . str_repeat('☆', 5 - $r) . '</span></span>';
}

function availability_badge(array $b): string
{
    $n = (int) $b['available_copies'];
    if ($n > 0) {
        return '<span class="badge ok">' . $n . ' of ' . (int) $b['total_copies'] . ' available</span>';
    }
    return '<span class="badge bad">All copies on loan</span>';
}

/** Typographic cover: colour comes from the book id, so it never changes. */
function cover(array $b, string $extra = ''): string
{
    $hue = ((int) $b['book_id'] * 47) % 360;
    return '<div class="cover ' . e($extra) . '" style="--h:' . $hue . '">'
        . '<span class="cover-cat">' . e($b['category'] ?: 'Book') . '</span>'
        . '<span class="cover-title">' . e($b['title']) . '</span>'
        . '<span class="cover-author">' . e($b['author']) . '</span></div>';
}

/** Books the signed-in user currently has on loan, keyed by book_id. */
function my_active_loans(?array $user): array
{
    if (!$user) return [];
    $rows = fetch_all("SELECT book_id, loan_id, due_date, due_date < NOW() AS is_overdue
                       FROM borrowings WHERE user_id = ? AND status = 'borrowed'", 'i', [$user['user_id']]);
    $map = [];
    foreach ($rows as $r) $map[(int) $r['book_id']] = $r;
    return $map;
}

function book_card(array $b, ?array $user, array $loans): string
{
    $id = (int) $b['book_id'];
    $link = e(url('book.php?id=' . $id));
    $html = '<article class="book-card"><a class="cover-link" href="' . $link . '">' . cover($b) . '</a>'
          . '<div class="book-meta">' . stars((float) $b['avg_rating'], (int) $b['review_count'])
          . availability_badge($b) . '</div><div class="book-actions">';
    if (isset($loans[$id])) {
        $html .= '<a class="btn small" href="' . e(url('read.php?id=' . $id)) . '">Read</a>';
    } elseif ((int) $b['available_copies'] > 0) {
        if ($user) {
            $html .= '<form method="post" action="' . e(url('borrow.php')) . '">' . csrf_field()
                  . '<input type="hidden" name="action" value="borrow"><input type="hidden" name="book_id" value="' . $id . '">'
                  . '<input type="hidden" name="back" value="' . e($_SERVER['REQUEST_URI'] ?? '') . '">'
                  . '<button class="btn small" type="submit">Borrow</button></form>';
        } else {
            $html .= '<a class="btn small" href="' . e(url('login.php') . '?next=' . urlencode(url('book.php?id=' . $id))) . '">Sign in to borrow</a>';
        }
    }
    return $html . '<a class="btn ghost small" href="' . $link . '">Details</a></div></article>';
}

/** SQL used wherever books are listed with their rating summary. */
const BOOK_LIST_SQL = "SELECT b.*, COALESCE(AVG(r.rating), 0) AS avg_rating, COUNT(r.review_id) AS review_count
                       FROM books b LEFT JOIN reviews r ON r.book_id = b.book_id";

// ---------- Uploads ----------
function store_upload(array $f): array
{
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $msg = in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'The file is larger than the server allows.' : 'The file could not be uploaded.';
        return [null, $msg];
    }
    if ($f['size'] > MAX_UPLOAD_MB * 1024 * 1024) {
        return [null, 'The file must be ' . MAX_UPLOAD_MB . ' MB or smaller.'];
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $allowed = ['pdf' => ['application/pdf'], 'epub' => ['application/epub+zip', 'application/zip']];
    if (!isset($allowed[$ext]) || !in_array($mime, $allowed[$ext], true)) {
        return [null, 'Upload a PDF or EPUB file.'];
    }
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $name)) {
        return [null, 'The file could not be saved on the server.'];
    }
    return [$name, null];
}

/** Delete a stored file, unless another book still points at it. */
function delete_book_file(?string $file, int $exceptBookId = 0): void
{
    if (!$file) return;
    $others = (int) fetch_value('SELECT COUNT(*) FROM books WHERE file_path = ? AND book_id <> ?', 'si', [$file, $exceptBookId]);
    $path = UPLOAD_DIR . '/' . basename($file);
    if ($others === 0 && is_file($path)) unlink($path);
}
