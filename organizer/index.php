<?php
// Folder URL (e.g. /admin/) → the dashboard, which handles the login redirect.
header('Location: dashboard.php', true, 302);
exit;
