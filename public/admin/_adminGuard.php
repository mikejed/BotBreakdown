<?php
// Admin-only guard. Include at the very top of every /admin page, before any
// output. Sets up $db and the session/auth globals via _dbConnection.php, then
// redirects any non-admin (including logged-out visitors) away.
include __DIR__ . '/../_dbConnection.php';
if (!$isAdmin) {
    header('Location: /', true, 302);
    exit;
}
