<?php
/** Page footer + LemboBot widget + scripts. */
$layout ??= 'public';
$pageScripts ??= [];
$footerCategories = db_all('SELECT name, slug FROM categories WHERE is_active = 1 ORDER BY sort_order LIMIT 6');
?>
<?php if ($layout === 'dashboard'): ?>
        </div><!-- /.dash-content -->
    </main>
</div><!-- /.dash -->
<?php else: ?>
</main>
<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <a class="brand brand-light" href="<?= e(url('index.php')) ?>">
                <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M4 9a3 3 0 0 0 3-3h18a3 3 0 0 0 3 3v3.5a3.5 3.5 0 0 0 0 7V23a3 3 0 0 0-3 3H7a3 3 0 0 0-3-3v-3.5a3.5 3.5 0 0 0 0-7z" fill="currentColor"/><path d="M12 11v10h7" fill="none" stroke="#12101A" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                <span class="brand-name">Lembo<span>Events</span></span>
            </a>
            <p>Discover events worth remembering. Concerts, festivals, conferences and workshops across Kosovo — with tickets in your pocket in seconds.</p>
            <div class="footer-badges">
                <span><?= icon('shield') ?> Secure checkout</span>
                <span><?= icon('qr') ?> QR tickets</span>
            </div>
        </div>
        <div>
            <h3>Discover</h3>
            <ul>
                <li><a href="<?= e(url('events.php')) ?>">All events</a></li>
                <li><a href="<?= e(url('events.php?date=weekend')) ?>">This weekend</a></li>
                <li><a href="<?= e(url('events.php?price=free')) ?>">Free events</a></li>
                <li><a href="<?= e(url('events.php?city=Prishtina')) ?>">Events in Prishtina</a></li>
            </ul>
        </div>
        <div>
            <h3>Categories</h3>
            <ul>
                <?php foreach ($footerCategories as $c): ?>
                    <li><a href="<?= e(url('events.php?category=' . $c['slug'])) ?>"><?= e($c['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div>
            <h3>Organizers</h3>
            <ul>
                <li><a href="<?= e(url('register.php?role=organizer')) ?>">Sell tickets</a></li>
                <li><a href="<?= e(url('organizer/dashboard.php')) ?>">Organizer dashboard</a></li>
                <li><a href="<?= e(url('organizer/check-in.php')) ?>">QR check-in</a></li>
                <li><a href="<?= e(url('index.php#how-it-works')) ?>">How it works</a></li>
            </ul>
        </div>
    </div>
    <div class="container footer-bottom">
        <span>&copy; <?= date('Y') ?> LemboEvents. Built in Prishtina.</span>
        <span>Payments on this site are a demo — no real charges are made.</span>
    </div>
</footer>
<?php endif; ?>

<?php require __DIR__ . '/partials/chatbot_widget.php'; ?>

<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(asset('js/chatbot.js')) ?>" defer></script>
<?php foreach ($pageScripts as $s): ?>
    <script src="<?= e(preg_match('#^https://#', $s) ? $s : asset('js/' . $s)) ?>"<?= preg_match('#^https://#', $s) ? ' crossorigin="anonymous"' : '' ?>></script>
<?php endforeach; ?>
</body>
</html>
<?php clear_old(); ?>
