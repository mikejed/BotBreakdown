<?php
$authRequired = true;
include '../_dbConnection.php';
include '../_head.php';

echo ("<div class=\"container\">");
    // Unflagging changes data, so it must arrive as a POST with a valid CSRF token
    // (a bare GET link could be triggered by email-scanner prefetching or a crafted page).
    if (isset($_POST["submissionId"])) {
        requireCsrf();
        logHistory("unflagged", json_encode($_POST), "submission", $_POST["submissionId"]);

        $flagRemove = $db->prepare("DELETE FROM `flag` WHERE `submissionId` = ? AND `reportingScouterId` = $currentPersonId;");
        $flagRemove->bind_param("i", $_POST["submissionId"]);
        $flagRemove->execute();

        echo("<div class=\"alert alert-success\">Done</div>");
    } else {
        echo("<div class=\"alert alert-warning\">Invalid request. Please go back and try again.</div>");
    }

echo ("</div>");

include '../_footer.php';
?>
