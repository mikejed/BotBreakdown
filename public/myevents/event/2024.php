<?php
$authRequired = true;
$pageTitle = "Enter " . $_GET["event"] . " Data";
include '../../_dbConnection.php';
include '../../_head.php';
echo("<link href=\"/assets/css/dataStyles.css\" rel=\"stylesheet\" />");

if ( isset($_GET["event"]) ) {
    $firstEventCode = substr($_GET["event"],4);
    // JSON-encoded copy of the event code, safe to embed in JavaScript string contexts.
    $eventJs = json_encode($_GET["event"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    // make this event page available offline
    ?>
    <script src="/myevents/event/offlineWorker.js"></script>
    <?php

    // Get the event name
    $eventNameQuery = $db->prepare("SELECT `eventName` FROM `eventData` WHERE `event` = ?");
    $eventNameQuery->bind_param("s", $_GET["event"]);
    $eventNameQuery->execute();
    $eventNameResult = $eventNameQuery->get_result();
    $eventNameData = $eventNameResult->fetch_all(MYSQLI_ASSOC);
    $eventName = $eventNameData[0]["eventName"];

    if(strlen($eventName) < 1) {
        $eventRequest = curl_init("https://frc-api.firstinspires.org/v3.0/$currentSeason/events?eventCode=" . $firstEventCode);
        curl_setopt_array($eventRequest, $firstCurlOpt);
        $eventContent = json_decode(curl_exec($eventRequest),true);
        $eventName = $eventContent['Events'][0]['name'];

        if(strlen($eventName) > 0) {
            $eventData = $db->prepare("INSERT INTO `eventData` (`event`,`eventName`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `eventName` = ?");
            $eventData->bind_param("sss",$_GET["event"],$eventName,$eventName);
            $eventData->execute();
        } else {
            $eventName = $_GET["event"];
        }
    }

    /*
    <script>
        async function addFormToCache() {
            if ('serviceWorker' in navigator) {
                const swRegistration = await navigator.serviceWorker.ready;
                const cache = await caches.open('offlinePages-0.8');
                // await cache.addAll(["/myevents/<?php echo ($_GET["event"]); ?>","/myevents/dataEntry.php?event=<?php echo ($_GET["event"]); ?>"]);
                console.log('Form (not) Cached');
            }
        }
        addFormToCache();
    </script>
    */
    ?>

    <script src="/assets/js/qrcode.min.js"></script>


    <form id="eventForm" method="post" action="../submit.php">

        <div class="container">

            <div class="row"><div class="col">
                <a class="btn btn-outline-secondary float-end" title="Review my submissions" href="javascript:viewHistory();"><i class="fa-solid fa-clock-rotate-left"></i></a>
                <h1><?php echo e($eventName); ?></h1>
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
                        echo("<option value=\"" . e($team->teamNumber) . "\">" . e($team->teamNumber) . " - " . e($team->nameShort) . "</option>");
                    }
                    echo "</select>";
                    echo "<input type=\"hidden\" name=\"event\" value=\"" . e($_GET["event"]) . "\" />";
                    } else {
?>
                        <input type="number" class="form-control" id="teamNumber" name="teamNumber" min="1" required>
                        <div class="invalid-feedback">Please set the team number.</div>
<?php
                        echo "</div><div class=\"col-md\">
                                <label for=\"event\" class=\"form-label\">Event Code</label>
                                <input type=\"text\" id=\"event\" name=\"event\" value=\"" . e($_GET["event"]) . "\" class=\"form-control\" />
                            ";
                    }
?>
                </div>
            
                <div class="col-md">
                    <label for="match" class="form-label">Match</label>
                    <input type="number" class="form-control" id="match" name="match" min="1" required>
                    <div class="invalid-feedback">Please set the match number.</div>
                </div>
            
                <div class="col-md">
                    <fieldset>
                    <label class="form-label">Level</label>
                    <div class="form-check">
                        <input type="radio" class="form-check-input" id="level-qual" name="level" value="qm" checked>
                        <label for="level-qual" class="form-check-label">Qualification</label>
                    </div>
                </div>
        
            </div><!-- /.row -->

            <?php if (substr($_GET["event"], 0, 4) == $currentSeason) { ?>

                <div class="card card-body bg-body-secondary mt-4">
                    <h2>Autonomous</h2>

                    <div class="row">
                        <div class="col-md">
                            <label class="form-label">Left Starting Zone?</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" id="leftZone-1" name="leftZone" value="Yes">
                                <label class="form-check-label" for="leftZone-1">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" id="leftZone-0" name="leftZone" value="No">
                                <label class="form-check-label" for="leftZone-0">No</label>
                            </div>
                        </div>
                        <div class="col-md">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="autoIntake" name="autoIntake" value="scouted" onChange="$('input[name=\'autoIntake-wing\'').prop('disabled', !this.checked);$('input[name=\'autoIntake-centerline\'').prop('disabled', !this.checked);">
                                <label class="form-label" style="font-weight:bold;" for="autoIntake">Autonomous Intake</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input bb-default-disabled" type="checkbox" id="autoIntake-wing" name="autoIntake-wing" value="Wing" disabled>
                                <label class="form-check-label" for="autoIntake-wing">Wing</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input bb-default-disabled" type="checkbox" id="autoIntake-centerline" name="autoIntake-centerline" value="Centerline" disabled>
                                <label class="form-check-label" for="autoIntake-centerline">Centerline</label>
                            </div>
                        </div>
                    </div><!-- /.row -->
                    
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="autoSpeakerToggle" name="autoSpeakerToggle" value="scouted" onChange="$('#autoSpeakerDecrease').toggleClass('disabled', !this.checked);$('#autoSpeakerIncrease').toggleClass('disabled', !this.checked);$('#autoSpeaker').prop('disabled', !this.checked);">
                        <label class="form-label" style="font-weight:bold;" for="autoSpeakerToggle">Auto Speaker <small style="font-weight: normal;">(Count notes, not points)</small></label>
                    </div>
                    <div class="row">
                        <div class="col">
                            <a class="btn btn-big btn-danger form-control bb-default-disabled disabled" id="autoSpeakerDecrease" onClick="changeValue($('#autoSpeaker'),-1)">-1</a>
                        </div>
                        <div class="col">
                            <input type="number" class="form-control mt-2 input-lg bb-default-disabled" id="autoSpeaker" name="autoSpeaker" min="0" value="0" disabled>
                        </div>
                        <div class="col">
                            <a class="btn btn-big btn-success form-control bb-default-disabled disabled" id="autoSpeakerIncrease" onClick="changeValue($('#autoSpeaker'),1)">+1</a>
                        </div>
                    </div><!-- /.row -->

                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="autoAmpToggle" name="autoAmpToggle" value="scouted" onChange="$('#autoAmpDecrease').toggleClass('disabled', !this.checked);$('#autoAmpIncrease').toggleClass('disabled', !this.checked);$('#autoAmp').prop('disabled', !this.checked);">
                        <label class="form-label" style="font-weight:bold;" for="autoAmpToggle">Auto Amp <small style="font-weight: normal;">(Count notes, not points)</small></label>
                    </div>
                    <div class="row">
                        <div class="col">
                            <a class="btn btn-big btn-danger form-control bb-default-disabled disabled" id="autoAmpDecrease"onClick="changeValue($('#autoAmp'),-1)">-1</a>
                        </div>
                        <div class="col">
                            <input type="number" class="form-control mt-2 input-lg bb-default-disabled" id="autoAmp" name="autoAmp" min="0" value="0" disabled>
                        </div>
                        <div class="col">
                            <a class="btn btn-big btn-success form-control bb-default-disabled disabled" id="autoAmpIncrease" onClick="changeValue($('#autoAmp'),1)">+1</a>
                        </div>
                    </div><!-- /.row -->

                </div><!-- /.card -->
                
                <div class="card card-body bg-body-secondary mt-4">
                    <h2>Teleop</h2>

                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="speakerToggle" name="speakerToggle" value="scouted" onChange="$('#speakerDecrease').toggleClass('disabled', !this.checked);$('#speakerIncrease').toggleClass('disabled', !this.checked);$('#speaker').prop('disabled', !this.checked);">
                        <label class="form-label" style="font-weight:bold;" for="speakerToggle">Speaker <small style="font-weight: normal;">(Count notes, not points)</small></label>
                    </div>
                    <div class="row">
                        <div class="col">
                            <a class="btn btn-big btn-danger form-control bb-default-disabled disabled" id="speakerDecrease" onClick="changeValue($('#speaker'),-1)">-1</a>
                        </div>
                        <div class="col">
                            <input type="number" class="form-control mt-2 input-lg bb-default-disabled" id="speaker" name="speaker" min="0" value="0" disabled>
                        </div>
                        <div class="col">
                            <a class="btn btn-big btn-success form-control bb-default-disabled disabled" id="speakerIncrease" onClick="changeValue($('#speaker'),1)">+1</a>
                        </div>
                    </div><!-- /.row -->
                    
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="ampToggle" name="ampToggle" value="scouted" onChange="$('#ampDecrease').toggleClass('disabled', !this.checked);$('#ampIncrease').toggleClass('disabled', !this.checked);$('#amp').prop('disabled', !this.checked);">
                        <label class="form-label" style="font-weight:bold;" for="ampToggle">Amp <small style="font-weight: normal;">(Count notes, not points)</small></label>
                    </div>
                    <div class="row">
                        <div class="col">
                            <a class="btn btn-big btn-danger form-control bb-default-disabled disabled" id="ampDecrease" onClick="changeValue($('#amp'),-1)">-1</a>
                        </div>
                        <div class="col">
                            <input type="number" class="form-control mt-2 input-lg bb-default-disabled" id="amp" name="amp" min="0" value="0" disabled>
                        </div>
                        <div class="col">
                            <a class="btn btn-big btn-success form-control bb-default-disabled disabled" id="ampIncrease" onClick="changeValue($('#amp'),1)">+1</a>
                        </div>
                    </div><!-- /.row -->
                    
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="trapToggle" name="trapToggle" value="scouted" onChange="$('#trapDecrease').toggleClass('disabled', !this.checked);$('#trapIncrease').toggleClass('disabled', !this.checked);$('#trap').prop('disabled', !this.checked);">
                        <label class="form-label" style="font-weight:bold;" for="trapToggle">Trap <small style="font-weight: normal;">(Count notes, not points)</small></label>
                    </div>
                    <div class="row">
                        <div class="col">
                            <a class="btn btn-big btn-danger form-control bb-default-disabled disabled" id="trapDecrease" onClick="changeValue($('#trap'),-1)">-1</a>
                        </div>
                        <div class="col">
                            <input type="number" class="form-control mt-2 input-lg bb-default-disabled" id="trap" name="trap" min="0" value="0" disabled>
                        </div>
                        <div class="col">
                            <a class="btn btn-big btn-success form-control bb-default-disabled disabled" id="trapIncrease" onClick="changeValue($('#trap'),1)">+1</a>
                        </div>
                    </div><!-- /.row -->

                </div><!-- /.card -->
                
                <div id="helpGraphic" style="display: none;">
                    <img src="/assets/images/2024/2024-shootingRegions.png" class="img-fluid m-3" title="Shooting Region key" onClick="$('#helpGraphic').slideToggle();" />
                </div>

                <div class="card card-body bg-body-secondary mt-4">
                    <h2>Information</h2>
                    
                    <div class="row">

                        <div class="col-md">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="intake" name="intake" value="scouted" onChange="$('input[name=\'intake-floor\'').prop('disabled', !this.checked);$('input[name=\'intake-source\'').prop('disabled', !this.checked);">
                                <label class="form-label" style="font-weight:bold;" for="intake">Intake</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input bb-default-disabled" type="checkbox" id="intake-floor" name="intake-floor" value="Floor" disabled>
                                <label class="form-check-label" for="intake-floor">Ground</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input bb-default-disabled" type="checkbox" id="intake-source" name="intake-source" value="Source" disabled>
                                <label class="form-check-label" for="intake-source">Source Chute</label>
                            </div>
                        </div>

                        <div class="col-md">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="shoots" name="shoots" value="scouted" onChange="$('input[name=\'shoots-podium\'').prop('disabled', !this.checked);$('input[name=\'shoots-centerline\'').prop('disabled', !this.checked);$('input[name=\'shoots-subwoofer\'').prop('disabled', !this.checked);$('input[name=\'shoots-wing\'').prop('disabled', !this.checked);";>
                                <label class="form-label" style="font-weight:bold;" for="shoots">Shooting Positions</label>
                                <i class="fa-solid fa-question-circle" onClick="$('#helpGraphic').slideToggle();"></i>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input bb-default-disabled" type="checkbox" id="shoots-subwoofer" name="shoots-subwoofer" value="Subwoofer" disabled>
                                <label class="form-check-label" for="shoots-subwoofer">Subwoofer</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input bb-default-disabled" type="checkbox" id="shoots-podium" name="shoots-podium" value="Podium" disabled>
                                <label class="form-check-label" for="shoots-podium">Podium</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input bb-default-disabled" type="checkbox" id="shoots-wing" name="shoots-wing" value="Wing" disabled>
                                <label class="form-check-label" for="shoots-wing">Wing</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input bb-default-disabled" type="checkbox" id="shoots-centerline" name="shoots-centerline" value="Centerline" disabled>
                                <label class="form-check-label" for="shoots-centerline">Centerline</label>
                            </div>
                        </div>

                        <div class="col-md">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="role" name="role" value="scouted" onChange="$('input[name=\'role-defense\'').prop('disabled', !this.checked);$('input[name=\'role-offense\'').prop('disabled', !this.checked);$('input[name=\'role-feeder\'').prop('disabled', !this.checked);$('input[name=\'role-other\'').prop('disabled', !this.checked);">
                                <label class="form-label" style="font-weight:bold;" for="role">Role</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input bb-default-disabled" type="checkbox" id="role-defense" name="role-defense" value="Defense" disabled>
                                <label class="form-check-label" for="role-defense">Defense</label>
                            </div>
                            <div class="col-md">
                                <div class="form-check">
                                    <input class="form-check-input bb-default-disabled" type="checkbox" id="role-offense" name="role-offense" value="Offense" disabled>
                                    <label class="form-check-label" for="role-offense">Offense</label>
                                </div>
                            </div>
                            <div class="col-md">
                                <div class="form-check">
                                    <input class="form-check-input bb-default-disabled" type="checkbox" id="role-feeder" name="role-feeder" value="Feeder" disabled>
                                    <label class="form-check-label" for="role-feeder">Feeder</label>
                                </div>
                            </div>
                            <div class="col-md">
                                <div class="form-check">
                                    <input class="form-check-input bb-default-disabled" type="checkbox" id="role-other" name="role-other" value="Other" disabled>
                                    <label class="form-check-label" for="role-other">Other</label>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="row">

                        <div class="col-md">
                            <label class="form-label">Climb</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" id="climb-0" name="climb" value="No">
                                <label class="form-check-label" for="climb-0">No</label>
                            </div>
                            <div class="col-md">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="climb-1" name="climb" value="First">
                                    <label class="form-check-label" for="climb-1">First</label>
                                </div>
                            </div>
                            <div class="col-md">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="climb-2" name="climb" value="Second">
                                    <label class="form-check-label" for="climb-2">Second (Harmonized)</label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md">
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

                        <div class="col-md">
                            <label class="form-label">Disabled</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" id="disabled-1" name="disabled" value="Disabled">
                                <label class="form-check-label" for="disabled-1">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" id="disabled-0" name="disabled" value="No">
                                <label class="form-check-label" for="disabled-0">No</label>
                            </div>
                        </div>

                    </div><!-- /.row -->
                </div><!-- /.card -->
            
                <div class="row">
                    <div class="col-md-8">
                        <label class="form-label" for="comment">Comment</label>
                        <textarea class="form-control" id="comment" name="comment" rows="6"></textarea>
                    </div>
                    <div class="col-md-4">
                        <?php 
                        /*
                        <input type="submit" value="Submit" class="mt-5" />
                        <a href="javascript:pingTest();">pingTest</a>
                        */
                        ?>
                        <a href="javascript:doSubmit();" class="btn btn-primary btn-big form-control mt-5">Save</a>
                        <input class="btn btn-secondary form-control mt-4" type="reset" value="Reset form" onClick="$('input.bb-default-disabled').attr('disabled', true);$('a.bb-default-disabled').addClass('disabled');" />
                        <div id="submitError" class="alert alert-warning" style="display: none;"><i class="fa-solid fa-triangle-exclamation fa-3x fa-pull-left"></i>Missing data- please check the event, team, and match. <a href="#" class="btn btn-default btn-sm"><i class="fa-solid fa-turn-up"></i></a></div>
                    </div>
                </div><!-- /.row -->
            <?php }
                else { ?>
                <div class="alert alert-info"><h4>Where we're going we don't need forms...</h4>Hmm, you loaded an event from a different year. Only time travelers would need to enter data here, so we assume you just need to view the history of your submissions on this device, or to check the list of which teams were at this event.</div>
            <?php } ?>

<!--
            <hr class="my-5">
            <div class="row"><input type="reset" value="Reset form" onClick="$('input.bb-default-disabled').attr('disabled', true);$('a.bb-default-disabled').addClass('disabled');" /></div>
-->
        </div><!-- /.container-->
        
    </form>

    <hr>
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
                        <div><strong>Delete my <?php echo e($_GET["event"]); ?> QR Code history</strong></div>
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

    <script type="text/javascript">

        function changeValue(ctl,val) {
            // Updates the input data numbers when you click [+] or [-]
            ctl.val( isNaN(parseInt(ctl.val())) ? 1 : parseInt(ctl.val()) + val );
            if (ctl.val() < 0) {ctl.val("0")}
        }

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
            var nextIndex = localStorage.getItem((<?php echo $eventJs; ?> + 'nextIndex'));
            if (nextIndex > 1) {
                for (let i = 1; i < nextIndex; i++) {
                    localStorage.removeItem(<?php echo $eventJs; ?> + i);
                    localStorage.removeItem(<?php echo $eventJs; ?> + i + 'title');
                    localStorage.removeItem(<?php echo $eventJs; ?> + i + 'submitted');
                }
            }
            localStorage.removeItem((<?php echo $eventJs; ?> + 'nextIndex'));
        }

        const codeModal = new bootstrap.Modal('#codeModal', {});
        function doSubmit() {
            $('body').addClass('was-validated');
            if (
                $('#teamNumber:valid').length > 0
                && $('#match:valid').length > 0
                && $("input[Name='level']:valid").length > 0
            ){
                
                $('#submitError').hide();
                // Store the URL in localStorage. Set/get the nextIndex
                if (!localStorage.getItem((<?php echo $eventJs; ?> + 'nextIndex')) > 0) {
                    localStorage.setItem((<?php echo $eventJs; ?> + 'nextIndex'),1);
                }
                var thisIndex = localStorage.getItem((<?php echo $eventJs; ?> + 'nextIndex'));

                // Set the URL of the submit page including data in GET string.
                var dataSubmitUrl = 'https://www.botbreakdown.com/myevents/submit.php?' + $('#eventForm').serialize();
                localStorage.setItem(<?php echo $eventJs; ?> + thisIndex, dataSubmitUrl);

                // Set the match name.
                var matchTitle = 'Match ' + $('#match').val() + ', Team ' + $('#teamNumber').val();
                localStorage.setItem(<?php echo $eventJs; ?> + thisIndex + 'title', matchTitle)

                // Increment the nextIndex in localStorage
                localStorage.setItem((<?php echo $eventJs; ?> + 'nextIndex'),thisIndex*1+1);

                // Now check if we're online. If so, submit. If not, show QR code.
                $.ajax({url: "/pingTest.php", cache: false, timeout: 1500}).done(function( data ) {
                    // Probably online (Note: data contains result)

                    // Indicate this URL has been submitted.
                    localStorage.setItem(<?php echo $eventJs; ?> + thisIndex+'submitted','true');

                    // Then actually submit it.
                    $('#eventForm').submit();

                }).fail(function(err) {
                    // Offline or server down.

                    // Indicate this URL hasn't been submitted.
                    localStorage.setItem(<?php echo $eventJs; ?> + thisIndex+'submitted','false');

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
        }

        function viewHistory(i) {
            var maxIndex = localStorage.getItem((<?php echo $eventJs; ?> + 'nextIndex'))-1;
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
            if (localStorage.getItem(<?php echo $eventJs; ?> + i + 'submitted') != "true") {

                // It hasn't been submitted. So check if we're online. If so, enable submit button. If not, show disabled "go online to submit" message.
                $.ajax({url: "/pingTest.php", cache: false, timeout: 500}).done(function( data ) {

                    // response received; probably online
                    var submitButton = $('<button class="btn btn-outline-success">Submit</button>').on('click', function() {
                        localStorage.setItem(<?php echo $eventJs; ?> + i + 'submitted', 'true');
                        window.location.href = localStorage.getItem(<?php echo $eventJs; ?> + i);
                    });
                    $('#modal-actions2').empty().append(submitButton);

                }).fail(function(err){
                    // ping failed; offline or server down
                    $('#modal-actions2').html("<button disabled class=\"btn btn-outline-success\">Go online to submit</button>");
                })
            }
            $('#modalQRCode').html("");
            $('#codeModalLabel').text("Submission " + i + ": " + localStorage.getItem(<?php echo $eventJs; ?> + i + 'title'))

            // Set and show the QR code in the modal.
            new QRCode(document.getElementById("modalQRCode"), {text:localStorage.getItem(<?php echo $eventJs; ?> + i),width:800,height:800,correctLevel:QRCode.CorrectLevel.L});
            $('#modalQRCode > img').addClass('img-fluid');
            codeModal.show();
        }


        // NOTE: No longer using the below function but I'm leaving it here for the moment in case it proves useful.
        /*!
        * jQuery serializeObject - v0.2 - 1/20/2010
        * http://benalman.com/projects/jquery-misc-plugins/
        * 
        * Copyright (c) 2010 "Cowboy" Ben Alman
        * Dual licensed under the MIT and GPL licenses.
        * http://benalman.com/about/license/
        */

        // Whereas .serializeArray() serializes a form into an array, .serializeObject()
        // serializes a form into an (arguably more useful) object.

        (function($,undefined){
        '$:nomunge'; // Used by YUI compressor.
        
        $.fn.serializeObject = function(){
            var obj = {};
            
            $.each( this.serializeArray(), function(i,o){
            var n = o.name,
                v = o.value;
                
                obj[n] = obj[n] === undefined ? v
                : $.isArray( obj[n] ) ? obj[n].concat( v )
                : [ obj[n], v ];
            });
            
            return obj;
        };
        
        })(jQuery);
        // If I want to get a JSON representation of the form, use this like JSON.stringify($('#eventForm').serializeObject())

    </script>

<?php
} else {
    ?>
        <div class="container">
            <div class="alert alert-warning"><h4>Sorry</h4>You must select an event before providing data.</div>
        </div>
    <?php
}
include '../../_footer.php'; ?>