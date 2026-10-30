<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_role('admin');

if (is_post()) {
    csrf_verify();
    $target = db_one('SELECT * FROM users WHERE id = ?', [(int) ($_POST['user_id'] ?? 0)]);
    $action = $_POST['action'] ?? '';
    if (!$target) {
        flash('error', 'User not found.');
    } elseif ((int) $target['id'] === (int) $admin['id']) {
        flash('error', 'You cannot change your own account here.');
    } elseif ($action === 'suspend') {
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 255) ?: 'Violation of the terms of service';
        db_exec("UPDATE users SET status = 'suspended', suspended_reason = ? WHERE id = ?", [$reason, $target['id']]);
        log_activity('user.suspended', 'Suspended user ' . $target['first_name'] . ' ' . $target['last_name'], 'user', (int) $target['id']);
        flash('success', $target['first_name'] . ' has been suspended and will be logged out.');
    } elseif ($action === 'activate') {
        db_exec("UPDATE users SET status = 'active', suspended_reason = NULL WHERE id = ?", [$target['id']]);
        notify((int) $target['id'], 'account', 'Account reactivated', 'Your LemboEvents account is active again. Welcome back!', null);
        log_activity('user.activated', 'Reactivated user ' . $target['first_name'] . ' ' . $target['last_name'], 'user', (int) $target['id']);
        flash('success', $target['first_name'] . ' has been reactivated.');
    } elseif ($action === 'make_admin' && $target['role'] === 'attendee') {
        db_exec("UPDATE users SET role = 'admin' WHERE id = ?", [$target['id']]);
        log_activity('user.promoted', 'Promoted ' . $target['email'] . ' to admin', 'user', (int) $target['id']);
        flash('success', $target['first_name'] . ' is now an administrator.');
    }
    redirect('admin/users.php?' . http_build_query(array_intersect_key($_GET, array_flip(['q', 'role', 'status', 'page']))));
}

$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$role = in_array($_GET['role'] ?? '', ['attendee', 'organizer', 'admin'], true) ? $_GET['role'] : '';
$status = in_array($_GET['status'] ?? '', ['active', 'suspended'], true) ? $_GET['status'] : '';
$where = ['1=1'];
$params = [];
if ($q !== '') { $where[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ? OR u.city LIKE ?)"; $like = '%' . addcslashes($q, '%_\\') . '%'; array_push($params, $like, $like, $like); }
if ($role) { $where[] = 'u.role = ?'; $params[] = $role; }
if ($status) { $where[] = 'u.status = ?'; $params[] = $status; }
$w = implode(' AND ', $where);
$total = (int) db_value("SELECT COUNT(*) FROM users u WHERE $w", $params);
$pg = paginate($total, 20);
$users = db_all(
    "SELECT u.*, (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id) AS bookings,
            (SELECT COALESCE(SUM(total), 0) FROM bookings b WHERE b.user_id = u.id AND b.status = 'confirmed') AS spent,
            o.organization_name
       FROM users u LEFT JOIN organizer_profiles o ON o.user_id = u.id
      WHERE $w ORDER BY u.created_at DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
$counts = db_one("SELECT COUNT(*) AS all_users, SUM(role='attendee') AS attendees, SUM(role='organizer') AS organizers, SUM(role='admin') AS admins, SUM(status='suspended') AS suspended FROM users");

$pageTitle = 'Users';
$layout = 'dashboard';
$dashActive = 'users';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head"><div><h1>Users</h1><p><?= number_format((int) $counts['all_users']) ?> accounts · <?= (int) $counts['attendees'] ?> attendees · <?= (int) $counts['organizers'] ?> organizers · <?= (int) $counts['admins'] ?> admins · <?= (int) $counts['suspended'] ?> suspended</p></div></div>

<form class="toolbar" method="get">
    <div class="input-icon"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, email or city" aria-label="Search users"></div>
    <select class="select" name="role" data-autosubmit aria-label="Role"><option value="">All roles</option><?php foreach (['attendee', 'organizer', 'admin'] as $r): ?><option value="<?= $r ?>"<?= $role === $r ? ' selected' : '' ?>><?= ucfirst($r) ?>s</option><?php endforeach; ?></select>
    <select class="select" name="status" data-autosubmit aria-label="Status"><option value="">Any status</option><option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Active</option><option value="suspended"<?= $status === 'suspended' ? ' selected' : '' ?>>Suspended</option></select>
    <button class="btn btn-dark btn-sm">Search</button>
</form>

<div class="table-wrap">
    <table class="table">
        <thead><tr><th>User</th><th>Role</th><th>City</th><th class="num">Bookings</th><th>Joined</th><th>Status</th><th class="num">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><div class="cell-main"><span class="avatar-initials" style="width:36px;height:36px"><?= e(initials($u['first_name'] . ' ' . $u['last_name'])) ?></span><div><strong><?= e($u['first_name'] . ' ' . $u['last_name']) ?></strong><span><?= e($u['email']) ?></span></div></div></td>
                <td><span class="role-pill role-<?= e($u['role']) ?>"><?= e(ucfirst($u['role'])) ?></span><?php if ($u['organization_name']): ?><br><span class="small muted"><?= e($u['organization_name']) ?></span><?php endif; ?></td>
                <td><?= e((string) $u['city']) ?></td>
                <td class="num"><?= (int) $u['bookings'] ?><br><span class="small muted"><?= e(money_exact($u['spent'])) ?></span></td>
                <td class="nowrap small"><?= e(fmt_date($u['created_at'])) ?><br><span class="muted">Last login <?= e($u['last_login_at'] ? time_ago($u['last_login_at']) : 'never') ?></span></td>
                <td><?= status_badge($u['status']) ?><?php if ($u['suspended_reason']): ?><br><span class="small muted"><?= e(str_limit($u['suspended_reason'], 40)) ?></span><?php endif; ?></td>
                <td>
                    <div class="actions">
                        <?php if ((int) $u['id'] === (int) $admin['id']): ?>
                            <span class="small muted">You</span>
                        <?php elseif ($u['status'] === 'active'): ?>
                            <form method="post" class="inline-form" data-confirm="Suspend <?= e($u['first_name']) ?>? They will be logged out and unable to sign in." data-confirm-title="Suspend account?" data-confirm-button="Suspend">
                                <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                <button class="btn btn-danger-soft btn-xs" name="action" value="suspend">Suspend</button>
                            </form>
                        <?php else: ?>
                            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><button class="btn btn-success btn-xs" name="action" value="activate">Reactivate</button></form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$users): ?><tr><td colspan="7"><div class="text-center muted" style="padding:30px">No users match your filters.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?= pagination_links($pg) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
