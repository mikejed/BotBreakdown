<?php
// Inline attachment manager, embedded on the blog/update editor pages so files
// can be uploaded and organized without leaving the entry (unsaved content is
// never lost - everything here is AJAX). Requires _editorAssets.php to have been
// included first (it defines BB_CSRF and the .bot-editor Summernote instance).
//
// Browses public/blog/attachments/ via attachment.php. "Insert" drops an <img>
// (images) or a link (other files) into the editor at the cursor.
?>
<div class="card mt-4">
    <div class="card-header">
        <button class="btn btn-link text-decoration-none p-0" type="button" data-bs-toggle="collapse" data-bs-target="#attachmentBody" aria-expanded="true">
            <i class="fa-solid fa-paperclip"></i> Attachments
        </button>
    </div>
    <div id="attachmentBody" class="collapse show">
        <div class="card-body">
            <input type="hidden" id="attachmentDir" value="">
            <nav aria-label="folder path"><ol id="bbCrumb" class="breadcrumb mb-2"></ol></nav>

            <div class="row g-2 align-items-end mb-3">
                <div class="col-sm">
                    <label class="form-label mb-0" for="bbUploadInput">Upload file</label>
                    <input type="file" id="bbUploadInput" class="form-control form-control-sm">
                </div>
                <div class="col-sm-auto">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bbMkdir();"><i class="fa-solid fa-folder-plus"></i> New folder</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bbRefreshAttachments();"><i class="fa-solid fa-arrows-rotate"></i></button>
                </div>
            </div>
            <div id="bbUploadStatus" class="small text-body-secondary mb-2"></div>

            <div id="bbList" class="row g-2"></div>
        </div>
    </div>
</div>

<script>
    var bbCurDir = '';
    var bbAllowedNote = 'png, jpg, jpeg, gif, webp, svg, pdf, csv, zip';

    function bbEditorReady() {
        return $('.bot-editor').length > 0 && $('.note-editor').length > 0;
    }

    function bbInsert(url, isImage, name) {
        if (bbEditorReady()) {
            var $ed = $('.bot-editor').first();
            if (isImage) {
                $ed.summernote('insertImage', url, function ($img) { $img.addClass('img-fluid mb-4'); });
            } else {
                $ed.summernote('createLink', { text: name, url: url, isNewWindow: false });
            }
        } else {
            bbCopy(url);
        }
    }

    function bbCopy(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text);
            $('#bbUploadStatus').text('Copied path: ' + text);
        } else {
            window.prompt('Copy this path:', text);
        }
    }

    function bbEsc(s) {
        return $('<div>').text(s).html();
    }

    function bbLoadAttachments(dir) {
        $.getJSON('/admin/attachment.php', { action: 'list', dir: dir })
            .done(function (resp) {
                if (!resp.ok) { $('#bbUploadStatus').text(resp.error || 'Could not list folder.'); return; }
                bbCurDir = resp.dir;
                $('#attachmentDir').val(bbCurDir);

                // breadcrumb
                var crumb = '<li class="breadcrumb-item"><a href="#" onclick="bbLoadAttachments(\'\');return false;">attachments</a></li>';
                if (resp.dir !== '') {
                    var parts = resp.dir.split('/'), acc = '';
                    for (var i = 0; i < parts.length; i++) {
                        acc += (i ? '/' : '') + parts[i];
                        var last = (i === parts.length - 1);
                        crumb += last
                            ? '<li class="breadcrumb-item active">' + bbEsc(parts[i]) + '</li>'
                            : '<li class="breadcrumb-item"><a href="#" onclick="bbLoadAttachments(\'' + bbEsc(acc) + '\');return false;">' + bbEsc(parts[i]) + '</a></li>';
                    }
                }
                $('#bbCrumb').html(crumb);

                var html = '';
                if (resp.parent !== null) {
                    html += '<div class="col-6 col-md-3"><button type="button" class="btn btn-outline-secondary w-100 text-start" onclick="bbLoadAttachments(\'' + bbEsc(resp.parent) + '\');"><i class="fa-solid fa-turn-up"></i> ..</button></div>';
                }
                resp.folders.forEach(function (f) {
                    var p = (resp.dir ? resp.dir + '/' : '') + f.name;
                    html += '<div class="col-6 col-md-3"><div class="btn-group w-100">'
                        + '<button type="button" class="btn btn-outline-primary text-start text-truncate" onclick="bbLoadAttachments(\'' + bbEsc(p) + '\');"><i class="fa-solid fa-folder"></i> ' + bbEsc(f.name) + '</button>'
                        + '<button type="button" class="btn btn-outline-danger flex-grow-0" title="Delete empty folder" onclick="bbDelete(\'' + bbEsc(f.name) + '\',true);"><i class="fa-solid fa-trash"></i></button>'
                        + '</div></div>';
                });
                resp.files.forEach(function (fl) {
                    var thumb = fl.isImage
                        ? '<img src="' + fl.url + '" alt="" style="max-height:80px;max-width:100%;object-fit:contain;">'
                        : '<i class="fa-solid fa-file fa-3x text-body-secondary"></i>';
                    html += '<div class="col-6 col-md-3"><div class="border rounded p-2 h-100 d-flex flex-column">'
                        + '<div class="text-center mb-1" style="min-height:80px;">' + thumb + '</div>'
                        + '<div class="small text-truncate" title="' + bbEsc(fl.name) + '">' + bbEsc(fl.name) + '</div>'
                        + '<div class="small text-body-secondary mb-1">' + Math.max(1, Math.round(fl.size / 1024)) + ' KB</div>'
                        + '<div class="btn-group btn-group-sm mt-auto">'
                        + '<button type="button" class="btn btn-primary" title="Insert into article" onclick="bbInsert(\'' + fl.url + '\',' + (fl.isImage ? 'true' : 'false') + ',\'' + bbEsc(fl.name) + '\');"><i class="fa-solid fa-arrow-left"></i></button>'
                        + '<button type="button" class="btn btn-outline-secondary" title="Copy path" onclick="bbCopy(\'' + fl.url + '\');"><i class="fa-solid fa-link"></i></button>'
                        + '<button type="button" class="btn btn-outline-secondary" title="Rename" onclick="bbRename(\'' + bbEsc(fl.name) + '\');"><i class="fa-solid fa-pen"></i></button>'
                        + '<button type="button" class="btn btn-outline-danger" title="Delete" onclick="bbDelete(\'' + bbEsc(fl.name) + '\',false);"><i class="fa-solid fa-trash"></i></button>'
                        + '</div></div></div>';
                });
                if (resp.folders.length === 0 && resp.files.length === 0) {
                    html += '<div class="col-12 text-body-secondary small">This folder is empty.</div>';
                }
                $('#bbList').html(html);
            })
            .fail(function () { $('#bbUploadStatus').text('Could not reach the attachment service.'); });
    }

    function bbRefreshAttachments() { bbLoadAttachments(bbCurDir); }

    function bbPost(params, done) {
        params.csrf = BB_CSRF;
        $.post('/admin/attachment.php', params, null, 'json')
            .done(done)
            .fail(function (xhr) {
                var msg = 'Action failed.';
                try { msg = JSON.parse(xhr.responseText).error || msg; } catch (e) {}
                $('#bbUploadStatus').text(msg);
            });
    }

    function bbMkdir() {
        var name = window.prompt('New folder name:');
        if (!name) { return; }
        bbPost({ action: 'mkdir', dir: bbCurDir, name: name }, function (r) {
            if (r.ok) { bbRefreshAttachments(); } else { $('#bbUploadStatus').text(r.error); }
        });
    }

    function bbRename(oldName) {
        var newName = window.prompt('Rename to:', oldName);
        if (!newName || newName === oldName) { return; }
        bbPost({ action: 'rename', dir: bbCurDir, oldName: oldName, newName: newName }, function (r) {
            if (r.ok) { bbRefreshAttachments(); } else { $('#bbUploadStatus').text(r.error); }
        });
    }

    function bbDelete(name, isFolder) {
        if (!window.confirm('Delete "' + name + '"' + (isFolder ? ' (must be empty)' : '') + '?')) { return; }
        bbPost({ action: 'delete', dir: bbCurDir, name: name }, function (r) {
            if (r.ok) { bbRefreshAttachments(); } else { $('#bbUploadStatus').text(r.error); }
        });
    }

    function bbUpload(file, overwrite) {
        var data = new FormData();
        data.append('file', file);
        data.append('csrf', BB_CSRF);
        data.append('dir', bbCurDir);
        if (overwrite) { data.append('overwrite', '1'); }
        $('#bbUploadStatus').text('Uploading ' + file.name + '...');
        $.ajax({
            url: '/admin/attachment.php?action=upload',
            method: 'POST', data: data, contentType: false, processData: false, dataType: 'json'
        }).done(function (r) {
            if (r.ok) {
                $('#bbUploadStatus').text('Uploaded ' + r.name);
                bbRefreshAttachments();
            } else if (r.exists) {
                if (window.confirm(r.error + ' Overwrite?')) { bbUpload(file, true); }
                else { $('#bbUploadStatus').text('Upload cancelled.'); }
            } else {
                $('#bbUploadStatus').text(r.error || 'Upload failed.');
            }
        }).fail(function (xhr) {
            var msg = 'Upload failed.';
            try { msg = JSON.parse(xhr.responseText).error || msg; } catch (e) {}
            $('#bbUploadStatus').text(msg);
        });
    }

    $(function () {
        $('#bbUploadInput').on('change', function () {
            if (this.files && this.files[0]) { bbUpload(this.files[0], false); this.value = ''; }
        });
        bbLoadAttachments('');
    });
</script>
