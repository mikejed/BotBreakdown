<?php
$authRequired = true;
$pageTitle = "View " . $_GET["event"] . " Submissions";
include '../_dbConnection.php';
include '../_head.php';

// Get the event name
$eventName = "";
$eventNameQuery = $db->prepare("SELECT `eventName` FROM `eventData` WHERE `event` = ?");
$eventNameQuery->bind_param("s", $_GET["event"]);
$eventNameQuery->execute();
$eventNameResult = $eventNameQuery->get_result();
if($eventNameResult->num_rows) {
    $eventNameData = $eventNameResult->fetch_all(MYSQLI_ASSOC);
    $eventName = $eventNameData[0]["eventName"];
}

if(strlen($eventName) < 1) {
    $eventRequest = curl_init("https://frc-api.firstinspires.org/v3.0/$currentSeason/events?eventCode=" . $firstEventCode);
    curl_setopt_array($eventRequest, $firstCurlOpt);
    $eventContent = json_decode(curl_exec($eventRequest),true);
    if (isset($eventContent['Events']) && is_array($eventContent['Events'])) {
        $eventName = $eventContent['Events'][0]['name'];
    }

    if(strlen($eventName) > 0) {
        $eventData = $db->prepare("INSERT INTO `eventData` (`event`,`eventName`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `eventName` = ?");
        $eventData->bind_param("sss",$_GET["event"],$eventName,$eventName);
        $eventData->execute();
    } else {
        $eventName = $_GET["event"];
    }
}

echo("<div class=\"container mb-3\">
        <h1>Submissions for " . e($eventName) . "</h1>
        <a href=\"viewData.php?event=" . rawurlencode($_GET["event"]) . "\" class=\"btn btn-outline-secondary\"><i class=\"fa-solid fa-left-long\"></i> Back to event</a>
        <hr>
    ");

if (isset($_GET['event'])) {
    if (isset($_GET["allData"]) && $_GET["allData"] == "true") {
        $showAllData = "";
        echo("<a href=\"" . e(str_replace("&allData=true", "", $_SERVER['REQUEST_URI'])) . "\" class=\"btn btn-outline-warning\">Show condensed table</a>");
    } else {
        $showAllData = "AND dp.`dataSet` = 'minimum'";
        echo("<a href=\"" . e($_SERVER['REQUEST_URI']) . "&allData=true\" class=\"btn btn-outline-warning\">Show all fields</a>");
    }
    echo("<a href=\"getRawSubmissions.php?event=" . rawurlencode($_GET["event"]) . "\" class=\"float-end btn btn-primary\"><i class=\"fa-solid fa-file-csv\"></i> Export Submissions</a>
        </div>");

    $dataPointGet = $db->prepare("SELECT dp.`id`, dp.`name`, dp.`dataType`, dp.`dataSet` FROM `dataPoint` dp WHERE `season` = ? $showAllData");
    $dataPointGet->bind_param("i",substr($_GET['event'],0,4));
    $dataPointGet->execute();
    $dataPointGetResult = $dataPointGet->get_result();
    $dataPointGetResultData = $dataPointGetResult->fetch_all(MYSQLI_ASSOC);

    if ($dataPointGetResult->num_rows > 0) {
        echo("<div class=\"container\"><div class=\"table-responsive\" style=\"clear:both;\"><table class=\"table table-striped table-hover\"><thead><tr>");

        $dataPointColumns = "";
        $joinColumns = "";
        echo "<th>Scouter</th><th>Match Number</th><th>Team Number</th>";
        foreach ($dataPointGetResultData as $dataItem) {
            if (strlen($dataPointColumns) > 0) {
                $dataPointColumns .= ", ";
                $joinColumns .= "\n";
            }
            echo ("<th>" . e($dataItem["name"]) . "</th>");
            if ($dataItem["dataType"] == "text") {
                $dataPointColumns .= "data" . $dataItem["id"] . ".`dataText` AS `" . $dataItem["name"] . "`\n";
            } else {
                $dataPointColumns .= "data" . $dataItem["id"] . ".`dataValue` AS `" . $dataItem["name"] . "`\n";
            }
            
            $joinColumns .= "LEFT OUTER JOIN `submissionData` data" . $dataItem["id"] . " ON data" . $dataItem["id"] . ".`dataPointId` = " . $dataItem["id"] . " AND sub.`id` = data" . $dataItem["id"] . ".`submissionId`";
            
        }
        echo "<th>Actions</th><tr></thead><tbody>";

        if ( isset($_GET["match"]) && isset($_GET["team"]) ) {
            // both match and team are set; show only submissions for this teamMatch
            $matchGet = $db->prepare("SELECT
                                        s.`displayName` AS `scouter`
                                        , sub.`scouterId`
                                        , tm.`match`
                                        , tm.`teamNumber` AS `teamNumberId`
                                        , sub.`id` AS `submissionId`
                                        , (SELECT count(1) FROM `flag` WHERE `submissionId` = sub.`id` AND `reportingScouterId` = $currentPersonId) AS `previouslyFlagged`
                                        , $dataPointColumns
                                    FROM
                                        `submission` sub
                                        INNER JOIN `teamMatch` tm ON sub.`teamMatchId` = tm.`id`
                                        $joinColumns
                                        LEFT OUTER JOIN `scouter` s ON sub.`scouterId` = s.`id`
                                    WHERE
                                        tm.`event` = ?
                                        AND tm.`match` = ?
                                        AND tm.`teamNumber` = ?
                                        AND (SELECT count(1) FROM `flag` WHERE `submissionId` = sub.`id` AND `reportingScouterId` <> $currentPersonId) < $flagThreshold
                                    GROUP BY
                                        tm.`id`
                                        , tm.`match`
                                        , tm.`teamNumber`
                                        , sub.`scouterId`
                                        , sub.`id`
                                        , s.`displayName`
                                    ORDER BY
                                        tm.`match`
                                        , tm.`teamNumber`
                                    ");
            $matchGet->bind_param("sis", $_GET["event"], $_GET["match"], $_GET["team"]);

        } else if ( isset($_GET["match"]) ) {
            // match is set but not team; show all submissions for the match
            // note: this is supported but undocumented and unlinked.
            $matchGet = $db->prepare("SELECT
                                        s.`displayName` AS `scouter`
                                        , sub.`scouterId`
                                        , tm.`match`
                                        , tm.`teamNumber` AS `teamNumberId`
                                        , sub.`id` AS `submissionId`
                                        , (SELECT count(1) FROM `flag` WHERE `submissionId` = sub.`id` AND `reportingScouterId` = $currentPersonId) AS `previouslyFlagged`
                                        , $dataPointColumns
                                    FROM
                                        `submission` sub
                                        INNER JOIN `teamMatch` tm ON sub.`teamMatchId` = tm.`id`
                                        $joinColumns
                                        LEFT OUTER JOIN `scouter` s ON sub.`scouterId` = s.`id`
                                    WHERE
                                        tm.`event` = ?
                                        AND tm.`match` = ?
                                        AND (SELECT count(1) FROM `flag` WHERE `submissionId` = sub.`id` AND `reportingScouterId` <> $currentPersonId) < $flagThreshold
                                    GROUP BY
                                        tm.`id`
                                        , tm.`match`
                                        , tm.`teamNumber`
                                        , sub.`scouterId`
                                        , sub.`id`
                                        , s.`displayName`
                                    ORDER BY
                                        tm.`match`
                                        , tm.`teamNumber`
                                    ");
            $matchGet->bind_param("si", $_GET["event"], $_GET["match"]);
        } else {
            // match is not specified; show all submissions for the entire event
            $matchGet = $db->prepare("SELECT
                                        s.`displayName` AS `scouter`
                                        , sub.`scouterId`
                                        , tm.`match`
                                        , tm.`teamNumber` AS `teamNumberId`
                                        , sub.`id` AS `submissionId`
                                        , (SELECT count(1) FROM `flag` WHERE `submissionId` = sub.`id` AND `reportingScouterId` = $currentPersonId) AS `previouslyFlagged`
                                        , $dataPointColumns
                                    FROM
                                        `submission` sub
                                        INNER JOIN `teamMatch` tm ON sub.`teamMatchId` = tm.`id`
                                        $joinColumns
                                        LEFT OUTER JOIN `scouter` s ON sub.`scouterId` = s.`id`
                                    WHERE
                                        tm.`event` = ?
                                        AND (SELECT count(1) FROM `flag` WHERE `submissionId` = sub.`id` AND `reportingScouterId` <> $currentPersonId) < $flagThreshold
                                        AND tm.`match` > 0
                                    GROUP BY
                                        tm.`id`
                                        , tm.`match`
                                        , tm.`teamNumber`
                                        , sub.`scouterId`
                                        , sub.`id`
                                        , s.`displayName`
                                    ORDER BY
                                        tm.`match`
                                        , tm.`teamNumber`
                                    ");
            $matchGet->bind_param("s", $_GET["event"]);
        }
        $matchGet->execute();
        $matchGetResult = $matchGet->get_result();
        $matchGetResultData = $matchGetResult->fetch_all(MYSQLI_ASSOC);
        foreach ($matchGetResultData as $row) {
            $submissionId = $row["submissionId"];
            $scouterId = $row["scouterId"];
            
            if ($row["previouslyFlagged"] == 0) {
                    $previouslyFlagged = false;
            } else {
                    $previouslyFlagged = true;
            }
            
            unset($row["submissionId"]);
            unset($row["scouterId"]);
            unset($row["previouslyFlagged"]);
            $safeCells = array_map(function($value) { return nl2br(e($value)); }, $row);
            echo ("<tr><td>" . implode("</td><td>", $safeCells) . "</td><td>");
            
            
            if ($scouterId == $currentPersonId) {
                // Someday maybe an edit button will go here
            } else if ($previouslyFlagged == true) {
                echo("<a class=\"btn btn-success btn-sm\" href=\"unflagSubmission.php?submissionId=$submissionId\" title=\"Unreport Submission\"><i class=\"fa-solid fa-bell-slash\"></i></a>");
            } else {
                echo("<a class=\"btn btn-danger btn-sm\" href=\"flagSubmission.php?submissionId=$submissionId\" title=\"Report submission\"><i class=\"fa-solid fa-bell\"></i></a>");
            }
            

            echo("</td></tr>");
        }
        
        echo "</tbody></table></div></div>";
    }
}


include '../_footer.php';
?>