<?php
$authRequired = true;
include '../_dbConnection.php';
include '../_head.php';

function customSort($a, $b) {
    // First, compare by dateStart (by turning eg. 2024-05-24T00:00:00 into 20240524 for easy comparison)
    $weekComparison = substr(str_replace('-','',$a['dateStart']),0,8) - substr(str_replace('-','',$b['dateStart']),0,8);
    
    // If the dateStarts are the same, compare by Name
    if ($weekComparison == 0) {
        return strcmp($a['name'], $b['name']);
    }
    
    return $weekComparison;
}


$eventRequest = curl_init("https://frc-api.firstinspires.org/v3.0/" . $currentSeason . "/events");
curl_setopt_array($eventRequest, $firstCurlOpt);

$content = curl_exec($eventRequest);
$err     = curl_errno($eventRequest);
$errmsg  = curl_error($eventRequest);

?>
<div class="container">
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item"><a href="javascript:$('.eventCard').show();$('.nav-link').removeClass('active');$('#navWeekAll').addClass('active');" id="navWeekAll" class="nav-link active">Show all</a></li>
        <li class="nav-item"><a href="javascript:$('.eventCard').hide();$('.week1').show();$('.nav-link').removeClass('active');$('#navWeek1').addClass('active');" id="navWeek1" class="nav-link">Week 1</a></li>
        <li class="nav-item"><a href="javascript:$('.eventCard').hide();$('.week2').show();$('.nav-link').removeClass('active');$('#navWeek2').addClass('active');" id="navWeek2" class="nav-link">Week 2</a></li>
        <li class="nav-item"><a href="javascript:$('.eventCard').hide();$('.week3').show();$('.nav-link').removeClass('active');$('#navWeek3').addClass('active');" id="navWeek3" class="nav-link">Week 3</a></li>
        <li class="nav-item"><a href="javascript:$('.eventCard').hide();$('.week4').show();$('.nav-link').removeClass('active');$('#navWeek4').addClass('active');" id="navWeek4" class="nav-link">Week 4</a></li>
        <li class="nav-item"><a href="javascript:$('.eventCard').hide();$('.week5').show();$('.nav-link').removeClass('active');$('#navWeek5').addClass('active');" id="navWeek5" class="nav-link">Week 5</a></li>
        <li class="nav-item"><a href="javascript:$('.eventCard').hide();$('.week6').show();$('.nav-link').removeClass('active');$('#navWeek6').addClass('active');" id="navWeek6" class="nav-link">Week 6</a></li>
        <li class="nav-item"><a href="javascript:$('.eventCard').hide();$('.week7').show();$('.nav-link').removeClass('active');$('#navWeek7').addClass('active');" id="navWeek7" class="nav-link">Week 7</a></li>

        <li class="nav-item"><a href="javascript:$('.eventCard').hide();$('.week9').show();$('.nav-link').removeClass('active');$('#navWeek8').addClass('active');" id="navWeek8" class="nav-link">Finals</a></li>

        <li class="nav-item"><a href="javascript:$('.eventCard').hide();$('.post05').show();$('.nav-link').removeClass('active');$('#nav05').addClass('active');" id="nav05" class="nav-link">May</a></li>
        <li class="nav-item"><a href="javascript:$('.eventCard').hide();$('.post06').show();$('.nav-link').removeClass('active');$('#nav06').addClass('active');" id="nav06" class="nav-link">June</a></li>
        <li class="nav-item"><a href="javascript:$('.eventCard').hide();$('.post07').show();$('.nav-link').removeClass('active');$('#nav07').addClass('active');" id="nav07" class="nav-link">July</a></li>
        <li class="nav-item"><a href="javascript:$('.eventCard').hide();$('.post08').show();$('.nav-link').removeClass('active');$('#nav08').addClass('active');" id="nav08" class="nav-link">August</a></li>
        <li class="nav-item"><a href="javascript:$('.eventCard').hide();$('.post09').show();$('.nav-link').removeClass('active');$('#nav09').addClass('active');" id="nav09" class="nav-link">September</a></li>
        <li class="nav-item"><a href="javascript:$('.eventCard').hide();$('.post10').show();$('.nav-link').removeClass('active');$('#nav10').addClass('active');" id="nav10" class="nav-link">October</a></li>
        <li class="nav-item"><a href="javascript:$('.eventCard').hide();$('.post11').show();$('.nav-link').removeClass('active');$('#nav11').addClass('active');" id="nav11" class="nav-link">November</a></li>
        <li class="nav-item"><a href="javascript:$('.eventCard').hide();$('.post12').show();$('.nav-link').removeClass('active');$('#nav12').addClass('active');" id="nav12" class="nav-link">December</a></li>
    </ul>
    <div class="row d-flexbox">
    
        <?php
        if ($err == 0 ) {
            $a_Events = json_decode($content, true)["Events"];
            usort($a_Events, 'customSort');
            // $a_Events = json_decode($content) -> Events;

            // Get all of the events that the current scouter is watching
            $watchedEvents = $db->prepare("SELECT DISTINCT tm.`event` FROM `teamMatch` tm INNER JOIN `submission` s ON s.`teamMatchId` = tm.`id` WHERE s.`scouterId` = ? AND tm.`event` LIKE '" . $currentSeason . "%' AND tm.`match` = 0");
            $watchedEvents->bind_param("i", $currentPersonId);
            $watchedEvents->execute();
            $watchedEventsResult = $watchedEvents->get_result();
            $watchedEventsData = $watchedEventsResult->fetch_all(MYSQLI_ASSOC);

            // flatten the list of events the scouter is watching so we can use in_array()
            $watchedEventsList = array_map(function($obj) {
                return $obj["event"];
            }, $watchedEventsData);

            // Now loop through all of the events and show the cards
            foreach( $a_Events as $event ) {
                if ($event["weekNumber"] != 0) {
                    $class="week" . $event["weekNumber"];
                    $schedule = "<strong>Week</strong> " . $event["weekNumber"] . "<br>";
                } else {
                    $class="post" . substr($event["dateStart"],5,2);
                    $schedule = "<strong>Start Date</strong> <script>var startDate = new Date('" . $event["dateStart"] . "');document.write(startDate.toLocaleString().split(',')[0]);</script><br>";
                }

                if (in_array($currentSeason . $event["code"], $watchedEventsList)) {
                    $watchClass = 'btn-outline-warning';
                } else {
                    $watchClass = 'btn-outline-secondary';
                }

                echo "  <div class=\"col-md-3 mb-4 eventCard " . $class . "\">
                            <div class=\"card h-100 flex-grow-1\">
                                <div class=\"card-header\">
                                    <h5 class=\"card-title\">" . $event["name"] . "</h5>
                                </div>
                                <div class=\"card-body\">
                                    " . $schedule . "
                                    <strong>Location:</strong><br>
                                    " . $event["venue"] . "<br>
                                    " . $event["address"] . "<br>
                                    " . $event["city"] . " " . $event["stateprov"] . "
                                </div>
                                <div class=\"card-footer text-end\">
                                    <a href=\"#!\" class=\"btn $watchClass watch-toggle float-start\" data-event=\"". $currentSeason . $event["code"] . "\" title=\"Add this to events I'm watching\"><i class=\"fa-regular fa-eye\"></i></a>
                                    <a href=\"event/" . $currentSeason . $event["code"] . "\" class=\"btn btn-outline-secondary\" role=\"button\">Add Data</a>
                                </div>
                            </div>
                        </div>";

            }
        }

        curl_close($eventRequest);

        ?>

    </div>
</div>

<script>
    $(document).ready(function() {
        $(document).on('click', '.watch-toggle', function(e) {
            e.preventDefault(); // Prevent default anchor click behavior

            var $button = $(this); // The button that was clicked
            var event = $button.data('event'); // Get the event from the data attribute

            $.ajax({
                url: 'watchEvent.php',
                type: 'GET',
                data: { event: event }, // Send the event ID as a parameter
                success: function(response) {
                    $button.replaceWith(response);
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error: ' + status + error);
                }
            });
        });
    });
</script>
<?php include '../_footer.php'; ?>