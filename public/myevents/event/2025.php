<?php
$authRequired = true;
$pageTitle = "Enter " . $_GET["event"] . " Data";
include '../../_dbConnection.php';
include '../../_head.php';
echo("<link href=\"/assets/css/dataStyles.css\" rel=\"stylesheet\" />");

if ( isset($_GET["event"]) ) {
    $firstEventCode = substr($_GET["event"],4);

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
?>
    <style>
        .btn-big {
            --bs-btn-padding-y: 0rem;
            --bs-btn-padding-x: 0rem;
        }
        input.input-lg  {
            padding: 0rem;
            padding-left: 1rem;
        }
        .tab-pane {
            font-size: large;
        }
        .tooltip .tooltip-inner {
            text-align: left;
        }
    </style>
    <script src="/assets/js/qrcode.min.js"></script>

    <form id="eventForm" method="post" action="../submit.php">

        <div class="container">

            <div class="row"><div class="col">
                <a class="btn btn-outline-secondary float-end" title="Review my submissions" href="javascript:viewHistory();"><i class="fa-solid fa-clock-rotate-left"></i></a>
                <h1><?php echo $eventName; ?></h1>
            </div></div>
            <div class="row">

                <div class="col-md">
                    <label for="teamNumber" class="form-label">Team Number</label>
                        
<?php
                    
                $eventRequest = curl_init("https://frc-api.firstinspires.org/v3.0/$currentSeason/teams?eventCode=" . $firstEventCode);
                curl_setopt_array($eventRequest, $firstCurlOpt);
                $content = curl_exec($eventRequest);
                $err     = curl_errno($eventRequest);
                $errmsg  = curl_error($eventRequest);
                if ( str_starts_with($content, "{") && json_decode($content)->teamCountTotal > 0 ) {

                    $a_Teams = json_decode($content)->teams;
                    // make sure if there are more pages of teams that we get them all.
                    $i_pages = json_decode($content)->pageTotal;
                    for ($i=2; $i<=$i_pages; $i++) {
                        $eventRequest = curl_init("https://frc-api.firstinspires.org/v3.0/$currentSeason/teams?eventCode=" . $firstEventCode . "&page=" . $i);
                        curl_setopt_array($eventRequest, $firstCurlOpt);
                        $content = curl_exec($eventRequest);
                        $a_Teams = array_merge($a_Teams, json_decode($content)->teams);
                    }

                    echo "<select class=\"form-control\" id=\"teamNumber\" name=\"teamNumber\" required \">
                    <option disabled selected value=\"\">(select team)</option>";
                    foreach( $a_Teams as $team ) {
                        echo("<option value=\"$team->teamNumber\">$team->teamNumber - $team->nameShort</option>");
                    }
                    echo "</select>";
                    echo "<input type=\"hidden\" name=\"event\" value=\"" . $_GET["event"] . "\" />";
                    } else {
?>
                        <input type="number" class="form-control" id="teamNumber" name="teamNumber" min="1" required>
                        <div class="invalid-feedback">Please set the team number.</div>
<?php
                        echo "</div><div class=\"col-md\">
                                <label for=\"event\" class=\"form-label\">Event Code</label>
                                <input type=\"text\" id=\"event\" name=\"event\" value=\"" . $_GET["event"] . "\" class=\"form-control\" />
                            ";
                    }
?>
                </div>
            
                <?php if (substr($_GET["event"], 0, 4) == $currentSeason) { ?>
                <div class="col-md">
                    <label for="match" class="form-label">Match</label>
                    <input type="number" class="form-control" id="match" name="match" min="1" required>
                    <div class="invalid-feedback">Please set the match number.</div>
                </div>

                
                <div class="col-md">
                    <label for="match" class="form-label">
                        Submission Tag (optional)
                        <sup><i class="fa-solid fa-circle-info text-primary" data-bs-toggle="tooltip" data-bs-html="true" title="If you put some text here (such as your team number), you'll be able to see it in the final data. Your teams' scouters can all use the same text if you want to be able to filter the final data for just your submissions."></i></sup>
                    </label>
                    <input class="form-control" id="tag" name="tag">
                </div>
                <?php } ?>
        
            </div><!-- /.row -->

            <?php if (substr($_GET["event"], 0, 4) == $currentSeason) { ?>

                <div class="row mt-5">
                    <nav>
                        <div class="nav nav-tabs" id="nav-tab" role="tablist">
                            <button class="nav-link active" id="nav-auto-tab" data-bs-toggle="tab" data-bs-target="#nav-auto" type="button" role="tab" aria-controls="nav-auto" aria-selected="true">Autonomous</button>
                            <button class="nav-link" id="nav-tele-tab" data-bs-toggle="tab" data-bs-target="#nav-tele" type="button" role="tab" aria-controls="nav-tele" aria-selected="false">Teleoperated</button>
                        </div>
                    </nav>
                </div>
                <div class="tab-content card card-body bg-body-secondary" style="border-top:0px;" id="nav-tabContent">
                    <div class="tab-pane fade show active" id="nav-auto" role="tabpanel" aria-labelledby="nav-auto-tab">
                        <div class="row">
                            <div class="col-md-7">
                                <a href="javascript:undoPoint();" title="Erase last point" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left"></i></a>
                                <label class="form-label">
                                    Drive Path
                                    <sup><i class="fa-solid fa-circle-info text-primary" data-bs-toggle="tooltip" data-bs-html="true" title="Choose the alliance the team is on. Then click on the map where the robot starts the match.<br>If they move off the line during the autonomous phase, you can click more points to indicate where they drove.<br>Use the undo button to the left if you need to erase one or more points."></i></sup>
                                </label>
                                    
                                <div class="form-check form-check-inline ms-4 ms-xl-5">
                                    <input class="form-check-input" type="checkbox" id="flip" name="flip">
                                    <label class="form-check-label" for="flip">Flip</label>
                                </div>

                                <span class="float-end">
                                    <label class="form-label me-4">Alliance:</label>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" id="blue" name="alliance" value="Blue">
                                        <label class="form-check-label" for="blue">Blue</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" id="red" name="alliance" value="Red">
                                        <label class="form-check-label" for="red">Red</label>
                                    </div>
                                </span>
                                <br>
                                <canvas id="drawingCanvas" width="500" height="426" style="width: 100%; height: auto; background-image: url(''); background-size: cover;"></canvas>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">
                                    L4 Coral
                                    <small style="font-weight: normal;">(Count coral, not points)</small>
                                    <sup><i class="fa-solid fa-circle-info text-primary" data-bs-toggle="tooltip" data-bs-html="true" title="This is the NUMBER of coral the team SCORED during the autonomous phase, on the highest level of the reef."></i></sup>
                                </label>
                                <div class="row">
                                    <div class="col">
                                        <a class="btn btn-big btn-danger form-control" id="L4AutoDecrease" onClick="changeValue($('#L4Auto'),-1)">-1</a>
                                    </div>
                                    <div class="col">
                                        <input type="number" class="form-control input-lg" id="L4Auto" name="L4Auto" min="0" value="0">
                                    </div>
                                    <div class="col-1" style="min-width: 3rem; margin-left: -1.5rem;">
                                        <a class="btn btn-big btn-default" onClick="setBlank($('#L4Auto'))">⛔</a>
                                    </div>
                                    <div class="col">
                                        <a class="btn btn-big btn-success form-control" id="L4AutoIncrease" onClick="changeValue($('#L4Auto'),1)">+1</a>
                                    </div>
                                </div><!-- /.row -->
                                
                                <label class="form-label">
                                    L3 Coral
                                    <small style="font-weight: normal;">(Count coral, not points)</small>
                                    <sup><i class="fa-solid fa-circle-info text-primary" data-bs-toggle="tooltip" data-bs-html="true" title="This is the NUMBER of coral the team SCORED during the autonomous phase, on the third level of the reef."></i></sup>
                                </label>
                                <div class="row">
                                    <div class="col">
                                        <a class="btn btn-big btn-danger form-control" id="L3AutoDecrease" onClick="changeValue($('#L3Auto'),-1)">-1</a>
                                    </div>
                                    <div class="col">
                                        <input type="number" class="form-control input-lg" id="L3Auto" name="L3Auto" min="0" value="0">
                                    </div>
                                    <div class="col-1" style="min-width: 3rem; margin-left: -1.5rem;">
                                        <a class="btn btn-big btn-default" onClick="setBlank($('#L3Auto'))">⛔</a>
                                    </div>
                                    <div class="col">
                                        <a class="btn btn-big btn-success form-control" id="L3AutoIncrease" onClick="changeValue($('#L3Auto'),1)">+1</a>
                                    </div>
                                </div><!-- /.row -->
                                
                                <label class="form-label">
                                    L2 Coral
                                    <small style="font-weight: normal;">(Count coral, not points)</small>
                                    <sup><i class="fa-solid fa-circle-info text-primary" data-bs-toggle="tooltip" data-bs-html="true" title="This is the NUMBER of coral the team SCORED during the autonomous phase, on the second level of the reef."></i></sup>
                                </label>
                                <div class="row">
                                    <div class="col">
                                        <a class="btn btn-big btn-danger form-control" id="L2AutoDecrease" onClick="changeValue($('#L2Auto'),-1)">-1</a>
                                    </div>
                                    <div class="col">
                                        <input type="number" class="form-control input-lg" id="L2Auto" name="L2Auto" min="0" value="0">
                                    </div>
                                    <div class="col-1" style="min-width: 3rem; margin-left: -1.5rem;">
                                        <a class="btn btn-big btn-default" onClick="setBlank($('#L2Auto'))">⛔</a>
                                    </div>
                                    <div class="col">
                                        <a class="btn btn-big btn-success form-control" id="L2AutoIncrease" onClick="changeValue($('#L2Auto'),1)">+1</a>
                                    </div>
                                </div><!-- /.row -->
                                
                                <label class="form-label">
                                    L1 Coral
                                    <small style="font-weight: normal;">(Count coral, not points)</small>
                                    <sup><i class="fa-solid fa-circle-info text-primary" data-bs-toggle="tooltip" data-bs-html="true" title="This is the NUMBER of coral the team SCORED during the autonomous phase, in the lowest level (tray) of the reef."></i></sup>
                                </label>
                                <div class="row">
                                    <div class="col">
                                        <a class="btn btn-big btn-danger form-control" id="L1AutoDecrease" onClick="changeValue($('#L1Auto'),-1)">-1</a>
                                    </div>
                                    <div class="col">
                                        <input type="number" class="form-control input-lg" id="L1Auto" name="L1Auto" min="0" value="0">
                                    </div>
                                    <div class="col-1" style="min-width: 3rem; margin-left: -1.5rem;">
                                        <a class="btn btn-big btn-default" onClick="setBlank($('#L1Auto'))">⛔</a>
                                    </div>
                                    <div class="col">
                                        <a class="btn btn-big btn-success form-control" id="L1AutoIncrease" onClick="changeValue($('#L1Auto'),1)">+1</a>
                                    </div>
                                </div><!-- /.row -->
                                
                                <hr class="mt-4 mb-1">

                                <label class="form-label">
                                    Algae Removed
                                    <sup><i class="fa-solid fa-circle-info text-primary" data-bs-toggle="tooltip" data-bs-html="true" title="This is the NUMBER of algae the robot removed from the coral, during the autonomous phase."></i></sup>
                                </label>
                                <div class="row">
                                    <div class="col">
                                        <a class="btn btn-big btn-danger form-control" id="AlgaeAutoDecrease" onClick="changeValue($('#AlgaeAuto'),-1)">-1</a>
                                    </div>
                                    <div class="col">
                                        <input type="number" class="form-control input-lg" id="AlgaeAuto" name="AlgaeAuto" min="0" value="0">
                                    </div>
                                    <div class="col-1" style="min-width: 3rem; margin-left: -1.5rem;">
                                        <a class="btn btn-big btn-default" onClick="setBlank($('#AlgaeAuto'))">⛔</a>
                                    </div>
                                    <div class="col">
                                        <a class="btn btn-big btn-success form-control" id="AlgaeAutoIncrease" onClick="changeValue($('#AlgaeAuto'),1)">+1</a>
                                    </div>
                                </div><!-- /.row -->
                                
                                <label class="form-label">
                                    Algae in Processor
                                    <small style="font-weight: normal;">(Count algae, not points)</small>
                                    <sup><i class="fa-solid fa-circle-info text-primary" data-bs-toggle="tooltip" data-bs-html="true" title="This is the NUMBER of algae the team successfully put into the processor during the autonomous phase."></i></sup>
                                </label>
                                <div class="row">
                                    <div class="col">
                                        <a class="btn btn-big btn-danger form-control" id="ProcessorAutoDecrease" onClick="changeValue($('#ProcessorAuto'),-1)">-1</a>
                                    </div>
                                    <div class="col">
                                        <input type="number" class="form-control input-lg" id="ProcessorAuto" name="ProcessorAuto" min="0" value="0">
                                    </div>
                                    <div class="col-1" style="min-width: 3rem; margin-left: -1.5rem;">
                                        <a class="btn btn-big btn-default" onClick="setBlank($('#ProcessorAuto'))">⛔</a>
                                    </div>
                                    <div class="col">
                                        <a class="btn btn-big btn-success form-control" id="ProcessorAutoIncrease" onClick="changeValue($('#ProcessorAuto'),1)">+1</a>
                                    </div>
                                </div><!-- /.row -->
                                
                                <label class="form-label">
                                    Algae in Net
                                    <small style="font-weight: normal;">(Count algae, not points)</small>
                                    <sup><i class="fa-solid fa-circle-info text-primary" data-bs-toggle="tooltip" data-bs-html="true" title="This is the NUMBER of algae the team got in the net during the autonomous phase."></i></sup>
                                </label>
                                <div class="row">
                                    <div class="col">
                                        <a class="btn btn-big btn-danger form-control" id="NetAutoDecrease" onClick="changeValue($('#NetAuto'),-1)">-1</a>
                                    </div>
                                    <div class="col">
                                        <input type="number" class="form-control input-lg" id="NetAuto" name="NetAuto" min="0" value="0">
                                    </div>
                                    <div class="col-1" style="min-width: 3rem; margin-left: -1.5rem;">
                                        <a class="btn btn-big btn-default" onClick="setBlank($('#NetAuto'))">⛔</a>
                                    </div>
                                    <div class="col">
                                        <a class="btn btn-big btn-success form-control" id="NetAutoIncrease" onClick="changeValue($('#NetAuto'),1)">+1</a>
                                    </div>
                                </div><!-- /.row -->
                                <div class="row mt-4">
                                    <p>(Use the ⛔ buttons to clear the counts, if you're not tracking these items.)</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="nav-tele" role="tabpanel" aria-labelledby="nav-tele-tab">
                        <div class="row">
                            <div class="col-md order-1 order-md-0">
                                <div class="row">
                                    <div class="col-sm">
                                        <label class="form-label">Role</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="role-defense" name="role-defense" value="Defense">
                                            <label class="form-check-label" for="role-defense">Defense</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="role-offense" name="role-offense" value="Offense">
                                            <label class="form-check-label" for="role-offense">Offense</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="role-feeder" name="role-feeder" value="Feeder">
                                            <label class="form-check-label" for="role-feeder">Feeder</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="role-other" name="role-other" value="Other">
                                            <label class="form-check-label" for="role-other">Other</label>
                                        </div>
                                    </div>
                                    <div class="col-sm">
                                        <label class="form-label">
                                            Endgame
                                            <sup><i class="fa-solid fa-circle-info text-primary" data-bs-toggle="tooltip" data-bs-html="true" title="Where the robot actually ended the match - regardless of whether another ending was attempted."></i></sup>
                                        </label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" id="endgame-none" name="endgame" value="None">
                                            <label class="form-check-label" for="endgame-none">None</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" id="endgame-parked" name="endgame" value="Parked">
                                            <label class="form-check-label" for="endgame-parked">Parked</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" id="endgame-shallow" name="endgame" value="Shallow">
                                            <label class="form-check-label" for="endgame-shallow">Shallow</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" id="endgame-deep" name="endgame" value="Deep">
                                            <label class="form-check-label" for="endgame-deep">Deep</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm">
                                        <label class="form-label">
                                            Removes from Reef
                                            <sup><i class="fa-solid fa-circle-info text-primary" data-bs-toggle="tooltip" data-bs-html="true" title="If the bot was able to remove algae from the reef at any point in the match, indicate if it removed from the upper level of algae and/or the lower level."></i></sup>
                                        </label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="upper-algae-removed" name="upper-algae-removed" value="Upper">
                                            <label class="form-check-label" for="upper-algae-removed">Upper Algae</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="lower-algae-removed" name="lower-algae-removed" value="Lower">
                                            <label class="form-check-label" for="lower-algae-removed">Lower Algae</label>
                                        </div>
                                    </div>
                                    <div class="col-sm">
                                        <label class="form-label">
                                            Loads Coral From
                                            <sup><i class="fa-solid fa-circle-info text-primary" data-bs-toggle="tooltip" data-bs-html="true" title="Where the robot picked up coral from- the station and/or the ground."></i></sup>
                                        </label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="coral-station" name="coral-station" value="Station">
                                            <label class="form-check-label" for="coral-station">Station</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="coral-floor" name="coral-floor" value="Floor">
                                            <label class="form-check-label" for="coral-floor">Floor</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm">
                                        <label class="form-label">
                                            Ground Algae Pickup
                                            <sup><i class="fa-solid fa-circle-info text-primary" data-bs-toggle="tooltip" data-bs-html="true" title="Check the box if the robot makes a controlled algae pickup from the ground."></i></sup>
                                        </label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="algae-ground" name="algae-ground" value="Yes">
                                            <label class="form-check-label" for="algae-ground">Yes</label>
                                        </div>
                                    </div>
                                    <div class="col-sm">
                                        <label class="form-label">Broken</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" id="broken-1" name="broken" value="Broken">
                                            <label class="form-check-label" for="broken-1">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" id="broken-0" name="broken" value="No">
                                            <label class="form-check-label" for="broken-0">No</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md order-0 order-md-1">
                                <label class="form-label">
                                    L4 Coral
                                    <small style="font-weight: normal;">(Count coral, not points)</small>
                                    <sup><i class="fa-solid fa-circle-info text-primary" data-bs-toggle="tooltip" data-bs-html="true" title="This is the NUMBER of coral the team SCORED during the teleoperated phase, on the highest level of the reef."></i></sup>
                                </label>
                                <div class="row">
                                    <div class="col">
                                        <a class="btn btn-big btn-danger form-control" id="L4TeleDecrease" onClick="changeValue($('#L4Tele'),-1)">-1</a>
                                    </div>
                                    <div class="col">
                                        <input type="number" class="form-control input-lg" id="L4Tele" name="L4Tele" min="0" value="0">
                                    </div>
                                    <div class="col-1" style="min-width: 3rem; margin-left: -1.5rem;">
                                        <a class="btn btn-big btn-default" onClick="setBlank($('#L4Tele'))">⛔</a>
                                    </div>
                                    <div class="col">
                                        <a class="btn btn-big btn-success form-control" id="L4TeleIncrease" onClick="changeValue($('#L4Tele'),1)">+1</a>
                                    </div>
                                </div><!-- /.row -->
                                
                                <label class="form-label">
                                    L3 Coral
                                    <small style="font-weight: normal;">(Count coral, not points)</small>
                                    <sup><i class="fa-solid fa-circle-info text-primary" data-bs-toggle="tooltip" data-bs-html="true" title="This is the NUMBER of coral the team SCORED during the teleoperated phase, on the third level of the reef."></i></sup>
                                </label>
                                <div class="row">
                                    <div class="col">
                                        <a class="btn btn-big btn-danger form-control" id="L3TeleDecrease" onClick="changeValue($('#L3Tele'),-1)">-1</a>
                                    </div>
                                    <div class="col">
                                        <input type="number" class="form-control input-lg" id="L3Tele" name="L3Tele" min="0" value="0">
                                    </div>
                                    <div class="col-1" style="min-width: 3rem; margin-left: -1.5rem;">
                                        <a class="btn btn-big btn-default" onClick="setBlank($('#L3Tele'))">⛔</a>
                                    </div>
                                    <div class="col">
                                        <a class="btn btn-big btn-success form-control" id="L3TeleIncrease" onClick="changeValue($('#L3Tele'),1)">+1</a>
                                    </div>
                                </div><!-- /.row -->
                                
                                <label class="form-label">
                                    L2 Coral
                                    <small style="font-weight: normal;">(Count coral, not points)</small>
                                    <sup><i class="fa-solid fa-circle-info text-primary" data-bs-toggle="tooltip" data-bs-html="true" title="This is the NUMBER of coral the team SCORED during the teleoperated phase, on the second level of the reef."></i></sup>
                                </label>
                                <div class="row">
                                    <div class="col">
                                        <a class="btn btn-big btn-danger form-control" id="L2TeleDecrease" onClick="changeValue($('#L2Tele'),-1)">-1</a>
                                    </div>
                                    <div class="col">
                                        <input type="number" class="form-control input-lg" id="L2Tele" name="L2Tele" min="0" value="0">
                                    </div>
                                    <div class="col-1" style="min-width: 3rem; margin-left: -1.5rem;">
                                        <a class="btn btn-big btn-default" onClick="setBlank($('#L2Tele'))">⛔</a>
                                    </div>
                                    <div class="col">
                                        <a class="btn btn-big btn-success form-control" id="L2TeleIncrease" onClick="changeValue($('#L2Tele'),1)">+1</a>
                                    </div>
                                </div><!-- /.row -->
                                
                                <label class="form-label">
                                    L1 Coral
                                    <small style="font-weight: normal;">(Count coral, not points)</small>
                                    <sup><i class="fa-solid fa-circle-info text-primary" data-bs-toggle="tooltip" data-bs-html="true" title="This is the NUMBER of coral the team SCORED during the teleoperated phase, on the bottom/tray level of the reef."></i></sup>
                                </label>
                                <div class="row">
                                    <div class="col">
                                        <a class="btn btn-big btn-danger form-control" id="L1TeleDecrease" onClick="changeValue($('#L1Tele'),-1)">-1</a>
                                    </div>
                                    <div class="col">
                                        <input type="number" class="form-control input-lg" id="L1Tele" name="L1Tele" min="0" value="0">
                                    </div>
                                    <div class="col-1" style="min-width: 3rem; margin-left: -1.5rem;">
                                        <a class="btn btn-big btn-default" onClick="setBlank($('#L1Tele'))">⛔</a>
                                    </div>
                                    <div class="col">
                                        <a class="btn btn-big btn-success form-control" id="L1TeleIncrease" onClick="changeValue($('#L1Tele'),1)">+1</a>
                                    </div>
                                </div><!-- /.row -->

                                <hr class="mt-4 mb-1">
                                
                                <label class="form-label">Algae in Processor <small style="font-weight: normal;">(Count algae, not points)</small></label>
                                <div class="row">
                                    <div class="col">
                                        <a class="btn btn-big btn-danger form-control" id="ProcessorTeleDecrease" onClick="changeValue($('#ProcessorTele'),-1)">-1</a>
                                    </div>
                                    <div class="col">
                                        <input type="number" class="form-control input-lg" id="ProcessorTele" name="ProcessorTele" min="0" value="0">
                                    </div>
                                    <div class="col-1" style="min-width: 3rem; margin-left: -1.5rem;">
                                        <a class="btn btn-big btn-default" onClick="setBlank($('#ProcessorTele'))">⛔</a>
                                    </div>
                                    <div class="col">
                                        <a class="btn btn-big btn-success form-control" id="ProcessorTeleIncrease" onClick="changeValue($('#ProcessorTele'),1)">+1</a>
                                    </div>
                                </div><!-- /.row -->
                                
                                <label class="form-label">Algae in Net <small style="font-weight: normal;">(Count algae, not points)</small></label>
                                <div class="row">
                                    <div class="col">
                                        <a class="btn btn-big btn-danger form-control" id="NetTeleDecrease" onClick="changeValue($('#NetTele'),-1)">-1</a>
                                    </div>
                                    <div class="col">
                                        <input type="number" class="form-control input-lg" id="NetTele" name="NetTele" min="0" value="0">
                                    </div>
                                    <div class="col-1" style="min-width: 3rem; margin-left: -1.5rem;">
                                        <a class="btn btn-big btn-default" onClick="setBlank($('#NetTele'))">⛔</a>
                                    </div>
                                    <div class="col">
                                        <a class="btn btn-big btn-success form-control" id="NetTeleIncrease" onClick="changeValue($('#NetTele'),1)">+1</a>
                                    </div>
                                </div><!-- /.row -->
                                <div class="row mt-4">
                                    <p>(Use the ⛔ buttons to clear the counts, if you're not tracking these items.)</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- /.row -->

                <div class="row">
                    <div class="col-md-8">
                        <label class="form-label" for="comment">Comment</label>
                        <textarea class="form-control" id="comment" name="comment" rows="6"></textarea>
                    </div>
                    <div class="col-md-4">
                        <input type="submit" class="btn btn-primary btn-big form-control mt-5 py-3" value="Submit" />
                        <input class="btn btn-secondary form-control mt-4" type="reset" value="Reset form" />
                        <div id="submitError" class="alert alert-warning" style="display: none;"><i class="fa-solid fa-triangle-exclamation fa-3x fa-pull-left"></i>Missing data- please check the event, team, and match. <a href="#" class="btn btn-default btn-sm"><i class="fa-solid fa-turn-up"></i></a></div>
                    </div>
                </div><!-- /.row -->

                <input type="hidden" id="coordinatesInput" name="coordinates">
                <input type="hidden" id="scouterId" name="scouterId" value="<?php echo $currentPersonId; ?>">
            <?php }
                else { ?>
                <div class="alert alert-info mt-5"><h4>Where we're going we don't need forms...</h4>Hmm, you loaded an event from a different year. Only time travelers would need to enter data here, so we assume you just need to view the history of your submissions on this device, or to check the list of which teams were at this event.</div>
            <?php } ?>
        </div>

    </form>

    <hr class="py-3 my-5">
    <div class="container mb-5">
        <div class="accordion" id="formAccordion">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#advancedOptions" aria-expanded="false" aria-controls="advancedOptions">
                        Advanced Options
                    </button>
                </h2>
                <div id="advancedOptions" class="accordion-collapse collapse" data-bs-parent="#formAccordion">
                    <div class="accordion-body">
                        <div><strong>Delete my <?php echo $_GET["event"]; ?> QR Code history</strong></div>
                        <ul>
                            <li>This will remove your submission QR Codes from this device.</li>
                            <li>Unsubmitted match data will be lost forever.</li>
                            <li>Submitted match data will not be deleted from the server.</li>
                        </ul>
                        <div class="text-end">
                            <a class="btn btn-outline-secondary" title="Delete QR Code history" href="javascript:deleteHistory();">Delete QR code history</a>
                        </div>

                        <hr>

                        <div><strong>Reset Offline Cache</strong></div>
                        <div class="alert alert-warning"><strong>Warning!</strong> Clicking this button will clear your BotBreakdown offline files cache. You will not be able to load pages from this site again until you are online.</div>
                        <div class="text-end">
                            <a class="btn btn-outline-danger" id="reload-cache" title="Reload Cached Files" href="javascript:startFlush();">Reset my offline page.</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="codeModal" tabindex="-1" aria-labelledby="codeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="codeModalLabel">Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="modalQRCode"></div>
                    </div>
                <div class="modal-footer justify-content-between">
                    <div id="modal-actions" style="width:200px;"></div>
                    <div id="modal-actions2"></div>
                    <div class="text-end" style="width:200px;">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (substr($_GET["event"], 0, 4) == $currentSeason) { ?>
        <script type="text/javascript">

            const canvas = document.getElementById('drawingCanvas');
            const ctx = canvas.getContext('2d');
            let coordinates = [];
            const form = document.getElementById('eventForm');
            const coordinatesInput = document.getElementById('coordinatesInput');
            const flipCheckbox = document.getElementById('flip');

            // sets the initial ratio of the canvas
            canvas.width = 500;
            canvas.height = 426;

            canvas.addEventListener('click', (event) => {
                const rect = canvas.getBoundingClientRect();

                // The click coordinates are absolute to the page, so account for where the canvas is on the page to get the click location in the canvas.
                // The points and lines need to be drawn relative to the native (500px wide) canvas size, even if the HTML is stretching the element.
                // So to store where the points will be drawn, get the percentage then multiply by the actual current size of the canvas. Crazy.
                let x = Math.floor(((event.clientX - rect.left) / rect.width) * canvas.width);
                let y = Math.floor(((event.clientY - rect.top) / rect.height) * canvas.height);

                // If the canvas is flipped, rotate the click to where it should show up.
                if (flipCheckbox.checked) {
                    x = canvas.width - x;
                    y = canvas.height - y;
                }

                coordinates.push({ x: x, y: y });
                drawPoint(x, y);
            if (coordinates.length > 1) {
                const prevPoint = coordinates[coordinates.length - 2];
                ctx.beginPath();
                ctx.moveTo(prevPoint.x, prevPoint.y);
                ctx.lineTo(x, y);
                ctx.stroke();
            }
            });

            function drawPoint(x, y) {
                ctx.fillStyle = 'black';
                ctx.fillRect(x, y, 2, 2);
            }

            function undoPoint() {
                coordinates.pop();
                redrawCanvas();
            }

            function redrawCanvas() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                coordinates.forEach((coord, index) => {
                    drawPoint(coord.x, coord.y);
                    if (index > 0) {
                        const prevPoint = coordinates[index - 1];
                        ctx.beginPath();
                        ctx.moveTo(prevPoint.x, prevPoint.y);
                        ctx.lineTo(coord.x, coord.y);
                        ctx.stroke();
                    }
                });
            }

            function mirrorCoordinates(coords) {
                return coords.map(coord => ({
                    x: canvas.width - coord.x,
                    y: coord.y
                }));
                redrawCanvas();
            }

            flipCheckbox.addEventListener('change', () => {
                if (flipCheckbox.checked) {
                    canvas.style.transform = 'rotate(180deg)';
                } else {
                    canvas.style.transform = 'rotate(0deg)';
                }
            });

            form.addEventListener('change', (event) => {
                if (event.target.name === 'alliance') {
                    if (event.target.value === 'Blue') {
                        canvas.style.backgroundImage = 'url(/assets/images/2025/blue-court-500.png)';
                        coordinates = mirrorCoordinates(coordinates);
                        redrawCanvas();
                    } else if (event.target.value === 'Red') {
                        canvas.style.backgroundImage = 'url(/assets/images/2025/red-court-500.png)';
                        coordinates = mirrorCoordinates(coordinates);
                        redrawCanvas();
                    }
                }
            });

            form.addEventListener('reset', (event) => {
                canvas.style.backgroundImage = 'url("")';
                canvas.style.transform = 'rotate(0deg)';
                coordinates.length = 0;
                const prevPoint = '';
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            });

            form.addEventListener('submit', (event) => {
                event.preventDefault();

                // First, turn the autonomous map into a series of SVG style coordinates.
                const scaledCoordinates = coordinates.map(coord => ({
                    // In the output, we'll divide by 5 to get back to 2 digits (percentages) to keep the submission string compact.
                    // (Note: that makes the lines much wider when recomposed - we're calling that a feature, not a bug).
                    x: Math.round(coord.x / 5),
                    y: Math.round(coord.y / 5)
                }));
                const pad = (num) => String(num).padStart(2, '0');
                const coordinatesList = scaledCoordinates.map(coord => `${pad(coord.x)} ${pad(coord.y)}`).join(' L');
                coordinatesInput.value = coordinatesList;

                // Store the value of the #tag input in a cookie
                setCookie("tag", document.getElementById('tag').value, 30);
                
                // Now validate the form
                $('body').addClass('was-validated');
                if (
                    $('#teamNumber:valid').length > 0
                    && $('#match:valid').length > 0
                ){
                    
                    $('#submitError').hide();
                    // Store the URL in localStorage. Set/get the nextIndex
                    if (!localStorage.getItem('<?php echo $_GET["event"]; ?>nextIndex') > 0) {
                        localStorage.setItem('<?php echo $_GET["event"]; ?>nextIndex',1);
                    }
                    var thisIndex = localStorage.getItem('<?php echo $_GET["event"]; ?>nextIndex');

                    // Set the URL of the submit page including data in GET string.
                    var dataSubmitUrl = 'https://www.botbreakdown.com/myevents/submit.php?' + $('#eventForm').serialize();
                    localStorage.setItem('<?php echo $_GET["event"]; ?>' + thisIndex, dataSubmitUrl);

                    // Set the match name.
                    var matchTitle = 'Match ' + $('#match').val() + ', Team ' + $('#teamNumber').val();
                    localStorage.setItem('<?php echo $_GET["event"]; ?>' + thisIndex + 'title', matchTitle)

                    // Increment the nextIndex in localStorage
                    localStorage.setItem('<?php echo $_GET["event"]; ?>nextIndex',thisIndex*1+1);

                    // Now check if we're online. If so, submit. If not, show QR code.
                    $.ajax({url: "/pingTest.php", cache: false, timeout: 1500}).done(function( data ) {
                        // Probably online (Note: data contains result)

                        // Indicate this URL has been submitted.
                        localStorage.setItem('<?php echo $_GET["event"]; ?>' + thisIndex+'submitted','true');

                        // Then actually submit it.
                        form.submit();

                    }).fail(function(err) {
                        // Offline or server down.

                        // Indicate this URL hasn't been submitted.
                        localStorage.setItem('<?php echo $_GET["event"]; ?>' + thisIndex+'submitted','false');

                        // clear the QR div's previous content.
                        $('#modal-actions').html("");
                        $('#modalQRCode').html("<p><strong>It looks like you may not be online. We'll store these results in a QR code for now; be sure to come back and submit this later</strong></p>");
                        $('#codeModalLabel').text(matchTitle);

                        // Set and show the QR code in the modal.
                        new QRCode(document.getElementById("modalQRCode"), {text:dataSubmitUrl,width:800,height:800,correctLevel:QRCode.CorrectLevel.L});
                        $('#modalQRCode > img').addClass('img-fluid');
                        codeModal.show();
                    });
                } else {
                    $('#submitError').show();
                }

            });
        
            function changeValue(ctl,val) {
                // Updates the input data numbers when you click [+] or [-]
                ctl.val( isNaN(parseInt(ctl.val())) ? 1 : parseInt(ctl.val()) + val );
                if (ctl.val() < 0) {ctl.val("0")}
            }

            function setBlank(ctl) {
                ctl.val('')
            }


            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
            })

            // Function to set a cookie
            function setCookie(name, value, days) {
                const d = new Date();
                d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
                const expires = "expires=" + d.toUTCString();
                document.cookie = name + "=" + value + ";" + expires + ";path=/";
            }

            // Function to get a cookie
            function getCookie(name) {
                const cname = name + "=";
                const decodedCookie = decodeURIComponent(document.cookie);
                const ca = decodedCookie.split(';');
                for (let i = 0; i < ca.length; i++) {
                    let c = ca[i];
                    while (c.charAt(0) == ' ') {
                        c = c.substring(1);
                    }
                    if (c.indexOf(cname) == 0) {
                        return c.substring(cname.length, c.length);
                    }
                }
                return "";
            }

            // Set the default value of the #tag input from the cookie
            window.onload = function() {
                const tagValue = getCookie("tag");
                if (tagValue != "") {
                    document.getElementById('tag').value = tagValue;
                }
            };

        </script>
    <?php } ?>

    <script type="text/javascript">

        const codeModal = new bootstrap.Modal('#codeModal', {});

        function startFlush() {
            // They clicked the flush cache button. Change the link of the button and the text to ask them to click it again to confirm that they really want to do so.
            $('#reload-cache').text('Click to confirm you want to dump the offline version of this page.');
            $('#reload-cache').attr('title','If you are not online you will lose access to this page.');
            $('#reload-cache').attr('href','javascript:flush();');
        }

        function flush() {
            // They clicked the flush cache button again to confirm their intent.
            // Delete the cache.
            if ('caches' in window) {
                caches.keys()
                    .then(function(keyList) {
                        return Promise.all(keyList.map(function(key) {
                            return caches.delete(key);
                        }));
                    });

                // Now update the button so it will reload the page (they could do this manually too, but this makes it easy).
                $('#reload-cache').text('Cache is cleared. Please reload when you\'re online to get the latest version.');
                $('#reload-cache').attr('href','javascript:location.reload();');
            }
        }

        function deleteHistory() {
            var nextIndex = localStorage.getItem('<?php echo $_GET["event"]; ?>nextIndex');
            if (nextIndex > 1) {
                for (let i = 1; i < nextIndex; i++) {
                    localStorage.removeItem('<?php echo $_GET["event"]; ?>' + i);
                    localStorage.removeItem('<?php echo $_GET["event"]; ?>' + i + 'title');
                    localStorage.removeItem('<?php echo $_GET["event"]; ?>' + i + 'submitted');
                }
            }
            localStorage.removeItem('<?php echo $_GET["event"]; ?>nextIndex');
        }

        function viewHistory(i) {
            var maxIndex = localStorage.getItem('<?php echo $_GET["event"]; ?>nextIndex')-1;
            if (!i > 0) { i = maxIndex; }

            // get next index
            var historyPrevIndex = i - 1;
            if (historyPrevIndex < 1) { historyPrevIndex = 1; }

            // get previous index
            var historyNextIndex = i + 1;
            if (historyNextIndex > maxIndex) { historyNextIndex = maxIndex; }

            // clear the QR div's previous content.
            $('#modal-actions').html("<button class=\"btn btn-outline-info\" onClick=\"viewHistory(" + historyPrevIndex + ")\"><i class=\"fa-solid fa-chevron-left\"></i> Previous</button><button class=\"btn btn-outline-info\" onClick=\"viewHistory(" + historyNextIndex + ")\">Next ️<i class=\"fa-solid fa-chevron-right\"></i></button>");
            
            // Show submit button for this code, only if it hasn't already been submitted
            if (localStorage.getItem('<?php echo $_GET["event"]; ?>' + i + 'submitted') != "true") {

                // It hasn't been submitted. So check if we're online. If so, enable submit button. If not, show disabled "go online to submit" message.
                $.ajax({url: "/pingTest.php", cache: false, timeout: 500}).done(function( data ) {

                    // response received; probably online
                    $('#modal-actions2').html("<button onClick=\"localStorage.setItem('<?php echo $_GET["event"]; ?>' + " + i + " +'submitted','true');window.location.href='" + localStorage.getItem('<?php echo $_GET["event"]; ?>' + i) + "\';\" class=\"btn btn-outline-success\">Submit</button>");

                }).fail(function(err){
                    // ping failed; offline or server down
                    $('#modal-actions2').html("<button disabled class=\"btn btn-outline-success\">Go online to submit</button>");
                })
            }
            $('#modalQRCode').html("");
            $('#codeModalLabel').text("Submission " + i + ": " + localStorage.getItem('<?php echo $_GET["event"]; ?>' + i + 'title'))

            // Set and show the QR code in the modal.
            new QRCode(document.getElementById("modalQRCode"), {text:localStorage.getItem('<?php echo $_GET["event"]; ?>' + i),width:800,height:800,correctLevel:QRCode.CorrectLevel.L});
            $('#modalQRCode > img').addClass('img-fluid');
            codeModal.show();
        }

    </script>
<?php
} else {
    ?>
        <div class="container">
            <div class="alert alert-warning"><h4>Sorry</h4>You must select an event before providing data.</div>
        </div>
    <?php
}
?>

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/myevents/event/offlineWorker.js')
                .then(registration => {
                    console.log('offlineWorker registration successful with scope: ', registration.scope);
                }, err => {
                    console.log('offlineworker registration failed: ', err);
                });
        });
    }
</script>

<?php
include '../../_footer.php';
?>