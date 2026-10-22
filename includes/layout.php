<?php
function page_header(string $title, string $active = ''): void
{
    $user = current_user();
    $nav = [
        'browse'    => ['books.php', 'Browse'],
        'borrowing' => ['my_borrowings.php', 'My borrowings'],
    ];
    if ($user && $user['role'] === 'admin') {
        $nav['admin'] = ['admin/index.php', 'Admin panel'];
    }
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · Digital Library</title>
<link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="site-header">
  <div class="bar">
    <a class="brand" href="<?= e(url('index.php')) ?>">
      <svg width="26" height="26" viewBox="0 0 26 26" aria-hidden="true"><rect x="2" y="6" width="6" height="18" rx="1.2" fill="#9ec5f2"/><rect x="10" y="2" width="6" height="22" rx="1.2" fill="#fff"/><rect x="18" y="8" width="6" height="16" rx="1.2" fill="#5b9be0"/></svg>
      Digital Library
    </a>
    <nav aria-label="Main">
      <?php foreach ($nav as $key => [$href, $label]): ?>
        <a href="<?= e(url($href)) ?>"<?= $key === $active ? ' class="current" aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="account">
      <?php if ($user): ?>
        <span class="who"><?= e($user['name']) ?></span>
        <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button class="link-btn" type="submit">Sign out</button></form>
      <?php else: ?>
        <a href="<?= e(url('login.php')) ?>">Sign in</a>
        <a class="pill" href="<?= e(url('register.php')) ?>">Create account</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<main id="main" class="container">
<?php foreach (take_flashes() as $f): ?>
  <div class="flash <?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($f['message']) ?></div>
<?php endforeach;
}

function page_footer(): void
{
    ?>
</main>
<footer class="site-footer">
  <div class="container">Digital Library · Browse, borrow and review books online.</div>
</footer>
<script src="<?= e(url('assets/app.js')) ?>"></script>
</body>
</html>
<?php
}

function admin_header(string $title, string $active): void
{
    $links = [
        'dashboard'  => ['admin/index.php', 'Dashboard'],
        'books'      => ['admin/books.php', 'Manage books'],
        'users'      => ['admin/users.php', 'Manage users'],
        'borrowings' => ['admin/borrowings.php', 'Borrowings'],
        'reviews'    => ['admin/reviews.php', 'Reviews'],
    ];
    page_header($title, 'admin');
    echo '<div class="admin"><aside class="admin-side"><nav aria-label="Admin">';
    foreach ($links as $key => [$href, $label]) {
        echo '<a href="' . e(url($href)) . '"' . ($key === $active ? ' class="current" aria-current="page"' : '') . '>' . e($label) . '</a>';
    }
    echo '</nav></aside><section class="admin-main"><h1>' . e($title) . '</h1>';
}

function admin_footer(): void
{
    echo '</section></div>';
    page_footer();
}

function pagination(int $page, int $pages, array $query): string
{
    if ($pages <= 1) return '';
    $html = '<nav class="pagination" aria-label="Pages">';
    for ($i = 1; $i <= $pages; $i++) {
        $qs = http_build_query(array_merge($query, ['page' => $i]));
        $html .= '<a href="?' . e($qs) . '"' . ($i === $page ? ' class="current" aria-current="page"' : '') . '>' . $i . '</a>';
    }
    return $html . '</nav>';
}
