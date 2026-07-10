<?php
$authRequired = true;
include '../_dbConnection.php';

logHistory("downloadSubmissions", $_GET["event"]);

header('Content-type: text/csv');
header('Content-Disposition: attachment; filename="' . $_GET["event"] . '-submissions.csv"');

// OK let's start getting the data from the database that we want to show.
if (isset($_GET['event'])) {

    $dataPointGet = $db->prepare("SELECT `id`,`name`,`dataType` FROM `dataPoint` WHERE `season` = ?");
    $season = substr($_GET['event'], 0, 4);
    $dataPointGet->bind_param("i", $season);
    $dataPointGet->execute();
    $dataPointGetResult = $dataPointGet->get_result();
    $dataPointGetResultData = $dataPointGetResult->fetch_all(MYSQLI_ASSOC);

    $dataPointColumns = "";
    
    echo('"Scouter","Match Level","Match Number","Team Number"');
    foreach ($dataPointGetResultData as $dataItem) {
        // output the CSV headers
        echo(',"' . $dataItem["name"] . '"');

        // now build the sub-queries for each of the data columns.
        if ($dataItem["dataType"] == "text") {
            $dataPointColumns .= ", (SELECT `dataText` FROM `submissionData` WHERE `dataPointId` = " . $dataItem["id"] . " AND `submissionId` = sub.`id` ORDER BY `modifiedDateTime` DESC LIMIT 1) AS `" . $dataItem["name"] . "`";
        } else {
            $dataPointColumns .= ", (SELECT `dataValue` FROM `submissionData` WHERE `dataPointId` = " . $dataItem["id"] . " AND `submissionId` = sub.`id` ORDER BY `modifiedDateTime` DESC LIMIT 1) AS `" . $dataItem["name"] . "`";
        }
    }
    echo(',"Team Alliance","Alliance Results","Flags"');
    echo("\r\n");

    $submissionGet = $db->prepare("SELECT
                                        s.`displayName` AS `Scouter`
                                        , tm.`level` AS `Match Level`
                                        , tm.`match` AS `Match Number`
                                        , tm.`teamNumber` AS `Team Number`
                                          $dataPointColumns
                                        , tm.`alliance` AS `Team Alliance`
                                        , tm.`allianceResults` AS `Alliance Results`
                                        , count(flag.`id`) AS `Flags`
                                    FROM
                                        `submission` sub
                                        INNER JOIN `teamMatch` tm ON tm.`id` = sub.`teamMatchId`
                                        LEFT OUTER JOIN `scouter` s ON s.`id` = sub.`scouterId`
                                        LEFT OUTER JOIN `flag` ON flag.`submissionId` = sub.`id`
                                    WHERE
                                        tm.`event` = ?
                                        AND tm.`match` > 0
                                    GROUP BY
                                        sub.`id`
                            ");
    $submissionGet->bind_param("s", $_GET['event']);
    $submissionGet->execute();
    $submissionGetResult = $submissionGet->get_result();
    $submissionGetResultData = $submissionGetResult->fetch_all(MYSQLI_ASSOC);

    // Write CSV rows, matching header format: enclose every field and double internal quotes
    foreach ($submissionGetResultData as $row) {
        $outRow = [];
        foreach ($row as $value) {
            // Escape quotes by doubling them, then enclose field
            $value = str_replace('"', '""', $value);
            $outRow[] = '"' . $value . '"';
        }
        echo implode(',', $outRow) . "\r\n";
    }
}

?>