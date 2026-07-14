<?php
// Admin blog post list: create / edit / preview / delete.
include '_adminGuard.php';

// Delete (POST + CSRF, before any output so a forged request gets a real 403).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    requireCsrf();
    $id = (int)($_POST['id'] ?? 0);
    $del = $db->prepare("DELETE FROM `blog` WHERE `id` = ?");
    $del->bind_param("i", $id);
    $del->execute();
    logHistory("blogDelete", $id, "blog", $id);
    header('Location: /admin/blog.php?deleted=1', true, 302);
    exit;
}

$pageTitle = "Blog Posts";
include '../_head.php';

$posts = $db->query("SELECT `id`, `slug`, `title`, `author`, `startDateTime`, `expireDateTime`, `modifiedDateTime` FROM `blog` ORDER BY `startDateTime` DESC, `id` DESC");
?>
<div class="container">
    <div class="card">
        <div class="card-header">
            <a href="blogEdit.php" class="btn btn-outline-secondary float-end"><i class="fa-solid fa-plus"></i> New Post</a>
            <h1 class="card-title mb-0 h3">Blog Posts</h1>
        </div>
        <div class="card-body p-0">
            <?php if (isset($_GET['deleted'])) { echo('<div class="alert alert-success m-3">Post deleted.</div>'); } ?>
            <?php if (isset($_GET['saved'])) { echo('<div class="alert alert-success m-3">Post saved.</div>'); } ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead><tr>
                        <th>Title</th><th class="d-none d-md-table-cell">Slug</th><th>Status</th>
                        <th class="d-none d-lg-table-cell">Posted</th><th class="text-end">Actions</th>
                    </tr></thead>
                    <tbody>
<?php
                        $now = new DateTime();
                        while ($row = $posts->fetch_assoc()) {
                            $start = new DateTime($row['startDateTime']);
                            $expired = !empty($row['expireDateTime']) && new DateTime($row['expireDateTime']) < $now;
                            if ($start > $now) {
                                $badge = '<span class="badge text-bg-warning">Scheduled</span>';
                            } elseif ($expired) {
                                $badge = '<span class="badge text-bg-secondary">Expired</span>';
                            } else {
                                $badge = '<span class="badge text-bg-success">Live</span>';
                            }
                            echo('<tr>'
                                . '<td>' . e($row['title']) . '</td>'
                                . '<td class="d-none d-md-table-cell"><code>' . e($row['slug']) . '</code></td>'
                                . '<td>' . $badge . '</td>'
                                . '<td class="d-none d-lg-table-cell">' . e(date('M j, Y', strtotime($row['startDateTime']))) . '</td>'
                                . '<td class="text-end text-nowrap">'
                                . '<a class="btn btn-sm btn-primary" href="blogEdit.php?id=' . (int)$row['id'] . '" title="Edit"><i class="fa-solid fa-pen"></i></a> '
                                . '<a class="btn btn-sm btn-outline-secondary" href="/blog/' . rawurlencode($row['slug']) . '" target="_blank" title="Preview"><i class="fa-solid fa-up-right-from-square"></i></a> '
                                . '<form method="post" action="blog.php" class="d-inline" onsubmit="return confirm(\'Delete this post? This cannot be undone.\');">'
                                . '<input type="hidden" name="action" value="delete">'
                                . '<input type="hidden" name="id" value="' . (int)$row['id'] . '">'
                                . '<input type="hidden" name="csrf" value="' . $csrfToken . '">'
                                . '<button class="btn btn-sm btn-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>'
                                . '</form>'
                                . '</td></tr>');
                        }
                        if ($posts->num_rows === 0) {
                            echo('<tr><td colspan="5" class="text-body-secondary p-3">No posts yet. Click <strong>New Post</strong> to write one.</td></tr>');
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
