<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['category_id'] ?? 0);
    if ($action === 'save') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $desc = trim((string) ($_POST['description'] ?? ''));
        $color = strtoupper(trim((string) ($_POST['color'] ?? '#FF4D2E')));
        $iconName = (string) ($_POST['icon'] ?? 'spark');
        $order = max(0, min(999, (int) ($_POST['sort_order'] ?? 0)));
        $active = !empty($_POST['is_active']) ? 1 : 0;
        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            flash('error', 'Category name must be 2–60 characters.');
        } elseif (!preg_match('/^#[0-9A-F]{6}$/', $color)) {
            flash('error', 'Choose a valid color.');
        } elseif (!in_array($iconName, category_icon_options(), true)) {
            flash('error', 'Choose a valid icon.');
        } elseif (db_value('SELECT 1 FROM categories WHERE name = ? AND id <> ?', [$name, $id])) {
            flash('error', 'A category with this name already exists.');
        } else {
            if ($id) {
                db_exec('UPDATE categories SET name = ?, description = ?, color = ?, icon = ?, sort_order = ?, is_active = ? WHERE id = ?', [$name, $desc ?: null, $color, $iconName, $order, $active, $id]);
                log_activity('category.updated', 'Updated category ' . $name, 'category', $id);
            } else {
                db_exec('INSERT INTO categories (name, slug, description, color, icon, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$name, unique_slug('categories', $name), $desc ?: null, $color, $iconName, $order, $active]);
                log_activity('category.created', 'Created category ' . $name, 'category', (int) db()->lastInsertId());
            }
            flash('success', 'Category saved.');
        }
    } elseif ($action === 'delete' && $id) {
        if ((int) db_value('SELECT COUNT(*) FROM events WHERE category_id = ?', [$id]) > 0) {
            flash('error', 'This category has events. Hide it instead of deleting.');
        } else {
            db_exec('DELETE FROM categories WHERE id = ?', [$id]);
            flash('success', 'Category deleted.');
        }
    }
    redirect('admin/categories.php');
}

$cats = db_all("SELECT c.*, (SELECT COUNT(*) FROM events e WHERE e.category_id = c.id) AS events,
                       (SELECT COUNT(*) FROM events e WHERE e.category_id = c.id AND e.status = 'published' AND e.ends_at >= NOW()) AS live
                  FROM categories c ORDER BY c.sort_order, c.name");

$pageTitle = 'Categories';
$layout = 'dashboard';
$dashActive = 'categories';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head">
    <div><h1>Categories</h1><p>Organize how people discover events.</p></div>
    <div class="actions"><button type="button" class="btn btn-primary btn-sm" data-modal-open="categoryModal"><?= icon('plus') ?> New category</button></div>
</div>
<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Category</th><th>Slug</th><th class="num">Events</th><th class="num">Order</th><th>Status</th><th class="num">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($cats as $c): ?>
            <tr>
                <td><div class="cell-main"><span class="category-tile" style="--cat: <?= e($c['color']) ?>;padding:0;border:0;background:none"><span class="cat-icon" style="width:40px;height:40px"><?= icon($c['icon']) ?></span></span>
                    <div><strong><?= e($c['name']) ?></strong><span><?= e(str_limit((string) $c['description'], 60)) ?></span></div></div></td>
                <td><code class="small"><?= e($c['slug']) ?></code></td>
                <td class="num"><?= (int) $c['events'] ?> <span class="small muted">(<?= (int) $c['live'] ?> live)</span></td>
                <td class="num"><?= (int) $c['sort_order'] ?></td>
                <td><?= status_badge($c['is_active'] ? 'active' : 'hidden') ?></td>
                <td><div class="actions">
                    <button type="button" class="btn btn-soft btn-xs" data-modal-open="categoryModal" data-category="<?= e(json_encode(['id' => (int) $c['id'], 'name' => $c['name'], 'description' => $c['description'], 'color' => $c['color'], 'icon' => $c['icon'], 'sort_order' => (int) $c['sort_order'], 'is_active' => (int) $c['is_active']])) ?>"><?= icon('edit') ?> Edit</button>
                    <?php if (!(int) $c['events']): ?>
                        <form method="post" class="inline-form" data-confirm="Delete the category “<?= e($c['name']) ?>”?" data-confirm-button="Delete"><?= csrf_field() ?><input type="hidden" name="category_id" value="<?= (int) $c['id'] ?>"><button class="btn btn-ghost btn-xs" name="action" value="delete" aria-label="Delete"><?= icon('trash') ?></button></form>
                    <?php endif; ?>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="modal" id="categoryModal" hidden>
    <div class="modal-backdrop"></div>
    <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="catTitle">
        <form method="post" data-validate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="category_id" value="">
            <div class="modal-head"><h2 id="catTitle">New category</h2><button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('close') ?></button></div>
            <div class="modal-body form-grid">
                <div class="field"><label for="catName">Name</label><input class="input" id="catName" name="name" required minlength="2" maxlength="60"></div>
                <div class="field"><label for="catDesc">Description</label><input class="input" id="catDesc" name="description" maxlength="255"></div>
                <div class="form-row form-row-3">
                    <div class="field"><label for="catColor">Color</label><input class="input" id="catColor" type="color" name="color" value="#FF4D2E" style="padding:4px;height:48px"></div>
                    <div class="field"><label for="catIcon">Icon</label><select class="select" id="catIcon" name="icon"><?php foreach (category_icon_options() as $i): ?><option value="<?= e($i) ?>"><?= e(ucfirst($i)) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="catOrder">Order</label><input class="input" id="catOrder" type="number" name="sort_order" min="0" max="999" value="0"></div>
                </div>
                <label class="check"><input type="checkbox" name="is_active" value="1" checked> Visible on the site</label>
            </div>
            <div class="modal-foot"><button type="button" class="btn btn-soft" data-modal-close>Cancel</button><button class="btn btn-primary">Save category</button></div>
        </form>
    </div>
</div>
<?php
$pageScripts = ['admin.js'];
require __DIR__ . '/../includes/footer.php';
