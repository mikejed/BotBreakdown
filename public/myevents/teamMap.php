<?php
// $authRequired = true;
include '../_dbConnection.php';

if (!isset($allowCache) || $allowCache != true) {
    header('Expires: Thu, 1 Jan 1970 00:00:00 GMT');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0',false);
    header('Pragma: no-cache');
}

// log a page view.
logHistory("mapView", $_SERVER['REQUEST_URI']);

header('Content-type: image/svg');

$firstEventCode = substr($_REQUEST["event"],4);

// Now set up the queries that we'll use to get the autonomous map data.
$teamAutoMapGet = $db->prepare("SELECT
                                    tm.`match`
                                    , sub.`id`
                                    , scout.`displayName`
                                    , map.`dataText` 'map'
                                    , alliance.`dataText` 'alliance'
                                    , (SELECT count(1) FROM `flag` WHERE `submissionId` = sub.`id`) 'flagCount'
                                FROM
                                    `teamMatch` tm
                                    INNER JOIN `submission` sub ON sub.`teamMatchId` = tm.`id`
                                    INNER JOIN `submissionData` map ON map.`submissionId` = sub.`id`
                                    INNER JOIN `dataPoint` mapField ON
                                        mapField.`id` = map.`dataPointId`
                                        AND mapField.`dataKey` = 'coordinates'
                                    INNER JOIN `submissionData` alliance ON alliance.`submissionId` = sub.`id`
                                    INNER JOIN `dataPoint` allianceField ON 
                                        allianceField.`id` = alliance.`dataPointId`
                                        AND allianceField.`dataKey` = 'alliance'
                                    LEFT OUTER JOIN `scouter` scout ON scout.`id` = sub.`scouterId`
                                    LEFT OUTER JOIN `flag` f ON f.`submissionId` = sub.`id`
                                WHERE
                                    tm.`event` = ?
                                    AND tm.`level` = 'qm'
                                    AND tm.`teamNumber` = ?
                                    AND map.`dataText` <> ''
                                    AND alliance.`dataText` <> ''
                                ORDER BY
                                    tm.`match`
                                    , scout.`id`
                                ;");


$teamAutoMapGet->bind_param("si", $_GET['event'], $_GET['team']);
$teamAutoMapGet->execute();
$teamAutoMapGetResult = $teamAutoMapGet->get_result();
$teamAutoMapGetResultData = $teamAutoMapGetResult->fetch_all(MYSQLI_ASSOC);

?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 85" width="500" height="426">
    <rect x="0" width="105" height="85" style="fill:none;stroke:#999;stroke-width:1"></rect>
    <?php
        foreach ($teamAutoMapGetResultData as $path) {
            if ($path['alliance'] == "Blue") {
                $shown = '';
                if ($path['flagCount'] >= $flagThreshold) {
                    $shown = 'display:none;';
                }
                echo('<path id="' . $path['id'] . '" d="M' . $path['map'] . '" style="fill:none;stroke:#000;stroke-width:1;' . $shown . '"></path>');
            }
        }
    ?>
</svg>