<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_role('organizer');
$pageTitle = 'Notifications';
$layout = 'dashboard';
$dashActive = 'notifications';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/partials/notifications_page.php';
require __DIR__ . '/../includes/footer.php';
