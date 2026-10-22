<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) redirect(url('books.php'));

$errors = []; $name = ''; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $name  = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $pass  = (string) ($_POST['password'] ?? '');

    if (text_len($name) < 2 || text_len($name) > 80)         $errors['name'] = 'Enter your name (2 to 80 characters).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) $errors['email'] = 'Enter a valid email address.';
    if (strlen($pass) < 8)                                     $errors['password'] = 'Use at least 8 characters.';
    elseif ($pass !== (string) ($_POST['confirm'] ?? ''))      $errors['confirm'] = 'The passwords do not match.';

    if (!$errors) {
        try {
            exec_sql("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'user')",
                     'sss', [$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            login_user(last_id());
            flash('success', 'Welcome, ' . $name . '! Your account is ready.');
            redirect(url('books.php'));
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) $errors['email'] = 'An account with this email already exists.';
            else { error_log($e); $errors['form'] = 'Something went wrong. Please try again.'; }
        }
    }
}

page_header('Create account');
?>
<div class="narrow">
  <h1>Create your account</h1>
  <?php if (isset($errors['form'])): ?><div class="flash error" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>
  <form class="stack" method="post" novalidate><?= csrf_field() ?>
    <?php
    $field = function (string $id, string $label, string $type, string $value = '', string $auto = '') use ($errors) {
        echo '<div class="field' . (isset($errors[$id]) ? ' has-error' : '') . '"><label for="' . $id . '">' . $label . '</label>'
           . '<input id="' . $id . '" name="' . $id . '" type="' . $type . '" value="' . e($value) . '" autocomplete="' . $auto . '" required'
           . (isset($errors[$id]) ? ' aria-invalid="true" aria-describedby="' . $id . '-err"' : '') . '>'
           . (isset($errors[$id]) ? '<p class="error" id="' . $id . '-err">' . e($errors[$id]) . '</p>' : '') . '</div>';
    };
    $field('name', 'Full name', 'text', $name, 'name');
    $field('email', 'Email', 'email', $email, 'email');
    $field('password', 'Password (8+ characters)', 'password', '', 'new-password');
    $field('confirm', 'Confirm password', 'password', '', 'new-password');
    ?>
    <button class="btn" type="submit">Create account</button>
  </form>
  <p class="note">Already registered? <a href="<?= e(url('login.php')) ?>">Sign in</a></p>
</div>
<?php page_footer();
