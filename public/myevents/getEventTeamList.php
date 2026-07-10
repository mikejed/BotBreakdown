<?php
$authRequired = true;
$pageTitle = "Enter " . $_GET["event"] . " Data";
include '../_dbConnection.php';

echo("<!doctype HTML><html><body><table border=\"1\"><thead><tr><th>Team Number</th><th>Team Name</th></tr></thead><tbody>");

if ( isset($_GET["event"]) ) {
    $firstEventCode = substr($_GET["event"],4);
    $requestedSeason = substr($_GET['event'],0,4) + 0;

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
        $eventRequest = curl_init("https://frc-api.firstinspires.org/v3.0/$requestedSeason/events?eventCode=" . $firstEventCode);
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
                    
    $eventRequest = curl_init("https://frc-api.firstinspires.org/v3.0/$requestedSeason/teams?eventCode=" . $firstEventCode);
    curl_setopt_array($eventRequest, $firstCurlOpt);
    $content = curl_exec($eventRequest);
    $err     = curl_errno($eventRequest);
    $errmsg  = curl_error($eventRequest);
    if ( str_starts_with($content, "{") && json_decode($content)->teamCountTotal > 0 ) {

        $a_Teams = json_decode($content)->teams;
        // make sure if there are more pages of teams that we get them all.
        $i_pages = json_decode($content)->pageTotal;
        for ($i=2; $i<=$i_pages; $i++) {
            $eventRequest = curl_init("https://frc-api.firstinspires.org/v3.0/$requestedSeason/teams?eventCode=" . $firstEventCode . "&page=" . $i);
            curl_setopt_array($eventRequest, $firstCurlOpt);
            $content = curl_exec($eventRequest);
            $a_Teams = array_merge($a_Teams, json_decode($content)->teams);
        }

        foreach( $a_Teams as $team ) {
            echo("<tr><td>" . e($team->teamNumber) . "</td><td>" . e($team->nameShort) . "</td></tr>");
        }
        echo("</tbody></table></body></html>");
    }
}
else {
    echo("</tbody></table><h1>Invalid event</h1></body></html>");
}