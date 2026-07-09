<?php
header('Expires: Thu, 1 Jan 1970 00:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0',false);
header('Pragma: no-cache');

include '../_dbConnection.php';
if ($currentPersonId > 0 && strlen($_GET["event"]) > 6) {
    $eventWatch = $db->prepare("SELECT count(1) AS `count` FROM `teamMatch` tm INNER JOIN `submission` s ON s.`teamMatchId` = tm.`id` WHERE tm.`event` = ? AND tm.`match` = 0 AND s.`scouterId` = ?");
    $eventWatch->bind_param("si", $_GET["event"], $currentPersonId);
    $eventWatch->execute();
    $eventWatchResult = $eventWatch->get_result();
    $eventWatchData = $eventWatchResult->fetch_all(MYSQLI_ASSOC);

    if ($eventWatchData[0]["count"] > 0) {
        // already watching the event: remove their submission for match 0.
        $watchSubmissionDelete = $db->prepare("DELETE s FROM `submission` AS `s` INNER JOIN `teamMatch` AS `tm` ON tm.`id` = s.`teamMatchId` WHERE tm.`event` = ? AND s.`scouterId` = ? AND tm.`level` = '' AND tm.`teamNumber` = '' AND tm.`match` = 0");
        $watchSubmissionDelete->bind_param("si", $_GET["event"], $currentPersonId);
        $watchSubmissionDelete->execute();

        echo('<a class="btn btn-outline-secondary watch-toggle float-start" data-event="' . e($_GET["event"]) . '" title="Add this to events I\'m watching"><i class="fa-solid fa-eye"></i></a>');

    } else {
        // Not watching the event already: create a teamMatch record and a submission for match 0 if it doesn't already exist.
        $teamMatchCreate = $db->prepare("INSERT IGNORE INTO `teamMatch` (`event`, `level`, `match`, `teamNumber`) VALUES (?, '', '0', '');");
        $teamMatchCreate->bind_param("s", $_GET["event"]);
        $teamMatchCreate->execute();

        // Get the Id of that teamMatch
        $teamMatch = $db->prepare("SELECT `id` FROM `teamMatch` WHERE `event` = ? AND `level` = '' AND `match` = 0 AND `teamNumber` = ''");
        $teamMatch->bind_param("s", $_GET["event"]);
        $teamMatch->execute();
        $teamMatchResult = $teamMatch->get_result();
        $teamMatchData = $teamMatchResult->fetch_all(MYSQLI_ASSOC);

        // Now create a submission using that teamMatch
        $newEventWatch = $db->prepare("INSERT INTO `submission` (`teamMatchId`, `scouterId`) VALUES (?, ?);");
        $newEventWatch->bind_param("si", $teamMatchData[0]["id"], $currentPersonId);
        $newEventWatch->execute();

        echo('<a class="btn btn-outline-warning watch-toggle float-start" data-event="' . e($_GET["event"]) . '" title="Remove this event from my watch list"><i class="fa-solid fa-eye"></i></a>');
    }

} else {
    echo('<a class="btn btn-danger float-start" title="Invalid request. Make sure you are logged in and choosing a valid match."><i class="fa-solid fa-triangle-exclamation"></i></a>');
}

?>