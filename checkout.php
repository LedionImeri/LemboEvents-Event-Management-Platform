<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/booking.php';

$user = require_login();
$eventId = (int) ($_POST['event'] ?? $_GET['event'] ?? 0);
$qty = $_POST['qty'] ?? $_GET['qty'] ?? [];
$qty = is_array($qty) ? array_slice($qty, 0, 20, true) : [];

$event = $eventId ? db_one('SELECT slug FROM events WHERE id = ?', [$eventId]) : null;
$backUrl = $event ? 'event.php?slug=' . $event['slug'] . '#tickets' : 'events.php';

if ($user['role'] !== 'attendee') {
    flash('info', 'Ticket purchases are available for attendee accounts. Create an attendee account to book tickets.');
    redirect($backUrl);
}

$errors = [];
$quote = null;
try {
    $quote = booking_quote($eventId, $qty);
} catch (BookingException $e) {
    flash('error', $e->getMessage());
    redirect($backUrl);
}
$isFree = $quote['total'] <= 0;

// One-time token prevents double bookings from double clicks / resubmits
if (is_post()) {
    csrf_verify();
    $nonce = (string) ($_POST['checkout_nonce'] ?? '');
    $used = $_SESSION['checkout_done'][$nonce] ?? null;
    if ($used) {
        redirect('booking.php?ref=' . rawurlencode($used));
    }
    if ($nonce === '' || !hash_equals($_SESSION['checkout_nonce'] ?? '', $nonce)) {
        flash('error', 'Your checkout session expired. Please review your order and try again.');
        redirect('checkout.php?' . http_build_query(['event' => $eventId, 'qty' => $qty]));
    }

    $name = trim((string) ($_POST['customer_name'] ?? ''));
    $email = mb_strtolower(trim((string) ($_POST['customer_email'] ?? '')));
    $phone = trim((string) ($_POST['customer_phone'] ?? ''));
    if (mb_strlen($name) < 3 || mb_strlen($name) > 120) $errors['customer_name'] = 'Enter the full name for the tickets.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) $errors['customer_email'] = 'Enter a valid email address.';
    if ($phone !== '' && !preg_match('/^[+0-9 ()\-]{6,30}$/', $phone)) $errors['customer_phone'] = 'Enter a valid phone number.';
    if (empty($_POST['accept'])) $errors['accept'] = 'Please accept the terms and refund policy.';

    $last4 = null;
    if (!$isFree) {
        $method = (string) ($_POST['payment_method'] ?? 'card');
        if (!in_array($method, ['card', 'apple', 'google'], true)) $method = 'card';
        if ($method === 'card') {
            $cardNumber = (string) ($_POST['card_number'] ?? '');
            if ($cardErr = demo_card_error($cardNumber, (string) ($_POST['card_expiry'] ?? ''), (string) ($_POST['card_cvc'] ?? ''), (string) ($_POST['card_name'] ?? ''))) {
                $errors['card'] = $cardErr;
            }
            $last4 = substr(preg_replace('/\D/', '', $cardNumber), -4);   // the only card data ever stored
        } else {
            $last4 = '0000';   // simulated wallet payment
        }
    }

    if (!$errors) {
        try {
            $booking = create_booking($user, $eventId, $qty, ['name' => $name, 'email' => $email, 'phone' => $phone], ['last4' => $last4]);
            unset($_SESSION['checkout_nonce']);
            $_SESSION['checkout_done'][$nonce] = $booking['reference'];
            redirect('booking.php?ref=' . rawurlencode($booking['reference']) . '&new=1');
        } catch (BookingException $e) {
            flash('error', $e->getMessage());
            redirect($backUrl);
        }
    }
    remember_input($_POST);
}

$_SESSION['checkout_nonce'] ??= bin2hex(random_bytes(16));
$ev = $quote['event'];
$pageTitle = 'Checkout · ' . $ev['title'];
$pageDescription = 'Secure checkout for ' . $ev['title'];
$fieldErr = fn(string $k) => isset($errors[$k]) ? '<span class="field-error">' . e($errors[$k]) . '</span>' : '';
$hasErr = fn(string $k) => isset($errors[$k]) ? ' has-error' : '';
require __DIR__ . '/includes/header.php';
?>
<div class="container">
    <div class="page-hero" style="padding-bottom:20px">
        <nav class="breadcrumbs" aria-label="Breadcrumb">
            <a href="<?= e(url('event.php?slug=' . $ev['slug'])) ?>"><?= icon('arrow-left') ?> Back to event</a>
        </nav>
        <div class="checkout-steps" aria-label="Checkout progress">
            <span class="done"><i><?= icon('check', 'icon-sm') ?></i> Tickets</span><span class="sep"></span>
            <span class="current"><i>2</i> Details &amp; payment</span><span class="sep"></span>
            <span><i>3</i> Your tickets</span>
        </div>
        <h1>Checkout</h1>
    </div>

    <div class="checkout-layout">
        <form method="post" class="form-grid" data-validate data-loading data-checkout autocomplete="on">
            <?= csrf_field() ?>
            <input type="hidden" name="checkout_nonce" value="<?= e($_SESSION['checkout_nonce']) ?>">
            <input type="hidden" name="event" value="<?= (int) $eventId ?>">
            <?php foreach ($quote['lines'] as $l): ?>
                <input type="hidden" name="qty[<?= (int) $l['type']['id'] ?>]" value="<?= (int) $l['qty'] ?>">
            <?php endforeach; ?>

            <?php if ($errors): ?>
                <div class="alert alert-error" role="alert"><?= icon('alert') ?><div>
                    <?= isset($errors['card']) ? e($errors['card']) : 'Please check the highlighted fields.' ?>
                </div></div>
            <?php endif; ?>

            <section class="card">
                <div class="card-head"><h2><?= icon('user') ?> Contact details</h2></div>
                <div class="form-grid">
                    <div class="field<?= $hasErr('customer_name') ?>">
                        <label for="customer_name">Full name (printed on tickets) <span class="req">*</span></label>
                        <input class="input" id="customer_name" name="customer_name" value="<?= e(old('customer_name', $user['name'])) ?>" required minlength="3" maxlength="120" autocomplete="name">
                        <?= $fieldErr('customer_name') ?>
                    </div>
                    <div class="form-row">
                        <div class="field<?= $hasErr('customer_email') ?>">
                            <label for="customer_email">Email <span class="req">*</span></label>
                            <input class="input" id="customer_email" type="email" name="customer_email" value="<?= e(old('customer_email', $user['email'])) ?>" required maxlength="190" autocomplete="email">
                            <?= $fieldErr('customer_email') ?>
                        </div>
                        <div class="field<?= $hasErr('customer_phone') ?>">
                            <label for="customer_phone">Phone</label>
                            <input class="input" id="customer_phone" type="tel" name="customer_phone" value="<?= e(old('customer_phone', (string) $user['phone'])) ?>" maxlength="30" autocomplete="tel">
                            <?= $fieldErr('customer_phone') ?>
                        </div>
                    </div>
                </div>
            </section>

            <?php if (!$isFree): ?>
            <section class="card">
                <div class="card-head"><h2><?= icon('card') ?> Payment</h2><span class="badge badge-warning">Demo mode</span></div>
                <div class="demo-banner"><?= icon('info') ?> This is a demo payment system — no real money is charged. Use test card 4242 4242 4242 4242, any future date and any CVC.</div>
                <div class="pay-methods" role="radiogroup" aria-label="Payment method">
                    <label class="pay-method"><input type="radio" name="payment_method" value="card" <?= old('payment_method', 'card') === 'card' ? 'checked' : '' ?>><span><?= icon('card') ?> Card<small>Visa, Mastercard</small></span></label>
                    <label class="pay-method"><input type="radio" name="payment_method" value="apple" <?= old('payment_method') === 'apple' ? 'checked' : '' ?>><span><?= icon('phone') ?> Apple Pay<small>Simulated</small></span></label>
                    <label class="pay-method"><input type="radio" name="payment_method" value="google" <?= old('payment_method') === 'google' ? 'checked' : '' ?>><span><?= icon('zap') ?> Google Pay<small>Simulated</small></span></label>
                </div>

                <div class="form-grid mt-3" data-card-fields>
                    <div class="card-preview" aria-hidden="true">
                        <span class="cp-brand" data-cp-brand>VISA</span>
                        <span class="cp-chip"></span>
                        <span class="cp-number" data-cp-number>•••• •••• •••• ••••</span>
                        <div class="cp-row"><span data-cp-name>YOUR NAME</span><span data-cp-exp>MM/YY</span></div>
                    </div>
                    <div class="field<?= isset($errors['card']) ? ' has-error' : '' ?>">
                        <label for="card_name">Name on card</label>
                        <input class="input" id="card_name" name="card_name" value="<?= e(old('card_name', $user['name'])) ?>" maxlength="80" autocomplete="cc-name" required>
                    </div>
                    <div class="field<?= isset($errors['card']) ? ' has-error' : '' ?>">
                        <label for="card_number">Card number</label>
                        <div class="input-icon"><?= icon('card') ?><input class="input" id="card_number" name="card_number" inputmode="numeric" autocomplete="cc-number" maxlength="23" placeholder="4242 4242 4242 4242" required pattern="[0-9 ]{13,23}" title="Enter a valid card number"></div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="card_expiry">Expiry</label>
                            <input class="input" id="card_expiry" name="card_expiry" inputmode="numeric" autocomplete="cc-exp" maxlength="7" placeholder="MM/YY" required pattern="(0[1-9]|1[0-2]) ?/ ?[0-9]{2}" title="Use MM/YY">
                        </div>
                        <div class="field">
                            <label for="card_cvc">CVC</label>
                            <input class="input" id="card_cvc" name="card_cvc" inputmode="numeric" autocomplete="cc-csc" maxlength="4" placeholder="123" required pattern="[0-9]{3,4}" title="3 or 4 digits">
                        </div>
                    </div>
                    <p class="small muted mb-0"><?= icon('lock', 'icon-sm') ?> Card details are validated and then discarded. Only the last 4 digits are stored on your receipt.</p>
                </div>
                <div class="alert alert-info mt-3" data-wallet-note hidden><?= icon('info') ?><div>You'll confirm with your wallet in the next step (simulated in this demo).</div></div>
            </section>
            <?php endif; ?>

            <div class="field<?= $hasErr('accept') ?>">
                <label class="check"><input type="checkbox" name="accept" value="1" required<?= old('accept') ? ' checked' : '' ?>>
                    <span>I agree to the Terms of Service. Bookings can be cancelled up to <?= CANCELLATION_WINDOW_HOURS ?> hours before the event for a full refund.</span></label>
                <?= $fieldErr('accept') ?>
            </div>
            <button class="btn btn-primary btn-lg btn-block" type="submit">
                <?= icon('lock') ?> <?= $isFree ? 'Confirm free booking' : 'Pay ' . e(money_exact($quote['total'])) ?>
            </button>
        </form>

        <aside class="order-summary" aria-label="Order summary">
            <div class="order-summary-media"><img src="<?= e(image_thumb($ev['cover_image'], 900)) ?>" alt="" loading="lazy"></div>
            <div class="order-summary-body">
                <div>
                    <h3><?= e($ev['title']) ?></h3>
                    <ul class="event-meta mt-1">
                        <li><?= icon('calendar') ?><?= e(fmt_event_range($ev['starts_at'], $ev['ends_at'])) ?></li>
                        <li><?= icon('pin') ?><?= e($ev['venue_name']) ?>, <?= e($ev['city']) ?></li>
                    </ul>
                </div>
                <div class="order-lines">
                    <?php foreach ($quote['lines'] as $l): ?>
                        <div class="order-line"><span><?= (int) $l['qty'] ?> × <?= e($l['type']['name']) ?></span><span><?= e($l['line'] > 0 ? money_exact($l['line']) : 'Free') ?></span></div>
                        <div class="order-line small muted" style="margin-top:-6px"><span><?= e(money($l['type']['price'])) ?> each</span></div>
                    <?php endforeach; ?>
                </div>
                <div class="summary-line"><span>Subtotal</span><span><?= e(money_exact($quote['subtotal'])) ?></span></div>
                <div class="summary-line"><span>Service fee (<?= (int) (SERVICE_FEE_RATE * 100) ?>%)</span><span><?= e(money_exact($quote['fee'])) ?></span></div>
                <div class="summary-line total"><span>Total</span><span><?= e($isFree ? 'Free' : money_exact($quote['total'])) ?></span></div>
                <a class="link small" href="<?= e(url('event.php?slug=' . $ev['slug'] . '#tickets')) ?>">Change tickets</a>
            </div>
        </aside>
    </div>
</div>
<?php
$pageScripts = ['checkout.js'];
require __DIR__ . '/includes/footer.php';
