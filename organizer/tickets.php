<?php
/** Ticket type management for one event: prices, quantities, sale windows and availability. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/organizer.php';

$org = require_organizer();
$events = db_all("SELECT id, title, starts_at, status FROM events WHERE organizer_id = ? AND status <> 'cancelled' ORDER BY (ends_at < NOW()), starts_at ASC", [$org['id']]);
$eventId = (int) ($_GET['event'] ?? $_POST['event_id'] ?? ($events[0]['id'] ?? 0));
$event = $eventId ? org_event_or_404($eventId, $org) : null;

if (is_post() && $event) {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $ttId = (int) ($_POST['ticket_type_id'] ?? 0);
    $tt = $ttId ? db_one('SELECT * FROM ticket_types WHERE id = ? AND event_id = ?', [$ttId, $event['id']]) : null;
    $name = trim((string) ($_POST['name'] ?? ''));
    $price = $_POST['price'] ?? '';
    $qty = $_POST['quantity'] ?? '';
    $saleEnd = trim((string) ($_POST['sale_ends_at'] ?? ''));
    $saleStart = trim((string) ($_POST['sale_starts_at'] ?? ''));
    $validate = function () use ($name, $price, $qty, $tt, $saleStart, $saleEnd): ?string {
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) return 'Ticket name must be 2–80 characters.';
        if (!is_numeric($price) || (float) $price < 0 || (float) $price > 10000) return 'Price must be between €0 and €10,000.';
        if (!ctype_digit((string) $qty) || (int) $qty < 1 || (int) $qty > 100000) return 'Quantity must be between 1 and 100,000.';
        if ($tt && (int) $qty < (int) $tt['quantity_sold']) return $tt['quantity_sold'] . ' tickets are already sold — quantity cannot be lower.';
        if (($saleStart !== '' && !strtotime($saleStart)) || ($saleEnd !== '' && !strtotime($saleEnd))) return 'Check the sale dates.';
        if ($saleStart !== '' && $saleEnd !== '' && strtotime($saleEnd) <= strtotime($saleStart)) return 'Sale end must be after sale start.';
        return null;
    };
    $dt = fn($v) => $v !== '' ? date('Y-m-d H:i:s', strtotime($v)) : null;

    if ($action === 'update' && $tt) {
        if ($err = $validate()) {
            flash('error', $err);
        } else {
            db_exec('UPDATE ticket_types SET name = ?, description = ?, price = ?, quantity = ?, sale_starts_at = ?, sale_ends_at = ? WHERE id = ?',
                [$name, trim((string) ($_POST['description'] ?? '')) ?: null, round((float) $price, 2), (int) $qty, $dt($saleStart), $dt($saleEnd), $tt['id']]);
            flash('success', $name . ' updated.');
        }
    } elseif ($action === 'create') {
        if ($err = $validate()) {
            flash('error', $err);
        } else {
            $order = (int) db_value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM ticket_types WHERE event_id = ?', [$event['id']]);
            db_exec('INSERT INTO ticket_types (event_id, name, description, price, quantity, sale_starts_at, sale_ends_at, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$event['id'], $name, trim((string) ($_POST['description'] ?? '')) ?: null, round((float) $price, 2), (int) $qty, $dt($saleStart), $dt($saleEnd), $order]);
            flash('success', 'Ticket type “' . $name . '” added.');
        }
    } elseif ($action === 'toggle' && $tt) {
        db_exec('UPDATE ticket_types SET is_active = 1 - is_active WHERE id = ?', [$tt['id']]);
        flash('success', $tt['name'] . ($tt['is_active'] ? ' is now hidden from sale.' : ' is on sale again.'));
    } elseif ($action === 'delete' && $tt) {
        if (db_value('SELECT 1 FROM booking_items WHERE ticket_type_id = ? LIMIT 1', [$tt['id']])) {
            flash('error', 'This ticket type has sales and cannot be deleted. Pause it instead.');
        } else {
            db_exec('DELETE FROM ticket_types WHERE id = ?', [$tt['id']]);
            flash('success', 'Ticket type deleted.');
        }
    }
    log_activity('tickets.updated', 'Updated ticket types for ' . $event['title'], 'event', (int) $event['id']);
    redirect('organizer/tickets.php?event=' . $event['id']);
}

$types = $event ? db_all('SELECT * FROM ticket_types WHERE event_id = ? ORDER BY sort_order, id', [$event['id']]) : [];
$dtLocal = fn($v) => $v ? date('Y-m-d\TH:i', strtotime($v)) : '';

$pageTitle = 'Ticket types';
$layout = 'dashboard';
$dashActive = 'tickets';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head">
    <div><h1>Ticket types</h1><p>Prices, quantities and availability — changes apply to new purchases instantly.</p></div>
</div>

<?php if (!$events): ?>
    <div class="empty-state"><div class="empty-icon"><?= icon('ticket') ?></div><h3>No events yet</h3><p>Create an event first, then manage its tickets here.</p><a class="btn btn-primary" href="<?= e(url('organizer/create-event.php')) ?>">Create event</a></div>
<?php else: ?>
    <form class="toolbar" method="get">
        <label class="sr-only" for="evSel">Event</label>
        <select class="select" id="evSel" name="event" data-autosubmit style="min-width:280px">
            <?php foreach ($events as $ev): ?><option value="<?= (int) $ev['id'] ?>"<?= (int) $ev['id'] === (int) $eventId ? ' selected' : '' ?>><?= e($ev['title']) ?> · <?= e(fmt_date($ev['starts_at'], 'M j')) ?></option><?php endforeach; ?>
        </select>
        <noscript><button class="btn btn-dark btn-sm">Show</button></noscript>
        <span class="spacer"></span>
        <a class="btn btn-soft btn-sm" href="<?= e(url('organizer/edit-event.php?id=' . $eventId)) ?>"><?= icon('edit') ?> Edit event</a>
    </form>

    <div class="stats">
        <?php $cap = array_sum(array_column($types, 'quantity')); $sold = array_sum(array_column($types, 'quantity_sold')); ?>
        <div class="stat"><span class="stat-icon"><?= icon('ticket') ?></span><small>Sold</small><strong><?= number_format($sold) ?></strong><span class="stat-foot">of <?= number_format($cap) ?> capacity</span></div>
        <div class="stat"><span class="stat-icon"><?= icon('layers') ?></span><small>Available</small><strong><?= number_format(max(0, $cap - $sold)) ?></strong><span class="stat-foot"><?= $cap ? round(($cap - $sold) / $cap * 100) : 0 ?>% remaining</span></div>
        <div class="stat"><span class="stat-icon"><?= icon('money') ?></span><small>Gross ticket value</small><strong><?= e(money_exact(array_sum(array_map(fn($t) => $t['price'] * $t['quantity_sold'], $types)))) ?></strong><span class="stat-foot">At current prices</span></div>
    </div>

    <div class="form-grid">
        <?php foreach ($types as $t):
            $state = ticket_type_state($t);
            $pct = $t['quantity'] ? round($t['quantity_sold'] / $t['quantity'] * 100) : 0; ?>
            <section class="card">
                <div class="card-head">
                    <h2><?= e($t['name']) ?> <span class="muted" style="font-weight:600"><?= e(money($t['price'])) ?></span></h2>
                    <div class="flex gap-1 items-center wrap">
                        <?= status_badge(['on_sale' => 'active', 'sold_out' => 'cancelled', 'inactive' => 'hidden', 'not_started' => 'pending', 'ended' => 'dismissed'][$state]) ?>
                        <span class="small muted"><?= ['on_sale' => 'On sale', 'sold_out' => 'Sold out', 'inactive' => 'Paused', 'not_started' => 'Scheduled', 'ended' => 'Sale ended'][$state] ?></span>
                    </div>
                </div>
                <div class="hbar mb-3">
                    <div class="hbar-top"><span><?= number_format((int) $t['quantity_sold']) ?> sold</span><span><?= number_format(max(0, $t['quantity'] - $t['quantity_sold'])) ?> left · <?= $pct ?>%</span></div>
                    <div class="hbar-track"><i style="width: <?= $pct ?>%"></i></div>
                </div>
                <form method="post" class="form-grid" data-loading>
                    <?= csrf_field() ?>
                    <input type="hidden" name="event_id" value="<?= (int) $eventId ?>">
                    <input type="hidden" name="ticket_type_id" value="<?= (int) $t['id'] ?>">
                    <div class="form-row form-row-3">
                        <div class="field"><label>Name</label><input class="input" name="name" value="<?= e($t['name']) ?>" required maxlength="80"></div>
                        <div class="field"><label>Price (€)</label><input class="input" type="number" name="price" value="<?= e($t['price']) ?>" min="0" step="0.01" required></div>
                        <div class="field"><label>Quantity</label><input class="input" type="number" name="quantity" value="<?= (int) $t['quantity'] ?>" min="<?= max(1, (int) $t['quantity_sold']) ?>" required></div>
                    </div>
                    <div class="form-row form-row-3">
                        <div class="field"><label>Description</label><input class="input" name="description" value="<?= e((string) $t['description']) ?>" maxlength="255"></div>
                        <div class="field"><label>Sale starts</label><input class="input" type="datetime-local" name="sale_starts_at" value="<?= e($dtLocal($t['sale_starts_at'])) ?>"></div>
                        <div class="field"><label>Sale ends</label><input class="input" type="datetime-local" name="sale_ends_at" value="<?= e($dtLocal($t['sale_ends_at'])) ?>"></div>
                    </div>
                    <div class="flex gap-1 wrap">
                        <button class="btn btn-dark btn-sm" name="action" value="update">Save</button>
                        <button class="btn btn-soft btn-sm" name="action" value="toggle" formnovalidate><?= $t['is_active'] ? 'Pause sales' : 'Resume sales' ?></button>
                        <?php if ((int) $t['quantity_sold'] === 0): ?><button class="btn btn-ghost btn-sm" name="action" value="delete" formnovalidate><?= icon('trash') ?> Delete</button><?php endif; ?>
                    </div>
                </form>
            </section>
        <?php endforeach; ?>

        <section class="card card-flat">
            <div class="card-head"><h2><?= icon('plus') ?> Add a ticket type</h2></div>
            <form method="post" class="form-grid" data-validate data-loading>
                <?= csrf_field() ?>
                <input type="hidden" name="event_id" value="<?= (int) $eventId ?>">
                <input type="hidden" name="action" value="create">
                <div class="form-row form-row-3">
                    <div class="field"><label for="ntName">Name</label><input class="input" id="ntName" name="name" required maxlength="80" placeholder="Early Bird"></div>
                    <div class="field"><label for="ntPrice">Price (€)</label><input class="input" id="ntPrice" type="number" name="price" min="0" step="0.01" required value="10"></div>
                    <div class="field"><label for="ntQty">Quantity</label><input class="input" id="ntQty" type="number" name="quantity" min="1" required value="100"></div>
                </div>
                <div class="form-row form-row-3">
                    <div class="field"><label for="ntDesc">Description</label><input class="input" id="ntDesc" name="description" maxlength="255"></div>
                    <div class="field"><label for="ntS">Sale starts</label><input class="input" id="ntS" type="datetime-local" name="sale_starts_at"></div>
                    <div class="field"><label for="ntE">Sale ends</label><input class="input" id="ntE" type="datetime-local" name="sale_ends_at"></div>
                </div>
                <div><button class="btn btn-primary btn-sm"><?= icon('plus') ?> Add ticket type</button></div>
            </form>
        </section>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
