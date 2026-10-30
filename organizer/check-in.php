<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/organizer.php';

$org = require_organizer();
$events = db_all(
    "SELECT id, title, starts_at, ends_at FROM events
      WHERE organizer_id = ? AND status = 'published' ORDER BY (ends_at < NOW()), ABS(TIMESTAMPDIFF(MINUTE, NOW(), starts_at)) ASC", [$org['id']]);
$eventId = (int) ($_GET['event'] ?? ($events[0]['id'] ?? 0));
$event = $eventId ? org_event_or_404($eventId, $org) : null;
$counts = $event ? db_one(
    "SELECT COUNT(*) AS total, COALESCE(SUM(t.status = 'checked_in'), 0) AS checked
       FROM tickets t JOIN booking_items bi ON bi.id = t.booking_item_id JOIN bookings b ON b.id = bi.booking_id
      WHERE b.event_id = ? AND t.status <> 'cancelled'", [$eventId]) : ['total' => 0, 'checked' => 0];
$pct = $counts['total'] ? round($counts['checked'] / $counts['total'] * 100) : 0;

$pageTitle = 'Check-in';
$layout = 'dashboard';
$dashActive = 'check-in';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head">
    <div><h1>QR check-in</h1><p>Scan tickets at the door. Every scan is verified on the server.</p></div>
</div>

<?php if (!$event): ?>
    <div class="empty-state"><div class="empty-icon"><?= icon('scan') ?></div><h3>No published events</h3><p>Once you publish an event and sell tickets, you can check guests in here.</p></div>
<?php else: ?>
    <form class="toolbar" method="get">
        <label class="sr-only" for="ciEvent">Event</label>
        <select class="select" id="ciEvent" name="event" data-autosubmit style="min-width:280px">
            <?php foreach ($events as $ev): ?>
                <option value="<?= (int) $ev['id'] ?>"<?= (int) $ev['id'] === $eventId ? ' selected' : '' ?>><?= e($ev['title']) ?> · <?= e(fmt_date($ev['starts_at'], 'M j')) ?><?= strtotime($ev['ends_at']) < time() ? ' (ended)' : '' ?></option>
            <?php endforeach; ?>
        </select>
        <span class="spacer"></span>
        <a class="btn btn-soft btn-sm" href="<?= e(url('organizer/attendees.php?event=' . $eventId)) ?>"><?= icon('users') ?> Guest list</a>
    </form>

    <?php $startsIn = strtotime($event['starts_at']) - time(); ?>
    <?php if ($startsIn > 86400): ?>
        <div class="alert alert-info mb-3"><?= icon('info') ?><div>This event starts <?= e(time_until($event['starts_at'])) ?>. You can still test check-in now — scanned tickets will be marked as used.</div></div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="hbar">
            <div class="hbar-top"><span><strong data-ci-checked><?= number_format((int) $counts['checked']) ?></strong> of <span data-ci-total><?= number_format((int) $counts['total']) ?></span> guests checked in</span><span data-ci-pct><?= $pct ?>%</span></div>
            <div class="checkin-progress"><i data-ci-bar style="width: <?= $pct ?>%"></i></div>
        </div>
    </div>

    <div class="scanner-layout" data-scanner data-event="<?= (int) $eventId ?>">
        <div class="form-grid">
            <div class="scanner-box" data-scanner-box>
                <video playsinline muted data-video hidden></video>
                <div class="scanner-frame" data-frame hidden></div>
                <div class="scanner-idle" data-idle>
                    <?= icon('camera') ?>
                    <strong style="color:#fff">Scan ticket QR codes with your camera</strong>
                    <span class="small">Allow camera access when your browser asks.</span>
                    <button type="button" class="btn btn-lime" data-start-camera><?= icon('scan') ?> Start scanner</button>
                </div>
            </div>
            <div class="flex gap-1 wrap">
                <button type="button" class="btn btn-soft btn-sm" data-stop-camera hidden><?= icon('close') ?> Stop camera</button>
                <button type="button" class="btn btn-soft btn-sm" data-switch-camera hidden><?= icon('refresh') ?> Switch camera</button>
            </div>
            <form class="card" data-manual-form>
                <div class="field">
                    <label for="manualCode">Enter ticket code manually</label>
                    <div class="flex gap-1">
                        <input class="input" id="manualCode" name="code" placeholder="ABCD-EFGH-JKLM-NPQR" autocomplete="off" autocapitalize="characters" maxlength="80" style="font-family:ui-monospace,monospace;letter-spacing:.05em">
                        <button class="btn btn-dark" type="submit">Verify</button>
                    </div>
                    <span class="hint">Use this when a phone screen is cracked or too dim to scan.</span>
                </div>
            </form>
        </div>

        <div class="form-grid">
            <div class="scan-result" data-result aria-live="assertive">
                <div class="sr-icon" style="background:var(--paper-2);color:var(--muted)"><?= icon('qr', 'icon-lg') ?></div>
                <h2>Ready to scan</h2>
                <p class="muted mb-0">Point the camera at a ticket QR code. Results appear here instantly.</p>
            </div>
            <section class="card">
                <div class="card-head"><h2>This session</h2><span class="small muted" data-log-count>0 scans</span></div>
                <div class="scan-log" data-log><p class="muted small mb-0" data-log-empty>No scans yet.</p></div>
            </section>
        </div>
    </div>
<?php endif; ?>
<?php
$pageScripts = ['https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js', 'checkin.js'];
require __DIR__ . '/../includes/footer.php';
