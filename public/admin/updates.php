<?php
// Admin list of homepage "Latest Update" announcements (the siteUpdate table).
// The newest currently-active row is what index.php shows.
include '_adminGuard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    requireCsrf();
    $id = (int)($_POST['id'] ?? 0);
    $del = $db->prepare("DELETE FROM `siteUpdate` WHERE `id` = ?");
    $del->bind_param("i", $id);
    $del->execute();
    logHistory("siteUpdateDelete", $id, "siteUpdate", $id);
    header('Location: /admin/updates.php?deleted=1', true, 302);
    exit;
}

$pageTitle = "Site Updates";
include '../_head.php';

// The single row index.php will render right now (for the "shown on homepage" marker).
$activeId = 0;
$activeRow = $db->query("SELECT `id` FROM `siteUpdate` WHERE `startDateTime` < NOW() AND (`expireDateTime` > NOW() OR `expireDateTime` IS NULL) ORDER BY `startDateTime` DESC, `id` DESC LIMIT 1")->fetch_assoc();
if ($activeRow) { $activeId = (int)$activeRow['id']; }

$rows = $db->query("SELECT `id`, `title`, `startDateTime`, `expireDateTime` FROM `siteUpdate` ORDER BY `startDateTime` DESC, `id` DESC");
?>
<div class="container">
    <div class="card">
        <div class="card-header">
            <a href="updateEdit.php" class="btn btn-outline-secondary float-end"><i class="fa-solid fa-plus"></i> New Update</a>
            <h1 class="card-title mb-0 h3">Site Updates</h1>
        </div>
        <div class="card-body p-0">
            <?php if (isset($_GET['deleted'])) { echo('<div class="alert alert-success m-3">Update deleted.</div>'); } ?>
            <?php if (isset($_GET['saved'])) { echo('<div class="alert alert-success m-3">Update saved.</div>'); } ?>
            <p class="text-body-secondary small px-3 pt-3 mb-2">The newest active update (marked <span class="badge text-bg-info">On homepage</span>) is shown in the "Latest Update" box on the home page.</p>
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead><tr>
                        <th>Title</th><th>Status</th><th class="d-none d-md-table-cell">Posted</th>
                        <th class="d-none d-lg-table-cell">Expires</th><th class="text-end">Actions</th>
                    </tr></thead>
                    <tbody>
<?php
                        $now = new DateTime();
                        while ($row = $rows->fetch_assoc()) {
                            $start = new DateTime($row['startDateTime']);
                            $expired = !empty($row['expireDateTime']) && new DateTime($row['expireDateTime']) < $now;
                            if ((int)$row['id'] === $activeId) {
                                $badge = '<span class="badge text-bg-info">On homepage</span>';
                            } elseif ($start > $now) {
                                $badge = '<span class="badge text-bg-warning">Scheduled</span>';
                            } elseif ($expired) {
                                $badge = '<span class="badge text-bg-secondary">Expired</span>';
                            } else {
                                $badge = '<span class="badge text-bg-light">Superseded</span>';
                            }
                            echo('<tr>'
                                . '<td>' . e($row['title']) . '</td>'
                                . '<td>' . $badge . '</td>'
                                . '<td class="d-none d-md-table-cell">' . e(date('M j, Y g:i A', strtotime($row['startDateTime']))) . '</td>'
                                . '<td class="d-none d-lg-table-cell">' . (empty($row['expireDateTime']) ? '<span class="text-body-secondary">never</span>' : e(date('M j, Y', strtotime($row['expireDateTime'])))) . '</td>'
                                . '<td class="text-end text-nowrap">'
                                . '<a class="btn btn-sm btn-primary" href="updateEdit.php?id=' . (int)$row['id'] . '" title="Edit"><i class="fa-solid fa-pen"></i></a> '
                                . '<form method="post" action="updates.php" class="d-inline" onsubmit="return confirm(\'Delete this update?\');">'
                                . '<input type="hidden" name="action" value="delete">'
                                . '<input type="hidden" name="id" value="' . (int)$row['id'] . '">'
                                . '<input type="hidden" name="csrf" value="' . $csrfToken . '">'
                                . '<button class="btn btn-sm btn-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>'
                                . '</form>'
                                . '</td></tr>');
                        }
                        if ($rows->num_rows === 0) {
                            echo('<tr><td colspan="5" class="text-body-secondary p-3">No updates yet.</td></tr>');
                        }
?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <p class="mt-3"><a href="/admin">&larr; Back to Admin tools</a></p>
</div>
<?php include '../_footer.php'; ?>
