<?php

include '../_dbConnection.php';
include '../_head.php';
$slug = isset($_GET['request']) ? $_GET['request'] : '';


// ------------- Query preparations ---------------
// Get the most recent (10) posts for display on the main index page.
$blogIndex = $db->prepare("SELECT
                            *
                        FROM
                            `blog`
                        WHERE
                            `startDateTime` < NOW()
                            AND (
                                `expireDateTime` > NOW()
                                OR `expireDateTime` IS NULL
                            )
                        ORDER BY
                            `startDateTime` DESC
                            , `id` DESC
                        LIMIT 10
                    ");
$blogIndex->execute();
$blogIndexResult = $blogIndex->get_result();
$blogIndexResultData = $blogIndexResult->fetch_all(MYSQLI_ASSOC);

// The sidebar shows EVERY published post grouped by year, so it uses its own
// query without the 10 post LIMIT that the main index uses.
// It selects only the columns the sidebar needs (no content longtext).
$sidebarIndex = $db->prepare("SELECT
                                `id`
                                , `slug`
                                , `title`
                                , `startDateTime`
                            FROM
                                `blog`
                            WHERE
                                `startDateTime` < NOW()
                                AND (
                                    `expireDateTime` > NOW()
                                    OR `expireDateTime` IS NULL
                                )
                            ORDER BY `startDateTime` DESC, `id` DESC
                        ");
$sidebarIndex->execute();
$sidebarPosts = $sidebarIndex->get_result()->fetch_all(MYSQLI_ASSOC);

// ------------- BEGIN PAGE BODY ---------------

echo('<div class="container">');
$showIndex = 1;
if (isset($slug) && $slug != "") {
    // specific post requested
    $showIndex = 0;

    $blogPost = $db->prepare("SELECT
                                *
                            FROM
                                `blog`
                            WHERE
                                `slug` = ?
                            ORDER BY `id` ASC
                            LIMIT 1
                        ");
    $blogPost->bind_param("s", $slug);
    $blogPost->execute();
    $blogPostResult = $blogPost->get_result();
    if ($blogPostResult->num_rows > 0) {
        $blogPostResultData = $blogPostResult->fetch_all(MYSQLI_ASSOC)[0];

        echo('<div class="row"><h1>' . $blogPostResultData['title'] . '</h1></div><hr><div class="row"><div class="col-md-10">');

        echo('  <div class="card mb-3">
                    <div class="card-header">
                        <p class="card-text blog-post-meta">
                            <span><strong>Posted on:</strong> ' . date('M j, Y', strtotime($blogPostResultData["startDateTime"])) . '</span> |
                            <span><strong>Author:</strong> ' . $blogPostResultData["author"] . '</span>
                        </p>
                    </div>
                    <div class="card-body blog-body">' . $blogPostResultData["content"] . '</div>
                </div>
            ');
        ?>
        <!-- Modal Structure -->
        <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="--bs-modal-width: 98.5%;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="imageModalLabel">Image Detail</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img id="modalImage" class="img-fluid" src="" alt="Image Detail">
                    </div>
                </div>
            </div>
        </div>
        <script>
        $(document).ready(function() {
            $('.img-fluid').on('click', function() {
                // Get the source of the clicked image
                var imgSrc = $(this).attr('src');

                // Set the source of the modal image to the clicked image's source
                $('#modalImage').attr('src', imgSrc);

                // Show the modal
                $('#imageModal').modal('show');
            });
        });
        </script>
        <?php
    } else {
        $showIndex = 1;
    }
}

if ($showIndex == 1) {
    // index requested
    echo('<div class="row">
            <h1>Blog</h1>
        </div>
        <hr>
        <div class="row">
            <div class="col-md-10">
    ');

    foreach ($blogIndexResultData as $item) {
        $summary = nl2br(strip_tags($item["content"]));
        if (strlen($summary) > 300) {
            $summary = substr($summary,0,300);
            $pos = strrpos($summary, ' ');
            if ($pos !== false && $pos > 200) { // If there is no space in the last 100 chars, just truncate
                $summary = substr($summary,0,$pos);
            }
        }

        echo('  <div class="card mb-3">
                    <div class="card-header">
                        <a href="' . rawurlencode($item["slug"]) . '"><h4 class="card-title post-title">' . $item["title"] . '</h4></a>
                        <p class="card-text blog-post-meta">
                            <span><strong>Posted on:</strong> ' . date('M j, Y', strtotime($item["startDateTime"])) . '</span> |
                            <span><strong>Author:</strong> ' . $item["author"] . '</span>
                        </p>
                    </div>
                    <div class="card-body">
                        <p>' . str_replace('<br />', '</p><p>', $summary) . '...</p>
                    </div>
                </div>
            ');

    }
}


echo('          </div><!-- .col-md-10 -->
                <div class="col-md-2" style="border-left:1px solid gray; font-size: 0.8rem;">
                    <strong style="font-size: 1rem;">Posts</strong>'
    );

// ------------- Sidebar: list of posts by year ---------------

// Group by publish year. The query is startDateTime DESC, so years are
// encountered newest-first and stay in that order.
$postsByYear = [];
foreach ($sidebarPosts as $item) {
    $postsByYear[date('Y', strtotime($item['startDateTime']))][] = $item;
}

// Which year is expanded by default: the open article's year if it's in the
// list, otherwise the latest (top) year.
$expandYear = $postsByYear ? (string)array_key_first($postsByYear) : '';
if ($showIndex == 0 && isset($blogPostResultData)) {
    $articleYear = date('Y', strtotime($blogPostResultData['startDateTime']));
    if (isset($postsByYear[$articleYear])) {
        $expandYear = $articleYear;
    }
}

echo('<style>.year-caret{transition:transform .15s ease;}.year-toggle:not(.collapsed) .year-caret{transform:rotate(90deg);}</style>');
echo('<div id="postYears">');

foreach ($postsByYear as $postYear => $yearPosts) {
    $isOpen = ((string)$postYear === $expandYear);
    $cid = 'year-' . $postYear;
    echo('<div class="mb-1">'
        . '<button class="btn btn-link btn-sm p-0 text-decoration-none year-toggle' . ($isOpen ? '' : ' collapsed') . '" type="button" data-bs-toggle="collapse" data-bs-target="#' . $cid . '" aria-expanded="' . ($isOpen ? 'true' : 'false') . '" aria-controls="' . $cid . '">'
        . '<i class="fa-solid fa-chevron-right fa-fw year-caret"></i><strong>' . $postYear . '</strong>'
        . '</button>'
        . '<div class="collapse' . ($isOpen ? ' show' : '') . '" id="' . $cid . '"><ul class="ps-3 mb-2">');
    foreach ($yearPosts as $item) {
        echo('<li>' . date('M j', strtotime($item["startDateTime"])) . ' <a href="' . rawurlencode($item['slug']) . '">' . $item["title"] . '</a></li>');
    }
    echo('</ul></div></div>');
}

echo('</div>');

echo('          </div><!-- /.col-md-2 -->
            </div><!-- /.row -->
        </div><!-- /.container -->'
    );
include '../_footer.php'
?>