<?php
// Create or edit a homepage "Latest Update". Content is trusted admin HTML
// (rendered raw by index.php); other fields are escaped on output.
include '_adminGuard.php';

function normalizeDateTime($v) {
    $v = trim((string)$v);
    if ($v === '') { return null; }
    $v = str_replace('T', ' ', $v);
    if (strlen($v) === 16) { $v .= ':00'; }
    return $v;
}
function toLocalInput($dt) {
    if (empty($dt)) { return ''; }
    return substr(str_replace(' ', 'T', $dt), 0, 16);
}

$errors = [];
$item = [
    'id' => 0, 'title' => 'Latest Update', 'author' => ($currentPersonName ?: 'Admin'),
    'content' => '', 'startDateTime' => date('Y-m-d H:i:s'), 'expireDateTime' => null,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    requireCsrf();
    $item['id']             = (int)($_POST['id'] ?? 0);
    $item['title']          = trim($_POST['title'] ?? '');
    $item['author']         = trim($_POST['author'] ?? '');
    $item['content']        = (string)($_POST['content'] ?? '');
    $item['startDateTime']  = normalizeDateTime($_POST['startDateTime'] ?? '') ?? date('Y-m-d H:i:s');
    $item['expireDateTime'] = normalizeDateTime($_POST['expireDateTime'] ?? '');

    if ($item['title'] === '') { $item['title'] = 'Latest Update'; }
    if (strlen($item['title']) > 128) { $errors[] = 'Title is too long (max 128).'; }
    if ($item['author'] === '') { $item['author'] = $currentPersonName ?: 'Admin'; }
    if (trim(strip_tags($item['content'])) === '' && strpos($item['content'], '<img') === false) {
        $errors[] = 'Content is required.';
    }

    if (empty($errors)) {
        if ($item['id'] > 0) {
            $up = $db->prepare("UPDATE `siteUpdate` SET `title`=?, `content`=?, `startDateTime`=?, `expireDateTime`=?, `author`=? WHERE `id`=?");
            $up->bind_param("sssssi", $item['title'], $item['content'], $item['startDateTime'], $item['expireDateTime'], $item['author'], $item['id']);
            $up->execute();
            logHistory("siteUpdateUpdate", $item['id'], "siteUpdate", $item['id']);
        } else {
            $ins = $db->prepare("INSERT INTO `siteUpdate` (`title`, `content`, `startDateTime`, `expireDateTime`, `author`) VALUES (?, ?, ?, ?, ?)");
            $ins->bind_param("sssss", $item['title'], $item['content'], $item['startDateTime'], $item['expireDateTime'], $item['author']);
            $ins->execute();
            $newId = $db->insert_id;
            logHistory("siteUpdateCreate", $newId, "siteUpdate", $newId);
        }
        header('Location: /admin/updates.php?saved=1', true, 302);
        exit;
    }
} elseif (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $q = $db->prepare("SELECT `id`, `title`, `author`, `content`, `startDateTime`, `expireDateTime` FROM `siteUpdate` WHERE `id` = ?");
    $q->bind_param("i", $id);
    $q->execute();
    $data = $q->get_result()->fetch_all(MYSQLI_ASSOC);
    if (count($data) > 0) { $item = $data[0]; }
}

$pageTitle = ($item['id'] > 0 ? 'Edit Update' : 'New Update');
include '../_head.php';
?>
<div class="container">
    <h1 class="h3"><?php echo e($pageTitle); ?></h1>
    <hr>
    <?php foreach ($errors as $err) { echo('<div class="alert alert-warning">' . e($err) . '</div>'); } ?>

    <form method="post" action="updateEdit.php">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
        <input type="hidden" name="csrf" value="<?php echo $csrfToken; ?>">

        <div class="row">
            <div class="col-md-6">
                <label class="form-label" for="title">Heading</label>
                <input class="form-control" id="title" name="title" value="<?php echo e($item['title']); ?>" maxlength="128">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="startDateTime">Publish (start)</label>
                <input type="datetime-local" class="form-control" id="startDateTime" name="startDateTime" value="<?php echo e(toLocalInput($item['startDateTime'])); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="expireDateTime">Expire (optional)</label>
                <input type="datetime-local" class="form-control" id="expireDateTime" name="expireDateTime" value="<?php echo e(toLocalInput($item['expireDateTime'])); ?>">
            </div>
        </div>
        <div class="row mt-2">
            <div class="col"><div class="form-text">The newest update whose start is in the past and which has not expired is the one shown on the home page.</div></div>
        </div>
        <div class="row mt-3">
            <div class="col">
                <label class="form-label" for="content">Content <small class="text-body-secondary">(use the <i class="fa-solid fa-code"></i> Code View button for raw HTML)</small></label>
                <textarea class="form-control bot-editor" id="content" name="content"><?php echo e($item['content']); ?></textarea>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col">
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                <a class="btn btn-outline-secondary" href="/admin/updates.php">Cancel</a>
                <a class="btn btn-outline-secondary float-end" href="/" target="_blank"><i class="fa-solid fa-up-right-from-square"></i> View home page</a>
            </div>
        </div>
    </form>

    <?php include '_editorAssets.php'; ?>
    <?php include '_attachmentPanel.php'; ?>
</div>
<?php include '../_footer.php'; ?>
