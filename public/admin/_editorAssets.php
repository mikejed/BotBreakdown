<?php
// Rich-text editor assets (Summernote BS5) plus the shared init. Include once
// inside the <body> of an admin editor page, after _head.php.
//
// Any <textarea class="bot-editor"> becomes a WYSIWYG editor whose toolbar
// includes a Code View button for direct raw-HTML editing. Inserted/pasted images
// are uploaded to the attachments folder (see attachment.php) rather than being
// base64-inlined into the content, which would bloat the longtext column.
?>
<link href="https://cdn.jsdelivr.net/npm/summernote@0.9.1/dist/summernote-bs5.min.css" rel="stylesheet" integrity="sha384-NCIOkH1RWTLh0uk0cWmHMJbcBZE8aFTbBNELvTaRgLwGsGIgaacBRlWynjlAt69p" crossorigin="anonymous" />
<script src="https://cdn.jsdelivr.net/npm/summernote@0.9.1/dist/summernote-bs5.min.js" integrity="sha384-FycAzMryzUZA6EwTBPuugMzUKzpNjcXJhQvibi8ej/+2/imEZ6kTAsxHGtrNn8Py" crossorigin="anonymous"></script>
<script>
    var BB_CSRF = '<?php echo $csrfToken; ?>';

    $(function () {
        $('.bot-editor').summernote({
            height: 400,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'clear']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['insert', ['link', 'picture', 'video', 'table', 'hr']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ],
            callbacks: {
                onImageUpload: function (files) {
                    var editor = $(this);
                    for (var i = 0; i < files.length; i++) {
                        bbUploadEditorImage(files[i], editor);
                    }
                }
            }
        });
    });

    // Upload one image file to the attachments folder, then insert it by URL.
    function bbUploadEditorImage(file, editor) {
        var data = new FormData();
        data.append('file', file);
        data.append('csrf', BB_CSRF);
        // Upload into whatever folder the attachment panel is currently browsing.
        data.append('dir', ($('#attachmentDir').val() || ''));
        $.ajax({
            url: '/admin/attachment.php?action=upload',
            method: 'POST',
            data: data,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function (resp) {
                if (resp && resp.ok) {
                    editor.summernote('insertImage', resp.url, function ($img) { $img.addClass('img-fluid mb-4'); });
                    if (typeof bbRefreshAttachments === 'function') { bbRefreshAttachments(); }
                } else {
                    alert('Upload failed: ' + (resp && resp.error ? resp.error : 'unknown error'));
                }
            },
            error: function () { alert('Upload failed (network or server error).'); }
        });
    }
</script>
