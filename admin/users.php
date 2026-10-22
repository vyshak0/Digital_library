<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $id = (int) ($_POST['user_id'] ?? 0);
    $target = fetch_one('SELECT user_id, name, role FROM users WHERE user_id = ?', 'i', [$id]);
    if (!$target) {
        flash('error', 'That user no longer exists.');
    } elseif ($id === (int) $me['user_id']) {
        flash('error', 'You cannot change or delete your own account here.');
    } elseif (($_POST['action'] ?? '') === 'delete') {
        if ((int) fetch_value("SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND status = 'borrowed'", 'i', [$id]) > 0) {
            flash('error', $target['name'] . ' still has books on loan. Return them from Borrowings first.');
        } else {
            exec_sql('DELETE FROM users WHERE user_id = ?', 'i', [$id]);
            flash('success', 'Deleted ' . $target['name'] . '.');
        }
    } elseif (($_POST['action'] ?? '') === 'role') {
        $role = $target['role'] === 'admin' ? 'user' : 'admin';
        exec_sql('UPDATE users SET role = ? WHERE user_id = ?', 'si', [$role, $id]);
        flash('success', $target['name'] . ' is now ' . ($role === 'admin' ? 'an admin.' : 'a regular reader.'));
    }
    redirect(url('admin/users.php'));
}

$users = fetch_all("SELECT u.user_id, u.name, u.email, u.role, u.created_at,
                           (SELECT COUNT(*) FROM borrowings l WHERE l.user_id = u.user_id AND l.status = 'borrowed') AS on_loan,
                           (SELECT COUNT(*) FROM borrowings l WHERE l.user_id = u.user_id) AS total_loans
                    FROM users u ORDER BY u.role = 'admin' DESC, u.name");

admin_header('Manage users', 'users');
?>
<div class="table-wrap"><table>
  <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>On loan</th><th>Joined</th><th><span class="sr">Actions</span></th></tr></thead>
  <tbody><?php foreach ($users as $u): $self = (int) $u['user_id'] === (int) $me['user_id']; ?>
    <tr>
      <td><strong><?= e($u['name']) ?></strong><?= $self ? ' <span class="muted">(you)</span>' : '' ?></td>
      <td><?= e($u['email']) ?></td>
      <td><span class="badge <?= $u['role'] === 'admin' ? 'warn' : '' ?>"><?= $u['role'] === 'admin' ? 'Admin' : 'Reader' ?></span></td>
      <td><?= (int) $u['on_loan'] ?> <span class="muted">of <?= (int) $u['total_loans'] ?> ever</span></td>
      <td><?= e(fmt_date($u['created_at'])) ?></td>
      <td class="actions"><?php if (!$self): ?>
        <form method="post" data-confirm="<?= $u['role'] === 'admin' ? 'Remove admin access for ' : 'Give admin access to ' ?><?= e($u['name']) ?>?"><?= csrf_field() ?>
          <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>"><input type="hidden" name="action" value="role">
          <button class="btn ghost small" type="submit"><?= $u['role'] === 'admin' ? 'Make reader' : 'Make admin' ?></button></form>
        <form method="post" data-confirm="Delete <?= e($u['name']) ?>? Their loan history and reviews are removed too."><?= csrf_field() ?>
          <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>"><input type="hidden" name="action" value="delete">
          <button class="btn danger small" type="submit">Delete</button></form>
      <?php endif; ?></td>
    </tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php admin_footer();
