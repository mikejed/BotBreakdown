<?php
// Create or edit a blog post. Content is trusted admin HTML (rendered raw by
// blog/index.php), so it is stored as-is; every other field is escaped on output.
include '_adminGuard.php';

function normalizeDateTime($v) {
    $v = trim((string)$v);
    if ($v === '') { return null; }
    $v = str_replace('T', ' ', $v);
    if (strlen($v) === 16) { $v .= ':00'; }  // datetime-local omits seconds
    return $v;
}
function toLocalInput($dt) {
    if (empty($dt)) { return ''; }
    return substr(str_replace(' ', 'T', $dt), 0, 16);
}

$errors = [];
$post = [
    'id' => 0, 'title' => '', 'slug' => '', 'author' => ($currentPersonName ?: 'Admin'),
    'content' => '', 'startDateTime' => date('Y-m-d H:i:s'), 'expireDateTime' => null,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    requireCsrf();
    $post['id']             = (int)($_POST['id'] ?? 0);
    $post['title']          = trim($_POST['title'] ?? '');
    $post['slug']           = trim($_POST['slug'] ?? '');
    $post['author']         = trim($_POST['author'] ?? '');
    $post['content']        = (string)($_POST['content'] ?? '');
    $post['startDateTime']  = normalizeDateTime($_POST['startDateTime'] ?? '') ?? date('Y-m-d H:i:s');
    $post['expireDateTime'] = normalizeDateTime($_POST['expireDateTime'] ?? '');

    if ($post['title'] === '') { $errors[] = 'Title is required.'; }
    if ($post['author'] === '') { $post['author'] = $currentPersonName ?: 'Admin'; }
    if (trim(strip_tags($post['content'])) === '' && strpos($post['content'], '<img') === false) {
        $errors[] = 'Content is required.';
    }
    if ($post['slug'] === '') {
        $errors[] = 'Slug is required.';
    } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $post['slug'])) {
        $errors[] = 'Slug may contain only letters, numbers, dots, dashes and underscores (it is used in the URL).';
    } elseif (strlen($post['slug']) > 200) {
        $errors[] = 'Slug is too long.';
    } else {
        // Uniqueness, matching the DB's 32-char-prefix unique key so we never hit a raw key error.
        $dupe = $db->prepare("SELECT `id` FROM `blog` WHERE LEFT(`slug`,32) = LEFT(?,32) AND `id` <> ?");
        $dupe->bind_param("si", $post['slug'], $post['id']);
        $dupe->execute();
        if ($dupe->get_result()->num_rows > 0) {
            $errors[] = 'Another post already uses that slug (or one sharing its first 32 characters).';
        }
    }

    if (empty($errors)) {
        if ($post['id'] > 0) {
            $up = $db->prepare("UPDATE `blog` SET `slug`=?, `startDateTime`=?, `expireDateTime`=?, `author`=?, `title`=?, `content`=?, `modifiedDateTime`=NOW() WHERE `id`=?");
            $up->bind_param("ssssssi", $post['slug'], $post['startDateTime'], $post['expireDateTime'], $post['author'], $post['title'], $post['content'], $post['id']);
            $up->execute();
            logHistory("blogUpdate", $post['id'], "blog", $post['id']);
        } else {
            $ins = $db->prepare("INSERT INTO `blog` (`slug`, `startDateTime`, `expireDateTime`, `author`, `title`, `content`, `modifiedDateTime`) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $ins->bind_param("ssssss", $post['slug'], $post['startDateTime'], $post['expireDateTime'], $post['author'], $post['title'], $post['content']);
            $ins->execute();
            $newId = $db->insert_id;
            logHistory("blogCreate", $newId, "blog", $newId);
        }
        header('Location: /admin/blog.php?saved=1', true, 302);
        exit;
    }
} elseif (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $q = $db->prepare("SELECT `id`, `title`, `slug`, `author`, `content`, `startDateTime`, `expireDateTime` FROM `blog` WHERE `id` = ?");
    $q->bind_param("i", $id);
    $q->execute();
    $data = $q->get_result()->fetch_all(MYSQLI_ASSOC);
    if (count($data) > 0) { $post = $data[0]; }
}

$pageTitle = ($post['id'] > 0 ? 'Edit Post' : 'New Post');
include '../_head.php';
?>
<div class="container">
    <h1 class="h3"><?php echo e($pageTitle); ?></h1>
    <hr>
    <?php foreach ($errors as $err) { echo('<div class="alert alert-warning">' . e($err) . '</div>'); } ?>

    <form method="post" action="blogEdit.php">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?php echo (int)$post['id']; ?>">
        <input type="hidden" name="csrf" value="<?php echo $csrfToken; ?>">

        <div class="row">
            <div class="col-md-8">
                <label class="form-label" for="title">Title</label>
                <input class="form-control" id="title" name="title" value="<?php echo e($post['title']); ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="slug">Slug <small class="text-body-secondary">(URL)</small></label>
                <input class="form-control" id="slug" name="slug" value="<?php echo e($post['slug']); ?>" placeholder="my-post-title" required>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-4">
                <label class="form-label" for="author">Author</label>
                <input class="form-control" id="author" name="author" value="<?php echo e($post['author']); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="startDateTime">Publish (start)</label>
                <input type="datetime-local" class="form-control" id="startDateTime" name="startDateTime" value="<?php echo e(toLocalInput($post['startDateTime'])); ?>">
                <div class="form-text">Future = scheduled/hidden until then.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="expireDateTime">Expire (optional)</label>
                <input type="datetime-local" class="form-control" id="expireDateTime" name="expireDateTime" value="<?php echo e(toLocalInput($post['expireDateTime'])); ?>">
                <div class="form-text">Blank = never expires.</div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col">
                <label class="form-label" for="content">Content <small class="text-body-secondary">(use the <i class="fa-solid fa-code"></i> Code View button for raw HTML)</small></label>
                <textarea class="form-control bot-editor" id="content" name="content"><?php echo e($post['content']); ?></textarea>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col">
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                <a class="btn btn-outline-secondary" href="/admin/blog.php">Cancel</a>
                <?php if ($post['id'] > 0) { echo('<a class="btn btn-outline-secondary float-end" href="/blog/' . rawurlencode($post['slug']) . '" target="_blank"><i class="fa-solid fa-up-right-from-square"></i> Preview</a>'); } ?>
            </div>
        </div>
    </form>

    <?php include '_editorAssets.php'; ?>
    <?php include '_attachmentPanel.php'; ?>
</div>
<?php include '../_footer.php'; ?>
