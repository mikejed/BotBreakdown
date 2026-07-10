<?php
$authRequired = true;
include '../_dbConnection.php';
include '../_head.php';

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

?>
<div class="container mb-5">

    <?php
        $teamAutoMapGet->bind_param("si", $_GET['event'], $_GET['team']);
        $teamAutoMapGet->execute();
        $teamAutoMapGetResult = $teamAutoMapGet->get_result();
        $teamAutoMapGetResultData = $teamAutoMapGetResult->fetch_all(MYSQLI_ASSOC);

        // for debugging :)
        /*
        echo ("<pre>");
        print_r($teamAutoMapGetResultData);
        echo ("</pre>");
        */

        echo('<h1>' . e($_GET['team']) . ' Auto Mapping</h1>');

        $lastMatch = 0;
        foreach ($teamAutoMapGetResultData as $submission) {
            if ($lastMatch <> $submission['match']) {
                if ($lastMatch <> 0) {
                    echo('</div>');
                }
                $lastMatch = $submission['match'];
                if ($submission['alliance'] == 'Blue') {
                    $rowColor='info';
                } elseif ($submission['alliance'] == 'Red') {
                    $rowColor='danger';
                }
                echo('<div class="row bg-' . $rowColor . ' bg-opacity-25 pb-3"><div class="col-sm-2"><input type="checkbox" id="match' . $submission['match'] . '" class="match-toggle" data-match="' . $submission['match'] . '" checked> <label for="match' . $submission['match'] . '">Match ' . $submission['match'] . ': </label></div>');
            }
            $checked = ' checked';
            if ($submission['flagCount'] >= $flagThreshold) {
                $checked = '';
            }
            echo('<div class="col-sm-2"><input type="checkbox" class="submission-checkbox match-' . $submission['match'] . '" id="checkbox-' . $submission['id'] . '" data-id="' . $submission['id'] . '"' . $checked . '> <label for="checkbox-' . $submission['id'] . '">' . e($submission['displayName']) . '</label></div>');
        }
        if ($lastMatch <> 0) {
            echo('</div>');
        }
        ?>

        <script>
            document.querySelectorAll('.match-toggle').forEach(toggle => {
                toggle.addEventListener('change', function() {
                    const match = this.dataset.match;
                    const checkboxes = document.querySelectorAll('.match-' + match);
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                        document.getElementById(checkbox.dataset.id).style.display = this.checked ? 'block' : 'none';
                    });
                });
            });

            document.querySelectorAll('.submission-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    document.getElementById(this.dataset.id).style.display = this.checked ? 'block' : 'none';
                });
            });

            document.querySelectorAll('.submission-checkbox').forEach(checkbox => {
                document.getElementById(checkbox.dataset.id).style.display = 'block';
            });
        </script>

    <hr class="my-5">
    <div class="row">
        <div class="col-sm">
            <svg width="500" height="318" xmlns="http://www.w3.org/2000/svg" style="background-image: url('/assets/images/<?php echo $currentSeason; ?>/blue-court-500.png'); background-size: cover;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 85" width="500" height="426">
                    <rect x="0" width="105" height="85" style="fill:none;stroke:#999;stroke-width:1"></rect>
                    <?php
                        foreach ($teamAutoMapGetResultData as $path) {
                            if ($path['alliance'] == "Blue") {
                                $shown = '';
                                if ($path['flagCount'] >= $flagThreshold) {
                                    $shown = 'display:none;';
                                }
                                echo('<path id="' . $path['id'] . '" d="M' . e($path['map']) . '" style="fill:none;stroke:#000;stroke-width:1;' . $shown . '"></path>');
                            }
                        }
                    ?>
                </svg>
            </svg>
        </div>
        <div class="col-sm text-end">
            <svg width="500" height="318" xmlns="http://www.w3.org/2000/svg" style="background-image: url('/assets/images/<?php echo $currentSeason; ?>/red-court-500.png'); background-size: cover;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 85" width="500" height="426">
                    <rect x="0" width="105" height="85" style="fill:none;stroke:#999;stroke-width:1"></rect>
                    <?php
                        foreach ($teamAutoMapGetResultData as $path) {
                            if ($path['alliance'] == "Red") {
                                $shown = '';
                                if ($path['flagCount'] >= $flagThreshold) {
                                    $shown = 'display:none;';
                                }
                                echo('<path id="' . $path['id'] . '" d="M' . e($path['map']) . '" style="fill:none;stroke:#000;stroke-width:1;' . $shown . '"></path>');
                            }
                        }
                    ?>
                </svg>
            </svg>
        </div>
    </div>
</div>

<?php
    include '../_footer.php';
?>