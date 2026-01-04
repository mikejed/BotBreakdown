<?php
$authRequired = true;
$pageTitle = "View " . $_GET["event"] . " Data";
include '../_dbConnection.php';
include '../_head.php';

$firstEventCode = substr($_GET["event"],4);

echo('
<script src="/assets/js/chart.umd.min.js"></script>
<script src="/assets/js/chartjs-adapter-date-fns.js"></script>
');

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

// set up request for event results, in case we need it. Don't execute it yet though- we only do that once per page and only IF a match is found without this data already set.
$matchRequest = curl_init("https://frc-api.firstinspires.org/v3.0/$currentSeason/matches/" . $firstEventCode . "?tournamentLevel=qualification");
curl_setopt_array($matchRequest, $firstCurlOpt);

$eventRequest = curl_init("https://frc-api.firstinspires.org/v3.0/$currentSeason/events?eventCode=" . $firstEventCode);
curl_setopt_array($eventRequest, $firstCurlOpt);

// run the queries for the charts

$matchScores = $db->prepare("SELECT DISTINCT `match`, JSON_EXTRACT(`allianceResults`, \"$.scoreRedFinal\") AS `red`, JSON_EXTRACT(`allianceResults`, \"$.scoreBlueFinal\") AS `blue`
                            FROM `teamMatch`
                            WHERE `event` = ? AND `level` = 'qm' AND `match` > 0
                            GROUP BY `match`
                            ORDER BY `match`
	");
$matchScores->bind_param("s", $_GET["event"]);
$matchScores->execute();
$matchScoresResult = $matchScores->get_result();
$matchScoresData = $matchScoresResult->fetch_all(MYSQLI_ASSOC);


$matchSubmissionCount = $db->prepare("SELECT tm.`match`, count(s.`id`) AS `count`
                                FROM
                                    `teamMatch` tm
                                    LEFT OUTER JOIN `submission` s ON s.`teamMatchId` = tm.`id`
                                    LEFT OUTER JOIN `flag` f ON f.`submissionId` = s.`id`
                                WHERE
                                    tm.`event` = ?
                                    AND tm.`match` > 0
                                GROUP BY
                                    tm.`match`
                                HAVING count(f.`id`) < ?
                                ORDER BY
                                    tm.`match` ASC
                                ");
$matchSubmissionCount->bind_param("si", $_GET["event"], $flagThreshold);
$matchSubmissionCount->execute();
$matchSubmissionCountResult = $matchSubmissionCount->get_result();
$matchSubmissionCountData = $matchSubmissionCountResult->fetch_all(MYSQLI_ASSOC);


$scouterCount = $db->prepare("SELECT count(DISTINCT s.`scouterId`) `count` FROM `submission` s INNER JOIN `teamMatch` tm ON s.`teamMatchId` = tm.`id` WHERE tm.`event` = ? AND tm.`match` > 0");
$scouterCount->bind_param("s", $_GET['event']);
$scouterCount->execute();
$scouterCountResult = $scouterCount->get_result();
$scouterCountData = $scouterCountResult->fetch_all(MYSQLI_ASSOC);


$recentMatch = $db->prepare("SELECT MAX(tm.`match`) as `match` FROM `submission` s INNER JOIN `teamMatch` tm ON s.`teamMatchId` = tm.`id` WHERE tm.`event` = ? AND tm.`level` = 'qm' AND tm.`match` > 0");
$recentMatch->bind_param("s", $_GET['event']);
$recentMatch->execute();
$recentMatchResult = $recentMatch->get_result();
$recentMatchData = $recentMatchResult->fetch_all(MYSQLI_ASSOC);


// Charts and KPIs
echo('
<div class="container">
    <span class="float-end mt-4"><a href="getEventTeamList.php?event=' . $_GET["event"] . '" title="For loading team lists into analytics">Team List</a></span>
    <h1>' . $eventName . '</h1><hr>');
?>
    <div class="row">
        <div class="col-md-4 mb-4">
            <!-- bar chart of red/blue scores for each match -->
            <div class="card bg-body-secondary"><canvas id="matchScoresChart" style="height:194px;"></canvas></div>
        </div>
        <div class="col-md-4 mb-4">
            <!-- line chart showing numbers of submissions for each match -->
            <div class="card bg-body-secondary"><canvas id="sessionChart" style="height:194px;"></canvas></div>
        </div>
        <div class="col-md-4">
            <div class="row mb-4">
                <div class="col">
                    <!-- Ideas for other KPIs: data coverage for submitted team match records, average data consistency -->
                    <div class="card bg-body-secondary">
                        <table><tr>
                            <td class="bg-info-subtle text-info" width="1"><div class="p-4 text-center"><i class="fa-solid fa-users fa-2x fa-fw"></i></div></td>
                            <td class="p-3 text-info"><strong>
                                <?php
                                    echo ($scouterCountData[0]["count"] . ' scouter');
                                    if ($scouterCountData[0]["count"] !== 1) {echo ('s');}
                                    echo (' providing data');
                                ?>
                            </strong></td>
                        </tr></table>
                    </div>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col">
                    <div class="card bg-body-secondary">
                        <table><tr>
                            <td class="bg-success-subtle text-success" width="1"><div class="p-4 text-center"><i class="fa-solid fa-flag-checkered fa-2x fa-fw"></i></div></td>
                            <td class="p-3 text-success"><strong>Most recent match submitted: 
                                <?php echo ($recentMatchData[0]["match"]); ?>
                            </strong></td>
                        </tr></table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <hr class="mt-0">
</div>

<?php

echo("
<script>
    // first, data for the matchScores
    const scoreChart = document.getElementById('matchScoresChart');

    new Chart(scoreChart, {
        type: 'bar',
        data: {
            datasets: [
                    {
                    label: 'Blue Alliance',
                    data: [");

foreach ($matchScoresData as $match) {
    if ($match["blue"] > 0) {
        echo ("{ x: '" . $match["match"] . "', y: " . $match["blue"] . "},");
    }
}

echo("                    ]
                },
                {
                    label: 'Red Alliance',
                    data: [");

foreach ($matchScoresData as $match) {
    if ($match["blue"] > 0) {
        echo ("{ x: '" . $match["match"] . "', y: " . $match["red"] . "},");
    }
}

echo("                    ]
                }
            ]
        },
        options: {
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Match'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Total Score'
                    },
                    beginAtZero: true
                }
            }
        }
    });
    


    // second, data for match Submissions
    const sessionChart = document.getElementById('sessionChart');

    new Chart(sessionChart, {
        type: 'line',
        data: {
            datasets: [{
                label: 'Submissions per Match',
                data: [");
foreach ($matchSubmissionCountData as $match) {
    echo ("{ x: '" . $match["match"] . "', y: " . $match["count"] . "},");
}
echo("
                            ]
                            }]
                        },
                        options: {
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                x: {
                                    title: {
                                        display: true,
                                        text: 'Match'
                                    }
                                },
                                y: {
                                    title: {
                                        display: true,
                                        text: 'Submissions'
                                    },
                                    beginAtZero: true
                                }
                            }
                        }
                    });

                    </script>");

// Section: show links to teams with data.
?>
<div class="container">
    <h3>View Team Autos:</h3>
    <div class="row">
        
    <?php
        $availableTeams = $db->prepare("SELECT `teamNumber` FROM `teamMatch` WHERE `event` = ? AND `level` = 'qm' AND `match` > 0 GROUP BY `teamNumber` ORDER BY `teamNumber`");
        $availableTeams->bind_param("s", $_GET['event']);
        $availableTeams->execute();
        $availableTeamsResult = $availableTeams->get_result();
        $availableTeamsData = $availableTeamsResult->fetch_all(MYSQLI_ASSOC);

        foreach( $availableTeamsData as $team ) {
            echo("<div class=\"col-4 col-lg-2 mb-1\"><a href=\"/myevents/teamData.php?event=" . $_GET['event'] . "&team=" . $team['teamNumber'] . "\" class=\"btn btn-outline-secondary\" style=\"width:100%;\">" . $team['teamNumber'] . "</a></div>");
        }
    ?>

    </div>
    <hr>
</div>

<?php

// Section: get the data from the database that we want to show in the table.
if (isset($_GET['event'])) {
    echo("<div class=\"container\">
            <a href=\"event/" . $_GET["event"] . "\" class=\"float-end btn btn-secondary\">Enter Data <i class=\"fa-solid fa-right-long\"></i></a>
            <h3>Averaged data</h3>
            <small><a href=\"viewSubmissions.php?event=" . $_GET['event'] . "\">(Show actual event submissions)</a></small>
            <hr>
        </div>");

    echo("<div class=\"container\">
            <a href=\"getRawData.php?event=" . $_GET["event"] . "\" class=\"float-end btn btn-primary\"><i class=\"fa-solid fa-file-csv\"></i> Download all data</a>
    ");
    if (isset($_GET["allData"]) && $_GET["allData"] == "true") {
        $showAllData = "";
        echo("<a href=\"" . str_replace("&allData=true", "", $_SERVER['REQUEST_URI']) . "\" class=\"btn btn-outline-warning\">Show condensed table</a>");
    } else {
        $showAllData = "AND `dataSet` = 'minimum'";
        echo("<a href=\"" . $_SERVER['REQUEST_URI'] . "&allData=true\" class=\"btn btn-outline-warning\">Show all fields</a>");
    }
    echo("</div>");

    $dataPointGet = $db->prepare("SELECT `id`, `name`, `dataType` FROM `dataPoint` WHERE `season` = ? $showAllData");
    $dataPointGet->bind_param("i",substr($_GET['event'],0,4));
    $dataPointGet->execute();
    $dataPointGetResult = $dataPointGet->get_result();
    $dataPointGetResultData = $dataPointGetResult->fetch_all(MYSQLI_ASSOC);

    if ($dataPointGetResult->num_rows > 0) {
        echo("<div class=\"container\"><div class=\"table-responsive\" style=\"clear:both;\"><table class=\"table table-striped table-hover\"><thead><tr>");

        $dataPointColumns = "";
        $joinColumns = "";
        echo "<th>Match Number</th><th>Team Number</th>";
        foreach ($dataPointGetResultData as $dataItem) {
            if (strlen($dataPointColumns) > 0) {
                $dataPointColumns .= ", ";
                $joinColumns .= "\n";
            }
            echo ("<th>" . $dataItem["name"] . "</th>");
            if ($dataItem["dataType"] == "text") {
                // There's no way to "average" text. Original decision: let "yes" values override "no" values. So get distinct text that's not equal to "no" and if there's nothing left THEN show "no". But see comment below- this wasn't desirable. Need to check and see how this handles multiple submissions now.
                $dataPointColumns .= "(
                                        SELECT IFNULL( GROUP_CONCAT(DISTINCT sd.`dataText` SEPARATOR '<br>'), '') -- Note: this used to use 'No' as the IFNULL replace string. This caused 'no' to show up where blanks made more sense.
                                        FROM
                                            `submissionData` sd
                                            INNER JOIN `dataPoint` dp ON sd.`dataPointId` = dp.`id`-- AND sd.`dataText` <> 'no'
                                            INNER JOIN `submission` s ON sd.`submissionId` = s.`id`
                                        WHERE
                                            s.`teamMatchId` = tm.`id`
                                            AND sd.dataPointId = " . $dataItem["id"] . "
                                            $showAllData
                                            AND (SELECT count(1) FROM `flag` WHERE `submissionId` = s.`id`) < $flagThreshold
                                    ) AS `" . $dataItem["name"] . "`\n";
            } else {
                $dataPointColumns .= "(
                                        SELECT ROUND(AVG(sd.`dataValue`),0)
                                        FROM
                                            `submissionData` sd
                                            INNER JOIN `dataPoint` dp ON sd.`dataPointId` = dp.`id`
                                            INNER JOIN `submission` s ON sd.`submissionId` = s.`id`
                                        WHERE
                                            s.`teamMatchId` = tm.`id`
                                            AND dataPointId = " . $dataItem["id"] . "
                                            $showAllData
                                            AND (SELECT count(1) FROM `flag` WHERE `submissionId` = s.`id`) < $flagThreshold
                                    ) AS `" . $dataItem["name"] . "`\n";
            }
            
            $joinColumns .= "RIGHT OUTER JOIN `submissionData` data" . $dataItem["id"] . " ON dp.`id` = data" . $dataItem["id"] . ".`dataPointId` AND tm.`id` = data" . $dataItem["id"] . ".`teamMatchId`";
        }
        echo "<th>Alliance</th><th></th></thead><tbody>";

        // set up the query that we'll use to update the teamMatch columns from the FIRST api.
        $matchResultsUpdate = $db->prepare("UPDATE `teamMatch` SET `alliance` = ?, `allianceResults` = ?, `allianceResultsRetrievalDateTime` = CURRENT_TIMESTAMP WHERE `id` = ?");

        $matchGet = $db->prepare("SELECT
                                    tm.`id` AS `teamMatchId`
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
            $setAlliance = "";
            $setResults = "";
            
            // If the stored alliance data for the team's alliance is either too short OR if it's been stored but the scores weren't in yet (using Red Alliance final score as the canary for this test), we need to try and store/update it in the teamMatch table.
            if (strlen($row["allianceResults"]) < 5 || (json_validate($row["allianceResults"]) && isset(json_decode($row["allianceResults"], true)["scoreRedFinal"]) == false)) {

                // See if we've already gotten the event data from the API for this page load. (One call contains the data from all the event's matches). If not, get it.
                if (isset($allianceContent) == false) {
                    $allianceContent = curl_exec($matchRequest);
                    $err     = curl_errno($matchRequest);
                    $errmsg  = curl_error($matchRequest);
                }

                // Now use the data we've gotten - make sure it's valid. If so, determine the alliance for this particular team/match.
                if (json_validate($allianceContent)) {
                    $a_Matches = json_decode($allianceContent, true)["Matches"];
                    foreach ($a_Matches as $match) {
                        if ($match["matchNumber"] == $row["match"]) {
                            foreach ($match["teams"] as $team) {
                                if ($team["teamNumber"] == $row["teamNumber"]) {
                                    if (str_starts_with($team["station"], "Red")) {
                                        // "Red";
                                        $setAlliance = "Red";
                                        $row["alliance"] = "Red";
                                        $setResults = json_encode($match);

                                    } else if (str_starts_with($team["station"], "Blue")) {
                                        // "Blue";
                                        $setAlliance = "Blue";
                                        $row["alliance"] = "Blue";
                                        $setResults = json_encode($match);
                                    }
                                }

                                // Now if we found the alliance, update the alliance data into the [teamMatch] table
                                if (isset($setAlliance) && $setAlliance  <> "") {
                                    $matchResultsUpdate->bind_param("ssi", $setAlliance, $setResults, $row["teamMatchId"]);
                                    $matchResultsUpdate->execute();
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            // Can we get back to just showing the data please?
            $teamMatchId = $row["teamMatchId"];
            unset($row["teamMatchId"]);
            unset($row["allianceResults"]);
            echo ("<tr><td>" . implode("</td><td>", $row) . "</td><td><a class=\"btn btn-info btn-sm\" href=\"viewSubmissions.php?event=" . $_GET['event'] . "&match=" . $row["match"] . "\">View Submissions</a></td></tr>");
        }

        echo "</tbody></table></div></div>";
    }
}


include '../_footer.php';
?>