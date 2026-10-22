<?php
require_once __DIR__ . '/includes/bootstrap.php';

$next = safe_next($_GET['next'] ?? $_POST['next'] ?? null);
if (current_user()) redirect($next ?? url('books.php'));

$error = ''; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $row = fetch_one('SELECT user_id, name, password_hash, role FROM users WHERE email = ?', 's', [$email]);
    // Verify against a dummy hash when the email is unknown, so timing does not reveal which emails exist.
    $hash = $row['password_hash'] ?? password_hash('not-a-real-password', PASSWORD_DEFAULT);
    if (password_verify((string) ($_POST['password'] ?? ''), $hash) && $row) {
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            exec_sql('UPDATE users SET password_hash = ? WHERE user_id = ?', 'si', [password_hash((string) $_POST['password'], PASSWORD_DEFAULT), $row['user_id']]);
        }
        login_user((int) $row['user_id']);
        flash('success', 'Welcome back, ' . $row['name'] . '.');
        redirect($next ?? ($row['role'] === 'admin' ? url('admin/index.php') : url('books.php')));
    }
    $error = 'The email or password is not correct.';
}

page_header('Sign in');
?>
<div class="narrow">
  <h1>Sign in</h1>
  <?php if ($error): ?><div class="flash error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form class="stack" method="post"><?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next ?? '') ?>">
    <div class="field"><label for="email">Email</label>
      <input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="email" required autofocus></div>
    <div class="field"><label for="password">Password</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required></div>
    <button class="btn" type="submit">Sign in</button>
  </form>
  <p class="note">New here? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
</div>
<?php page_footer();
