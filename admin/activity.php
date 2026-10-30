<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$type = preg_replace('/[^a-z_]/', '', (string) ($_GET['type'] ?? ''));
$where = ['1=1'];
$params = [];
if ($q !== '') { $where[] = '(a.description LIKE ? OR u.email LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; array_push($params, $like, $like); }
if ($type !== '') { $where[] = 'a.action LIKE ?'; $params[] = $type . '.%'; }
$w = implode(' AND ', $where);
$total = (int) db_value("SELECT COUNT(*) FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id WHERE $w", $params);
$pg = paginate($total, 30);
$rows = db_all("SELECT a.*, u.first_name, u.last_name, u.email, u.role FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id
                 WHERE $w ORDER BY a.created_at DESC, a.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
$types = array_column(db_all("SELECT DISTINCT SUBSTRING_INDEX(action, '.', 1) AS t FROM activity_logs ORDER BY t"), 't');

$pageTitle = 'Activity log';
$layout = 'dashboard';
$dashActive = 'activity';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head"><div><h1>Activity log</h1><p>An audit trail of important actions across the platform.</p></div></div>
<form class="toolbar" method="get">
    <div class="input-icon"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search description or email" aria-label="Search activity"></div>
    <select class="select" name="type" data-autosubmit aria-label="Type"><option value="">All types</option><?php foreach ($types as $t): ?><option value="<?= e($t) ?>"<?= $type === $t ? ' selected' : '' ?>><?= e(ucfirst($t)) ?></option><?php endforeach; ?></select>
    <button class="btn btn-dark btn-sm">Filter</button>
</form>
<div class="table-wrap">
    <table class="table">
        <thead><tr><th>When</th><th>User</th><th>Action</th><th>Description</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $a): ?>
            <tr>
                <td class="nowrap small"><?= e(fmt_date($a['created_at'], 'M j, Y H:i')) ?><br><span class="muted"><?= e(time_ago($a['created_at'])) ?></span></td>
                <td class="small"><?= $a['first_name'] ? e($a['first_name'] . ' ' . $a['last_name']) . '<br><span class="muted">' . e($a['role']) . '</span>' : '<span class="muted">System</span>' ?></td>
                <td><code class="small"><?= e($a['action']) ?></code></td>
                <td><?= e($a['description']) ?></td>
                <td class="small muted"><?= e((string) $a['ip_address']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5"><div class="text-center muted" style="padding:30px">No activity found.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?= pagination_links($pg) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
