<?php
$authRequired = true;
include '../_dbConnection.php';
include '../_head.php';

$flagCreate = $db->prepare("INSERT INTO `flag`(`submissionId`,`reportingScouterId`,`flagDateTime`,`reviewNote`) VALUES (?, ?, CURRENT_TIMESTAMP, ?);");

$flagCheck = $db->prepare("SELECT count(1) AS `flags` FROM `flag` WHERE `submissionId` = ? AND `reportingScouterId` = $currentPersonId");

$getRecentFlagCount = $db->prepare("SELECT count(1) AS `flags` FROM `flag` WHERE `reportingScouterId` = $currentPersonId AND `flagDateTime` > DATE_SUB(NOW(), INTERVAL 4 DAY);");
$getRecentFlagCount->execute();
$getRecentFlagCountResult = $getRecentFlagCount->get_result();
$getRecentFlagCountResultData = $getRecentFlagCountResult->fetch_all(MYSQLI_ASSOC);
$recentFlagCount = $getRecentFlagCountResultData[0]["flags"];
?>

<div class="container">
    <?php
        if (isset($_GET["submissionId"])) {

                $flagCheck->bind_param("i", $_GET["submissionId"]);
                $flagCheck->execute();
                $flagCheckResult = $flagCheck->get_result();
                $flagCheckResultData = $flagCheckResult->fetch_all(MYSQLI_ASSOC);
                $flagExists = $flagCheckResultData[0]["flags"];

                if ($flagExists == 0) {
                    // show the form to let them flag a submission.
                    if ($flagLimit - $recentFlagCount > 0 || $isAdmin) {
                    
                        echo("<div class=\"alert alert-info\">You have flagged $recentFlagCount submissions in the last (4) days. ");
                        if ($asAdmin) {
                            echo("You are an administrator so you're not limited in the number of flags you submit.</div>");
                        } else {
                            echo("You may not flag more than $flagLimit, so if you submit this, you will have " . $flagLimit - $recentFlagCount - 1 . " items left you can flag.</div>");
                        }
                        ?>
                        <form method="post" action="flagSubmission.php">
                            <input type="hidden" name="submissionId" value="<?php echo e($_GET["submissionId"]); ?>">
                            <label class="form-label">Review Reason</label>
                            <textarea name="reviewReason" class="form-control"></textarea>
                            <input type="submit" />
                        </form>
                        <?php
                    } else {
                        echo("<div class=\"alert alert-warning\">You have flagged $recentFlagCount submissions in the last (4) days. You have reached the limit of the items you can flag for now.</div>");
                    }
                } else {
                    echo("<div class=\"alert alert-warning\">Sorry, it looks like you've already flagged this submission.</div>");
                }

        // they've submitted a flag report.
        } else if (isset($_POST["submissionId"]) && isset($_POST["reviewReason"])) {
            if ($recentFlagCount >= $flagLimit) {
                echo("<div class=\"alert alert-warning\">Sorry, you have reached the limit of the items you can flag for now.</div>");
                logHistory("flagLimitReached", json_encode($_REQUEST), "submission", $_POST["submissionId"]);
            } else {
                $flagCheck->bind_param("i", $_POST["submissionId"]);
                $flagCheck->execute();
                $flagCheckResult = $flagCheck->get_result();
                $flagCheckResultData = $flagCheckResult->fetch_all(MYSQLI_ASSOC);
                $flagExists = $flagCheckResultData[0]["flags"];

                if ($flagExists == 0) {
                    $flagCreate->bind_param("iis", $_POST["submissionId"], $currentPersonId, $_POST["reviewReason"]);
                    $flagCreate->execute();
                    echo("<div class=\"alert alert-success\">Thanks for flagging this submission.</div>");
                    logHistory("flagged", json_encode($_REQUEST), "submission", $_POST["submissionId"]);
                } else {
                    echo("<div class=\"alert alert-warning\">Sorry, it looks like you've already flagged this submission.</div>");
                    logHistory("flagDuplicated", json_encode($_REQUEST), "submission", $_POST["submissionId"]);
                }
            }
        // not a valid request for viewing the form or or submitting it.
        } else {
            ?>
            <div class="alert alert-warning"><h4>Invalid Request</h4>Please go back and try again.</div>
            <?php
            logHistory("invalidFlagAttempt", json_encode($_REQUEST), "submission", $_REQUEST["submissionId"]);
        }
    ?>
</div>

<?php
include '../_footer.php';
?>