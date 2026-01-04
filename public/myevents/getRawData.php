<?php
$authRequired = true;
include '../_dbConnection.php';

header('Expires: Thu, 1 Jan 1970 00:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0',false);
header('Pragma: no-cache');

$firstEventCode = substr($_GET["event"],4);

logHistory("download", $_GET["event"]);

header('Content-type: text/csv');
header('Content-Disposition: attachment; filename="' . $_GET["event"] . '-data.csv"');

// set up request for event results, in case we need it. Don't execute it yet though- we only do that once per page and only IF a match is found without this data already set.
$matchRequest = curl_init("https://frc-api.firstinspires.org/v3.0/$currentSeason/schedule/" . $firstEventCode . "?tournamentLevel=Qualification");
curl_setopt_array($matchRequest, $firstCurlOpt);

// OK let's start getting the data from the database that we want to show.
if (isset($_GET['event'])) {

    $dataPointGet = $db->prepare("SELECT `id`,`name`,`dataType`,`dataSet` FROM `dataPoint` WHERE `season` = ?");
    $dataPointGet->bind_param("i",substr($_GET['event'],0,4));
    $dataPointGet->execute();
    $dataPointGetResult = $dataPointGet->get_result();
    $dataPointGetResultData = $dataPointGetResult->fetch_all(MYSQLI_ASSOC);

    if ($dataPointGetResult->num_rows > 0) {

        $dataPointColumns = "";
        $joinColumns = "";
        echo ("\"Tournament Level\",\"Match Number\",\"Team Number\",\"");
        foreach ($dataPointGetResultData as $dataItem) {
            if (strlen($dataPointColumns) > 0) {
                $dataPointColumns .= ", ";
                $joinColumns .= "\n";
                echo ("\",\"");
            }

            // $dataPointColumns .= "data" . $dataItem["id"] . ".`dataValue` AS `" . $dataItem["name"] . "`";
            if ($dataItem["dataType"] == "text") {
                // There's no way to "average" text. Decision: let "yes" values override "no" values. So get distinct text that's not equal to "no" and if there's nothing left THEN show "no".
                echo ($dataItem["name"]);
                $dataPointColumns .= "(
                                        SELECT IFNULL( GROUP_CONCAT(DISTINCT `dataText` SEPARATOR '\n\n'), 'No')
                                        FROM
                                            `submissionData` sd
                                            INNER JOIN `dataPoint` dp ON sd.`dataPointId` = dp.`id` AND sd.`dataText` <> 'No'
                                            INNER JOIN `submission` s ON sd.`submissionId` = s.`id`
                                        WHERE
                                            s.`teamMatchId` = tm.`id`
                                            AND sd.dataPointId = " . $dataItem["id"] . "
                                            AND (SELECT count(1) FROM `flag` WHERE `submissionId` = s.`id`) < $flagThreshold
                                    ) AS `" . $dataItem["name"] . "`\n";
            } else {
                echo ($dataItem["name"] . "Min\",\"" . $dataItem["name"] . "Avg\",\"" . $dataItem["name"] . "Max");
                $dataPointColumns .= "(
                                        SELECT MIN(`dataValue`)
                                        FROM
                                            `submissionData` sd
                                            INNER JOIN `dataPoint` dp ON sd.`dataPointId` = dp.`id`
                                            INNER JOIN `submission` s ON sd.`submissionId` = s.`id`
                                        WHERE
                                            s.`teamMatchId` = tm.`id`
                                            AND dataPointId = " . $dataItem["id"] . "
                                            AND (SELECT count(1) FROM `flag` WHERE `submissionId` = s.`id`) < $flagThreshold
                                    ) AS `" . $dataItem["name"] . "Min`\n
                                    , (
                                        SELECT ROUND(AVG(`dataValue`),0)
                                        FROM
                                            `submissionData` sd
                                            INNER JOIN `dataPoint` dp ON sd.`dataPointId` = dp.`id`
                                            INNER JOIN `submission` s ON sd.`submissionId` = s.`id`
                                        WHERE
                                            s.`teamMatchId` = tm.`id`
                                            AND dataPointId = " . $dataItem["id"] . "
                                            AND (SELECT count(1) FROM `flag` WHERE `submissionId` = s.`id`) < $flagThreshold
                                    ) AS `" . $dataItem["name"] . "Avg`\n
                                        , (
                                        SELECT MAX(`dataValue`)
                                        FROM
                                            `submissionData` sd
                                            INNER JOIN `dataPoint` dp ON sd.`dataPointId` = dp.`id`
                                            INNER JOIN `submission` s ON sd.`submissionId` = s.`id`
                                        WHERE
                                            s.`teamMatchId` = tm.`id`
                                            AND dataPointId = " . $dataItem["id"] . "
                                            AND (SELECT count(1) FROM `flag` WHERE `submissionId` = s.`id`) < $flagThreshold
                                    ) AS `" . $dataItem["name"] . "Max`\n";
            }
            
            $joinColumns .= "RIGHT OUTER JOIN `submissionData` data" . $dataItem["id"] . " ON dp.`id` = data" . $dataItem["id"] . ".`dataPointId` AND sub.`id` = data" . $dataItem["id"] . ".`submissionId`";
        }
        echo ("\",\"alliance\",\"allianceResults\"\n");
        // echo ("<!-- $dataPointColumns -->\n");
        // echo ("<!-- $joinColumns -->\n");

        // set up the query that we'll use to update the teamMatch columns from the FIRST api.
        $matchResultsUpdate = $db->prepare("UPDATE `teamMatch` SET `alliance` = ?, `allianceResults` = ?, `allianceResultsRetrievalDateTime` = CURRENT_TIMESTAMP WHERE `id` = ?");

        $matchGet = $db->prepare("SELECT
                                    tm.`id` AS `teamMatchId`
                                    , tm.`level`
                                    , tm.`match`
                                    , tm.`teamNumber`
                                    , $dataPointColumns
                                    , tm.`alliance`
                                    , tm.`allianceResults`
                                FROM
                                    `teamMatch` tm
                                WHERE
                                    tm.`event` = ?
                                    AND tm.`match` > 0
                                ORDER BY
                                    tm.`match`
                                    , tm.`id`;
                                ");
        $matchGet->bind_param("s", $_GET['event']);
        $matchGet->execute();
        $matchGetResult = $matchGet->get_result();
        $matchGetResultData = $matchGetResult->fetch_all(MYSQLI_ASSOC);
        
        foreach ($matchGetResultData as $row) {

            // Check to see if we have the FIRST results for the team's alliance results - if not go get it and store it in the teamMatch table.
            // Determine alliance, if needed and if possible
            if (strlen($row["alliance"]) < 3) {
                if (isset($allianceContent) == false) {
                    $allianceContent = curl_exec($matchRequest);
                    $err     = curl_errno($matchRequest);
                    $errmsg  = curl_error($matchRequest);
                }
                
                $a_matches = json_decode($allianceContent, true);
                if (isset($a_matches[0]['alliances']) && is_array($a_matches[0]['alliances'])) {
                    $setAlliance = "";
                    foreach ($a_matches as $match) {
                        if ($match["key"] == $_GET["event"] . "_" . $row["level"] . $row["match"]) {
                            foreach ($match["alliances"]["red"]["team_keys"] as $team) {
                                if ($team == $row["teamNumber"]) {
                                    $setAlliance = "Red";
                                    $row["alliance"] = "Red";
                                    $setResults = json_encode($match["score_breakdown"]["red"]);
                                    break;
                                }
                            }
                            if ($setAlliance == "") {
                                foreach ($match["alliances"]["blue"]["team_keys"] as $team) {
                                    if ($team == $row["teamNumber"]) {
                                        $setAlliance = "Blue";
                                        $row["alliance"] = "Blue";
                                        $setResults = json_encode($match["score_breakdown"]["blue"]);
                                        break;
                                    }
                                }
                            }

                            // Now if we found the alliance, we have the data from FIRST. Update it into the [teamMatch] table
                            if ($setAlliance <> "") {
                                $matchResultsUpdate->bind_param("ssi", $setAlliance, $setResults, $row["teamMatchId"]);
                                $matchResultsUpdate->execute();
                            }
                            break;
                        }
                    }
                }
            }

            // Can we get back to just showing the data please?
            unset($row["teamMatchId"]);
            // $row["allianceResults"] = str_replace('"', '""', $row["allianceResults"]);

            $row = str_replace("\"", "\"\"", $row);
            echo ("\"" . implode('","', $row) . "\"\n");
        }
    }
}

?>