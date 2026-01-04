<?php
$pageTitle = 'My Events';
$authRequired = true;
include '../_dbConnection.php';
include '../_head.php';
?>
    

    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <a href="new.php" class="btn btn-outline-secondary float-end"><i class="fa-solid fa-plus"></i></a>
                <h3 class="card-title mb-0">My Events</h3>
            </div>
            <div class="card-body p-0">
                <div class="container">
                    <div class="row">
                        <div class="col">
                
<?php
                            // First get all of the events this person has submitted data for in this season
                            $sql = "SELECT
                                        tm.`event`
                                        , e.`eventName`
                                    FROM
                                        `teamMatch` tm
                                        INNER JOIN `submission` sub ON tm.`id` = sub.`teamMatchId`
                                        LEFT OUTER JOIN `eventData` e ON tm.`event` = e.`event`
                                    WHERE
                                        sub.`scouterId` = $currentPersonId
                                        AND tm.`event` LIKE '" . $currentSeason . "%'
                                        AND tm.`match` > 0
                                    GROUP BY
                                        tm.`event`
                                        , e.`eventName`
                                    ;";
                            $result = $db->query($sql);

                            if ($result->num_rows > 0) {
?>
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Event</th>
                                            <th class="d-none d-md-table-cell">Code</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
<?php
                                        // output data of each row
                                        while($row = $result->fetch_assoc()) {
                                            if (strlen($row["eventName"]) > 3) {
                                                $eventNameOrCode = $row["eventName"];
                                            } else {
                                                $eventNameOrCode = $row["event"];
                                            }
                                            echo "<tr>
                                                    <td>" . $eventNameOrCode . "</td>
                                                    <td class=\"d-none d-md-table-cell\">" . $row["event"] . "</td>
                                                    <td class=\"text-end\">
                                                        <a href=\"viewData.php?event=" . $row["event"] . "\" class=\"btn btn-primary\" title=\"View Data\"><i class=\"fa-solid fa-file-lines\"></i> View Data</a>
                                                        <a href=\"getRawData.php?event=" . $row["event"] . "\" class=\"btn btn-primary\" title=\"Download CSV\"><i class=\"fa-solid fa-table\"></i> Download .csv</a>
                                                    </td>
                                                </tr>";
                                        }
?>
                                    </tbody>
                                </table>
<?php
                            } else {
                                echo ('<div class="alert alert-info mt-3"><h5>No events for this season yet.</h5><p>Welcome to the My Events page! Once you contribute some data for at least one match at an event, the event will be added to this list. Then you\'ll be able to view and/or download all of that event\'s data from here.</p><p>Click the + button above to choose an event and start adding data.</p><p>If you want to test submitting without logging data on an actual event, may we recommend <a href="/myevents/event/2026TEST">2026TEST</a> 😁</p></div>');
                            }

?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

                
<?php
    // Now show the matches you're only following.
    $sql = "SELECT
                tm.`event`
                , e.`eventName`
            FROM
                `teamMatch` tm
                INNER JOIN `submission` sub ON tm.`id` = sub.`teamMatchId`
                LEFT OUTER JOIN `eventData` e ON tm.`event` = e.`event`
            WHERE
                sub.`scouterId` = $currentPersonId
                AND tm.`event` LIKE '" . $currentSeason . "%'
                AND tm.`match` = 0
            GROUP BY
                tm.`event`
                , e.`eventName`
            ;";
    $result = $db->query($sql);

    if ($result->num_rows > 0) {
?>
        <hr class="mt-5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">Events I'm Watching</h3>
            </div>
            <div class="card-body p-0">
                <div class="container">
                    <div class="row">
                        <div class="col">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th class="d-none d-md-table-cell">Code</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
<?php
                                    // output data of each row
                                    while($row = $result->fetch_assoc()) {
                                        if (strlen($row["eventName"]) > 3) {
                                            $eventNameOrCode = $row["eventName"];
                                        } else {
                                            $eventNameOrCode = $row["event"];
                                        }
                                        echo "<tr>
                                                <td>" . $eventNameOrCode . "</td>
                                                <td class=\"d-none d-md-table-cell\">" . $row["event"] . "</td>
                                                <td class=\"text-end\">
                                                    <a href=\"viewData.php?event=" . $row["event"] . "\" class=\"btn btn-primary\" title=\"View Data\"><i class=\"fa-solid fa-file-lines\"></i> View Data</a>
                                                    <a href=\"getRawData.php?event=" . $row["event"] . "\" class=\"btn btn-primary\" title=\"Download CSV\"><i class=\"fa-solid fa-table\"></i> Download .csv</a>
                                                </td>
                                            </tr>";
                                    }
?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php
    }
?>
    </div>

<?php include '../_footer.php'; ?>