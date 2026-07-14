<?php
// Admin-only AJAX backend for the attachment manager and the editor's image
// upload. Dispatched by ?action=list|upload|mkdir|rename|delete. All responses
// are JSON. Every mutating action requires a POST with a valid CSRF token.
//
// Files live on disk under public/blog/attachments/ ONLY - nothing here touches
// the database except the history audit log. The whole point of this file is to
// let an admin write into a folder that the webserver also serves, so path
// safety is enforced on every operation: paths are resolved with realpath() and
// must stay inside the attachments root, and every new name is whitelisted.

include '_adminGuard.php';

header('Content-Type: application/json');

function bb_json($arr) {
    echo json_encode($arr);
    exit;
}
function bb_fail($msg, $code = 400) {
    http_response_code($code);
    bb_json(['ok' => false, 'error' => $msg]);
}

// The attachments root. Create it if a fresh deploy hasn't yet (it is gitignored).
$ATTACH_ROOT = realpath(__DIR__ . '/../blog/attachments');
if ($ATTACH_ROOT === false) {
    @mkdir(__DIR__ . '/../blog/attachments', 0755, true);
    $ATTACH_ROOT = realpath(__DIR__ . '/../blog/attachments');
}
if ($ATTACH_ROOT === false) {
    bb_fail('Attachments folder is missing and could not be created.', 500);
}

// True if $real is the root or lives underneath it.
function bb_within($real) {
    global $ATTACH_ROOT;
    return $real === $ATTACH_ROOT || strpos($real, $ATTACH_ROOT . DIRECTORY_SEPARATOR) === 0;
}

// Resolve a caller-supplied relative directory to a real path inside the root,
// or false if it escapes, contains traversal, or does not exist.
function bb_resolveDir($rel) {
    global $ATTACH_ROOT;
    $rel = trim(str_replace('\\', '/', (string)$rel), '/');
    if ($rel === '') {
        return $ATTACH_ROOT;
    }
    foreach (explode('/', $rel) as $seg) {
        if (!bb_validName($seg)) {
            return false;
        }
    }
    $real = realpath($ATTACH_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
    if ($real === false || !is_dir($real) || !bb_within($real)) {
        return false;
    }
    return $real;
}

// A single path segment (file or folder name) is allowed only if it is a plain
// name - no slashes, no dot-dot, no control or shell-significant characters.
function bb_validName($name) {
    return is_string($name)
        && $name !== '' && $name !== '.' && $name !== '..'
        && $name[0] !== '.'   // no dotfiles (protects .htaccess from rename/delete)
        && strlen($name) <= 128
        && preg_match('/^[A-Za-z0-9._ -]+$/', $name) === 1;
}

// Build the public URL for a file given its relative dir and name.
function bb_url($relDir, $name) {
    $relDir = trim(str_replace('\\', '/', (string)$relDir), '/');
    return '/blog/attachments/' . ($relDir !== '' ? rawurlencode_path($relDir) . '/' : '') . rawurlencode($name);
}
function rawurlencode_path($relDir) {
    return implode('/', array_map('rawurlencode', explode('/', $relDir)));
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$relDir = isset($_REQUEST['dir']) ? $_REQUEST['dir'] : '';

// ---- list (read-only) --------------------------------------------------------
if ($action === 'list') {
    $dir = bb_resolveDir($relDir);
    if ($dir === false) {
        bb_fail('Invalid folder.');
    }
    $relDirNorm = trim(str_replace('\\', '/', (string)$relDir), '/');
    $folders = [];
    $files = [];
    $imageExt = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];
    foreach (scandir($dir) as $entry) {
        // Skip . / .. and any dotfile (e.g. the folder's own .htaccess hardening).
        if ($entry === '' || $entry[0] === '.') {
            continue;
        }
        $full = $dir . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($full)) {
            $folders[] = ['name' => $entry];
        } else {
            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            $files[] = [
                'name'    => $entry,
                'url'     => bb_url($relDirNorm, $entry),
                'size'    => filesize($full),
                'isImage' => in_array($ext, $imageExt, true),
            ];
        }
    }
    $parent = null;
    if ($relDirNorm !== '') {
        $parts = explode('/', $relDirNorm);
        array_pop($parts);
        $parent = implode('/', $parts);
    }
    bb_json([
        'ok'      => true,
        'dir'     => $relDirNorm,
        'parent'  => $parent,
        'folders' => $folders,
        'files'   => $files,
    ]);
}

// ---- everything below mutates: POST + CSRF -----------------------------------
// Detect a POST that overran the server's size limits (then $_POST/$_FILES are
// empty) so we can explain it, before CSRF (which would otherwise 403 blindly).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)
    && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    bb_fail('Upload is too large for the server (exceeds post_max_size / upload_max_filesize).', 413);
}
requireCsrf();

// ---- upload ------------------------------------------------------------------
if ($action === 'upload') {
    $dir = bb_resolveDir($relDir);
    if ($dir === false) {
        bb_fail('Invalid folder.');
    }
    if (!isset($_FILES['file'])) {
        bb_fail('No file was received.');
    }
    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $tooBig = in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        bb_fail($tooBig ? 'Upload is too large for the server.' : 'Upload failed (error ' . $f['error'] . ').', $tooBig ? 413 : 400);
    }
    if (!is_uploaded_file($f['tmp_name'])) {
        bb_fail('Invalid upload.');
    }

    $allowed = [
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'svg'  => [], // validated by content below, not by MIME
        'pdf'  => ['application/pdf'],
        'csv'  => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
        'zip'  => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream', 'multipart/x-zip'],
    ];

    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) {
        bb_fail('That file type is not allowed. Accepted: ' . implode(', ', array_keys($allowed)) . '.');
    }

    // MIME cross-check. SVG detection via finfo is unreliable, so require the
    // file to actually contain an <svg> root element instead.
    if ($ext === 'svg') {
        $head = (string)file_get_contents($f['tmp_name'], false, null, 0, 8192);
        if (!preg_match('/<svg[\s>]/i', $head)) {
            bb_fail('That does not look like a valid SVG image.');
        }
    } else {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $f['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed[$ext], true)) {
            bb_fail('File contents (' . e($mime) . ') do not match a .' . $ext . ' file.');
        }
    }

    // Build a safe target name from the uploaded name.
    $base = preg_replace('/[^A-Za-z0-9._ -]/', '_', (string)pathinfo($f['name'], PATHINFO_FILENAME));
    $base = trim($base);
    if ($base === '') {
        $base = 'file';
    }
    $name = $base . '.' . $ext;
    if (!bb_validName($name)) {
        bb_fail('Could not derive a safe file name.');
    }

    $target = $dir . DIRECTORY_SEPARATOR . $name;
    $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] === '1';
    if (file_exists($target) && !$overwrite) {
        bb_json(['ok' => false, 'error' => 'A file named "' . $name . '" already exists here.', 'exists' => true, 'name' => $name]);
    }
    if (!move_uploaded_file($f['tmp_name'], $target)) {
        bb_fail('Could not save the file.', 500);
    }
    $relDirNorm = trim(str_replace('\\', '/', (string)$relDir), '/');
    logHistory('attachmentUpload', $relDirNorm . '/' . $name, 'attachment', null);
    bb_json(['ok' => true, 'name' => $name, 'url' => bb_url($relDirNorm, $name)]);
}

// ---- mkdir -------------------------------------------------------------------
if ($action === 'mkdir') {
    $dir = bb_resolveDir($relDir);
    if ($dir === false) {
        bb_fail('Invalid folder.');
    }
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    if (!bb_validName($name)) {
        bb_fail('Invalid folder name. Use letters, numbers, spaces, dots, dashes or underscores.');
    }
    $target = $dir . DIRECTORY_SEPARATOR . $name;
    if (file_exists($target)) {
        bb_fail('That folder already exists.');
    }
    if (!mkdir($target, 0755)) {
        bb_fail('Could not create the folder.', 500);
    }
    logHistory('attachmentMkdir', trim(str_replace('\\', '/', (string)$relDir), '/') . '/' . $name, 'attachment', null);
    bb_json(['ok' => true]);
}

// ---- rename ------------------------------------------------------------------
if ($action === 'rename') {
    $dir = bb_resolveDir($relDir);
    if ($dir === false) {
        bb_fail('Invalid folder.');
    }
    $old = isset($_POST['oldName']) ? $_POST['oldName'] : '';
    $new = isset($_POST['newName']) ? trim($_POST['newName']) : '';
    if (!bb_validName($old) || !bb_validName($new)) {
        bb_fail('Invalid name.');
    }
    $src = realpath($dir . DIRECTORY_SEPARATOR . $old);
    if ($src === false || !bb_within($src)) {
        bb_fail('That item does not exist.');
    }
    $dest = $dir . DIRECTORY_SEPARATOR . $new;
    if (file_exists($dest)) {
        bb_fail('Something named "' . $new . '" already exists here.');
    }
    if (!rename($src, $dest)) {
        bb_fail('Could not rename.', 500);
    }
    logHistory('attachmentRename', trim(str_replace('\\', '/', (string)$relDir), '/') . '/' . $old . ' -> ' . $new, 'attachment', null);
    bb_json(['ok' => true]);
}

// ---- delete ------------------------------------------------------------------
if ($action === 'delete') {
    $dir = bb_resolveDir($relDir);
    if ($dir === false) {
        bb_fail('Invalid folder.');
    }
    $name = isset($_POST['name']) ? $_POST['name'] : '';
    if (!bb_validName($name)) {
        bb_fail('Invalid name.');
    }
    $target = realpath($dir . DIRECTORY_SEPARATOR . $name);
    if ($target === false || !bb_within($target) || $target === $ATTACH_ROOT) {
        bb_fail('That item does not exist.');
    }
    if (is_dir($target)) {
        // Only remove empty folders, so a stray click can't wipe a tree.
        $remaining = array_diff(scandir($target), ['.', '..']);
        if (count($remaining) > 0) {
            bb_fail('That folder is not empty.');
        }
        if (!rmdir($target)) {
            bb_fail('Could not remove the folder.', 500);
        }
    } else {
        if (!unlink($target)) {
            bb_fail('Could not delete the file.', 500);
        }
    }
    logHistory('attachmentDelete', trim(str_replace('\\', '/', (string)$relDir), '/') . '/' . $name, 'attachment', null);
    bb_json(['ok' => true]);
}

bb_fail('Unknown action.', 404);
