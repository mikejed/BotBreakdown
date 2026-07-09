<?php
$authRequired = true;
include '../_dbConnection.php';
include '../_head.php';

$firstEventCode = substr($_REQUEST["event"],4);

// set up the query that we'll use to update the teamMatch columns from the FIRST api.
$matchResultsUpdate = $db->prepare("UPDATE `teamMatch` SET `alliance` = ?, `allianceResults` = ?, `allianceResultsRetrievalDateTime` = CURRENT_TIMESTAMP WHERE `id` = ?");

// Now set up the queries that we'll use to store the data submitted through this page.
$teamMatchGet = $db->prepare("SELECT `id`,`alliance`,`allianceResults` FROM `teamMatch` WHERE `event` = ? AND `level` = ? AND `teamNumber` = ? AND `match` = ?;");
$teamMatchCreate = $db->prepare("INSERT INTO `teamMatch` (`event`,`level`,`teamNumber`,`match`) VALUES (?, ?, ?, ?);");

$submissionGet = $db->prepare("SELECT `id` FROM `submission` WHERE `teamMatchId` = ? AND `scouterId` = ?");
$submissionCreate = $db->prepare("INSERT INTO `submission` (`teamMatchId`, `scouterId`) VALUES (?, ?);");

$dataPointInsertNumerical = $db->prepare("INSERT INTO submissionData(`submissionId`,`dataPointId`,`dataValue`) VALUES (?, ?, ?);");
$dataPointInsertText = $db->prepare("INSERT INTO submissionData(`submissionId`,`dataPointId`,`dataText`) VALUES (?, ?, ?);");

?>
<div class="container">

    <?php

        // for testing/reference :)
        /*
        echo ("<pre>");
        print_r($_REQUEST);
        echo ("</pre>");
        */

        // Make sure we have data to store for this season.
        $dataPoints = $db->query("SELECT * FROM `dataPoint` WHERE `season` = $currentSeason;");
        if ($dataPoints->num_rows > 0) {
            
            // Only proceed with storing data if the needed data is included.
            if (isset($_REQUEST["event"]) && isset($_REQUEST["teamNumber"]) & isset($_REQUEST["match"])) {
                
                // First, see if there's already a teamMatch record.
                $qualLevel = "qm";
                $teamMatchGet->bind_param("sssi", $_REQUEST["event"], $qualLevel, $_REQUEST["teamNumber"], $_REQUEST["match"]);
                $teamMatchGet->execute();
                $teamMatchGetResult = $teamMatchGet->get_result();
                $teamMatchGetResultData = $teamMatchGetResult->fetch_all(MYSQLI_ASSOC);

                // If there's no teamMatch record, check to see if there's a matching record in the API
                if ($teamMatchGetResult->num_rows == 0) {
                    $matchRequest = curl_init("https://frc-api.firstinspires.org/v3.0/$currentSeason/matches/" . $firstEventCode . "?tournamentLevel=qualification&matchNumber=" . $_REQUEST["match"]);
                    curl_setopt_array($matchRequest, $firstCurlOpt);
                    $allianceContent    = curl_exec($matchRequest);
                    $err                = curl_errno($matchRequest);
                    $errmsg             = curl_error($matchRequest);
                    
                    $a_Matches = json_decode($allianceContent, true)["Matches"];
                    foreach ($a_Matches as $match) {
                        if ($match["matchNumber"] == $_REQUEST["match"]) {
                            foreach ($match["teams"] as $team) {
                                if ($team["teamNumber"] == $_REQUEST["teamNumber"]) {
                                    if (str_starts_with($team["station"], "Red")) {
                                        // "Red";
                                        $setAlliance = "Red";
                                        $teamMatchGetResultData[0]["alliance"] = "Red";
                                        $setResults = json_encode($match);
                                        break;

                                    } else if (str_starts_with($team["station"], "Blue")) {
                                        // "Blue";
                                        $setAlliance = "Blue";
                                        $teamMatchGetResultData[0]["alliance"] = "Blue";
                                        $setResults = json_encode($match);
                                        break;
                                    }
                                }
                            }
                        }
                    }


                    // If there's a match in the API, create a teamMatch record. (Don't create one otherwise, in case they make changes before submitting).
                    if (isset($setAlliance) && strlen($setAlliance) >= 3) {
                        // We found the alliance.
                        $teamMatchCreate->bind_param("sssi", $_REQUEST["event"], $_REQUEST["level"], $_REQUEST["teamNumber"], $_REQUEST["match"]);
                        $teamMatchCreate->execute();
                        $teamMatchGet->execute();
                        $teamMatchGetResult = $teamMatchGet->get_result();
                        $teamMatchGetResultData = $teamMatchGetResult->fetch_all(MYSQLI_ASSOC);

                        // Now update the API data into the [teamMatch] table. This could have been done in the above query but I'm changing only a few things at once and I don't know if i want to maintain two separate copies of the query- one with the API data and the other without.
                        $matchResultsUpdate->bind_param("ssi", $setAlliance, $setResults, $teamMatchGetResultData[0]["id"]);
                        $matchResultsUpdate->execute();

                    }
                }

                // Now let's figure out whether we should write the data or show a form allowing them to make corrections.
                if ($teamMatchGetResult->num_rows == 0 && ((isset($_REQUEST["skipConfirmation"]) == true && $_REQUEST["skipConfirmation"] != "true" ) || isset($_REQUEST["skipConfirmation"]) == false) ) {
                    // there was no match and no indication that we should store anyway. Show the form and make them resubmit.
                    ?>
                    <form action="submit.php" method="post">
                        <div class="alert alert-warning"><h3>Warning</h3>FIRST says that this team didn't compete in this event and match. If you want to store the data anyway, check the box to not check the match and submit again.</div>
                        <?php
                            foreach ($_REQUEST as $key => $val) {
                                // echo ($key . "=" . $val . "<br>");
                                if ($key != "event" && $key != "match" && $key != "teamNumber") {
                                    $safeKey = htmlspecialchars($key, ENT_QUOTES);
                                    $safeVal = htmlspecialchars($val, ENT_QUOTES);
                                    echo('<input type="hidden" name="' . $safeKey . '" value="' . $safeVal . '" />');
                                }
                            }
                        ?>
                        <div class="row">
                            <div class="col-md">
                                <label for="event" class="form-label">Event Code</label>
                                <input type="text" class="form-control" id="event" name="event" value="<?php echo htmlspecialchars($_REQUEST["event"], ENT_QUOTES); ?>" required>                
                            </div>

                            <div class="col-md">
                                <label for="teamNumber" class="form-label">Team Number</label>
                                <input type="text" class="form-control" id="teamNumber" name="teamNumber" value="<?php echo htmlspecialchars($_REQUEST["teamNumber"], ENT_QUOTES); ?>" required>                
                            </div>
                        
                            <div class="col-md">
                                <label for="match" class="form-label">Match</label>
                                <input type="number" class="form-control" id="match" name="match" min="1" value="<?php echo htmlspecialchars($_REQUEST["match"], ENT_QUOTES); ?>" required>
                            </div>


                        </div>

                        <input type="checkbox" name="skipConfirmation" id="skipConfirmation" value="true" />
                        <label for="skipConfirmation">Don't check this match again</label>
                        <br>
                        <input type="submit" class="mt-3" value="submit" />
                    </form>
                    <?php
                } else {
                    // We are writing the data

                    // Get the scouterid from the submission if it's provided. If not, default to $currentPersonId
                    // I wish I had a UUID for the scouters here now instead of ids. But for now this will work.
                    $submissionScouterId = isset($_REQUEST['scouterId']) && trim($_REQUEST['scouterId']) !== '' ? $_REQUEST['scouterId'] : $currentPersonId;

                    // If the teamMatch doesn't already exist, create it. (This should only happen if they chose to bypass the confirmation)
                    if ($teamMatchGetResult->num_rows == 0) {
                        $teamMatchCreate->bind_param("sssi", $_REQUEST["event"], $qualLevel, $_REQUEST["teamNumber"], $_REQUEST["match"]);
                        $teamMatchCreate->execute();
                        $teamMatchGet->execute();
                        $teamMatchGetResult = $teamMatchGet->get_result();
                        $teamMatchGetResultData = $teamMatchGetResult->fetch_all(MYSQLI_ASSOC);
                    }
                    $_teamMatchId = $teamMatchGetResultData[0]["id"];

                    // Find or Create the submission, then store $_submissionId.
                    $submissionGet->bind_param("ii", $_teamMatchId, $submissionScouterId);
                    $submissionGet->execute();
                    $submissionGetResult = $submissionGet->get_result();
                    $submissionGetResultData = $submissionGetResult->fetch_all(MYSQLI_ASSOC);

                    if ($submissionGetResult->num_rows == 0) {
                        $submissionCreate->bind_param("ii", $_teamMatchId, $submissionScouterId);
                        $submissionCreate->execute();
                        $submissionGet->execute();
                        $submissionGetResult = $submissionGet->get_result();
                        $submissionGetResultData = $submissionGetResult->fetch_all(MYSQLI_ASSOC);
                    }
                    $_submissionId = $submissionGetResultData[0]["id"];

                    // Write the history record for the submission.
                    logHistory("submit", json_encode($_REQUEST), "submission", $_submissionId); //(`operation`,`data`,`recordType`,`recordId`)
                    
                    // Easy navigation buttons
                    ?>
                    <div class="row">
                        <div class="col-md">
                            <a class="btn btn-outline-primary" href="viewData.php?event=<?php echo rawurlencode($_REQUEST["event"]); ?>"><i class="fa-solid fa-left-long"></i> View Event</a>
                        </div>
                        <div class="col-md text-end">
                            <a class="btn btn-primary" href="event/<?php echo rawurlencode($_REQUEST["event"]); ?>">Enter another match</a>
                        </div>
                    </div>
                    <?php

                    // Delete any existing data points for this submission (which constitutes the event, level, team, match, and scouter)
                    $submissionDelete = $db->prepare("DELETE FROM `submissionData` WHERE `submissionId` = ?");
                    $submissionDelete->bind_param("i", $_submissionId);
                    $submissionDelete->execute();
                    
                    // Also delete any flags for the submission now that new data is coming in. By design that will return a "flag" to the reporter if it was recent.
                    $submissionFlagDelete = $db->prepare("DELETE FROM `flag` WHERE `submissionId` = ?");
                    $submissionFlagDelete->bind_param("i", $_submissionId);
                    $submissionFlagDelete->execute();

                    // Now loop through the data provided and store anything that's valid.
                    // Assume that datapoints are approved until/unless other logic is added
                    echo ("<h4>Data entered:</h4><ul>\n");

                    // First: look for grouping values and store both values supplied AND missed ("no" or 0).
                    $groupingQuery = $db->prepare("SELECT DISTINCT `grouping` FROM `dataPoint` WHERE `season` = $currentSeason AND `grouping` IS NOT NULL;");
                    $groupingQuery->execute();
                    $groupingResult = $groupingQuery->get_result();
                    $groupingResultData = $groupingResult->fetch_all(MYSQLI_ASSOC);

                    $groupedFieldQuery = $db->prepare("SELECT `id`,`name`,`dataKey`,`dataType`,`blankValue` FROM `dataPoint` WHERE `season` = $currentSeason AND `grouping` = ? AND `grouping` IS NOT NULL");

                    foreach ($groupingResultData as $groupKey) {
                        // if (isset($_REQUEST[$groupKey["grouping"]]) && $_REQUEST[$groupKey["grouping"]] == "scouted" ) {
                            $groupedFieldQuery->bind_param("s", $groupKey["grouping"]);
                            $groupedFieldQuery->execute();
                            $groupedFieldResult = $groupedFieldQuery->get_result();
                            $groupedFieldData = $groupedFieldResult->fetch_all(MYSQLI_ASSOC);

                            foreach ($groupedFieldData as $field) {
                                if (isset($_REQUEST[$field["dataKey"]])) {
                                    if ($field["dataType"] == "text") {
                                        $dataPointInsertText->bind_param("iis", $_submissionId, $field["id"], $_REQUEST[$field["dataKey"]]);
                                        $dataPointInsertText->execute();
                                    } else{
                                        $dataPointInsertNumerical->bind_param("iii", $_submissionId, $field["id"], $_REQUEST[$field["dataKey"]]);
                                        $dataPointInsertNumerical->execute();
                                    }
                                    echo("<li><strong>" . e($field["name"]) . ":</strong> " . e($_REQUEST[$field["dataKey"]]) . "</li>\n");
                                } else {
                                    $noValue = 'No'; // $field["blankValue"];
                                    $noInt = 0;
                                    if ($field["dataType"] == "text") {
                                        // if the `blankValue` is null, don't store any value for this datapoint.
                                        if (!empty($noValue)) {
                                            $dataPointInsertText->bind_param("iis", $_submissionId, $field["id"], $noValue);
                                            $dataPointInsertText->execute();
                                            echo("<li><strong>" . e($field["name"]) . ":</strong> " . e($noValue) . "</li>\n");
                                        }
                                    } else {
                                        $dataPointInsertNumerical->bind_param("iii", $_submissionId, $field["id"], $noInt);
                                        $dataPointInsertNumerical->execute();
                                        echo("<li><strong>" . e($field["name"]) . ":</strong> " . $noInt . "</li>\n");
                                    }
                                }
                            }
                        // }
                    }

                    // Second: get all of the non-grouped values and store any that were passed with a value.
                    $ungroupedFieldQuery = $db->prepare("SELECT `id`,`name`,`dataKey`,`dataType` FROM `dataPoint` WHERE `season` = $currentSeason AND `grouping` IS NULL;");
                    $ungroupedFieldQuery->execute();
                    $ungroupedFieldResult = $ungroupedFieldQuery->get_result();
                    $ungroupedFieldData = $ungroupedFieldResult->fetch_all(MYSQLI_ASSOC);

                    foreach ($ungroupedFieldData as $field) {
                        if (isset($_REQUEST[$field["dataKey"]]) && strlen($_REQUEST[$field["dataKey"]]) > 0) {
                            if ($field["dataType"] == "text") {
                                $dataPointInsertText->bind_param("iis", $_submissionId, $field["id"], $_REQUEST[$field["dataKey"]]);
                                $dataPointInsertText->execute();
                            } else {
                                $dataPointInsertNumerical->bind_param("iii", $_submissionId, $field["id"], $_REQUEST[$field["dataKey"]]);
                                $dataPointInsertNumerical->execute();
                            }
                            echo("<li><strong>" . e($field["name"]) . ":</strong> " . e($_REQUEST[$field["dataKey"]]) . "</li>\n");
                        } elseif ($field["dataKey"] == "submitterId") {
                            $dataPointInsertText->bind_param("iis", $_submissionId, $field["id"], $currentPersonId);
                            $dataPointInsertText->execute();
                            echo("<li><strong>" . e($field["name"]) . ":</strong> " . $currentPersonId . "</li>\n");
                        }
                    }

                    // Done storing/listing.
                    echo ("</ul>");

                }
            } else {
                echo "<div class=\"alert alert-warning\"><strong>Oops!</strong> An event, team number, and match number are required.</div>";
            }
        } else {
            echo "No data points for this season yet.";
        }

    ?>
</div>

<?php
    include '../_footer.php';
?>