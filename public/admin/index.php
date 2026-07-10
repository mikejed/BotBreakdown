<?php

include '../_dbConnection.php';

if (!$isAdmin) {
    header('Location: /', true, 302);
    exit;
}

include '../_head.php';

$db->query("SET @@cte_max_recursion_depth = 1500");
$pageViewTimeline = $db->query("
    WITH RECURSIVE minute_intervals AS (
        SELECT DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i') AS `timestamp`
        UNION ALL
        SELECT DATE_FORMAT(DATE_SUB(`timestamp`, INTERVAL 5 MINUTE), '%Y-%m-%d %H:%i')
        FROM minute_intervals
        WHERE `timestamp` > DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 DAY), '%Y-%m-%d %H:%i')
    )
    SELECT mi.`timestamp`, COUNT(h.`timestamp`) AS `count`
    FROM minute_intervals mi
    LEFT JOIN (
        SELECT DATE_FORMAT(DATE_SUB(`modifiedDateTime`, INTERVAL 5 MINUTE), '%Y-%m-%d %H:%i') AS `timestamp`
        FROM `history`
        WHERE `modifiedDateTime` > DATE_SUB(NOW(), INTERVAL 1 DAY)
        AND `operation` = 'view'
    ) h ON mi.`timestamp` = h.`timestamp`
    GROUP BY mi.`timestamp`
    ORDER BY mi.`timestamp`;
");
$pageViewTimelineData = $pageViewTimeline->fetch_all(MYSQLI_ASSOC);

$pageViews = $db->query("SELECT 
                            CASE WHEN `data` LIKE '/myevents/dataentry.php%' THEN `data` ELSE SUBSTRING_INDEX(`data`, '?', 1) END AS `data`
                            , count(1) AS `count`
                        FROM `history`
                        WHERE
                            `modifiedDateTime` > DATE_SUB(now(), INTERVAL '1' DAY)
                            AND `operation` = 'view'
                        GROUP BY CASE WHEN `data` LIKE '/myevents/dataentry.php%' THEN `data` ELSE SUBSTRING_INDEX(`data`, '?', 1) END
                        ORDER BY count(1) DESC
                        LIMIT 20");
$pageViewData = $pageViews->fetch_all(MYSQLI_ASSOC);

$dataDownloads = $db->query("SELECT 
                            `data`
                            , count(1) AS `count`
                        FROM `history`
                        WHERE
                            `modifiedDateTime` > DATE_SUB(now(), INTERVAL '1' DAY)
                            AND `operation` = 'download'
                        GROUP BY `data`
                        ORDER BY count(1) DESC
                        LIMIT 20");
$dataDownloadData = $dataDownloads->fetch_all(MYSQLI_ASSOC);

$activeScouters = $db->query("SELECT s.`displayName`, s.`id`, count(DISTINCT h.`id`) AS `count` FROM `history` h INNER JOIN `scouter` s ON s.`id` = h.`scouterId` WHERE h.`modifiedDateTime` > DATE_SUB(now(), INTERVAL '1' DAY) GROUP BY s.`id`, s.`displayName` ORDER BY count(DISTINCT h.`id`) DESC LIMIT 20");
$activeScouterData = $activeScouters->fetch_all(MYSQLI_ASSOC);

?>
<script src="/assets/js/chart.umd.min.js"></script>
<script src="/assets/js/chartjs-adapter-date-fns.js"></script>

<div class="container">
    <h1>Admin Tools</h1>
    <div class="row"><div class="col">
    <a href="errors.php" class="float-end">Error log</a>
    </div></div>
    <hr>

    <div class="row">

        <div class="col-md">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Page Views <span style="font-size: 1rem;">(24 hours by 5 minutes)</span></h3>
                </div>
                <div class="card-body">

                    <div class="container"><div class="row">
                    <canvas id="sessionChart" class="col"></canvas>
                    </div></div>

                    <script>
                    const ctx = document.getElementById('sessionChart');

                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            datasets: [{
                                label: 'Pages Viewed',
                                data: [
                                    <?php
                                    foreach ($pageViewTimelineData as $view) {
                                        echo("{ x: '" . $view["timestamp"] . "', y: '" . $view["count"] . "'},");
                                    }
                                    ?>
                                ]
                            }]
                        },
                        options: {
                            scales: {
                                x: {
                                    type: 'time',
                                    time: {
                                        unit: 'minute'
                                    },
                                    title: {
                                        display: true,
                                        text: 'Time'
                                    }
                                },
                                y: {
                                    title: {
                                        display: true,
                                        text: 'Count'
                                    },
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                    </script>
                </div>
            </div>
            <div class="card mt-4">
                <div class="card-header">
                    <h3 class="card-title">Most Downloaded Events <span style="font-size: 1rem;">(24 hours)</span></h3>
                </div>
                <div class="card-body">
                    <ol>
                        <?php
                        foreach ($dataDownloadData as $event) {
                            echo('<li>' . $event["count"] . ' download:<span class="ms-3">' . e($event["data"]) . '</span></li>');
                        }
                        ?>
                    </ol>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Most Active Scouters <span style="font-size: 1rem;">(24 hours)</span></h3>
                </div>
                <div class="card-body">
                    <ol>
                        <?php
                        foreach ($activeScouterData as $scouter) {
                            echo('<li>' . $scouter["count"] . ' interactions:<span class="ms-3">' . e($scouter["displayName"]) . '</span></li>');
                        }
                        ?>
                    </ol>
                </div>
            </div>
            <div class="card mt-4">
                <div class="card-header">
                    <h3 class="card-title">Most Loaded Pages <span style="font-size: 1rem;">(24 hours)</span></h3>
                </div>
                <div class="card-body">
                    <ol>
                        <?php
                        foreach ($pageViewData as $page) {
                            echo('<li>' . $page["count"] . ' views:<span class="ms-3">' . e($page["data"]) . '</span></li>');
                        }
                        ?>
                    </ol>
                </div>
            </div>
        </div>
    </div>

</div>

<?php
include '../_footer.php';
?>