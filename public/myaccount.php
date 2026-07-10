<?php
$authRequired = true;
$pageTitle = "Account Settings";
include './_dbConnection.php';

if (isset($_POST["newEmail"]) && strlen($_POST["newEmail"]) > 4 ) {
    // insert record into addressChange with scouterId, newEmail, oldAddressCode, newAddressCode, expireDateTime
    $addressCleanup = $db->prepare("DELETE FROM `addressChange` WHERE `scouterId` = $currentPersonId");
    $addressCleanup->execute();

    $code1 = bin2hex(random_bytes(32));
    $code2 = bin2hex(random_bytes(32));
    $addressUpdate = $db->prepare("INSERT INTO `addressChange` (`scouterId`, `newEmail`, `oldAddressCode`, `newAddressCode`, `expireDateTime`) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))");
    $addressUpdate->bind_param("isss", $currentPersonId, $_POST["newEmail"], $code1, $code2);
    $addressUpdate->execute();

    $findScouterRecord = $db->prepare("SELECT `id`,`name`,`email` FROM `scouter` WHERE `id` = ? ORDER BY `id` ASC");
    $findScouterRecord->bind_param("i", $currentPersonId);
    $findScouterRecord->execute();
    $foundScouterResult = $findScouterRecord->get_result();
    $foundScouterData = $foundScouterResult->fetch_all(MYSQLI_ASSOC);

    // send email to current address
    $data = '{
        "sender":{
            "email":"account@botbreakdown.com",
            "name":"BotBreakdown Auth"
        },
        "to":[
            {
                "email":"' . $foundScouterData[0]["email"] . '"
            }
        ],
        "templateId":3,
        "params":{
            "token": "' . $code1 . '"
        }
    }';

    $emailRequest = curl_init("https://api.brevo.com/v3/smtp/email");

    curl_setopt_array($emailRequest, $brevoCurlOpt);
    curl_setopt($emailRequest, CURLOPT_POSTFIELDS, $data);

    $response = curl_exec($emailRequest);

    curl_close($emailRequest);

    // send email to new address
    $data = '{
        "sender":{
            "email":"account@botbreakdown.com",
            "name":"BotBreakdown Auth"
        },
        "to":[
            {
                "email":"' . $_POST["newEmail"] . '"
            }
        ],
        "templateId":3,
        "params":{
            "token": "' . $code1 . '"
        }
    }';

    $emailRequest = curl_init("https://api.brevo.com/v3/smtp/email");

    curl_setopt_array($emailRequest, $brevoCurlOpt);
    curl_setopt($emailRequest, CURLOPT_POSTFIELDS, $data);

    $response = curl_exec($emailRequest);

    curl_close($emailRequest);

    //TODO: add a check in either a cron job or in login.php or _dbConnection.php to make changes to `scouter` if new address is approved and expireDateTime is past.

}

if (isset($_POST["displayName"]) && isset($_POST["name"])) {
    $scouterUpdate = $db->prepare("UPDATE `scouter` SET `name` = ?, `displayName` = ? WHERE `id` = ?");
    $scouterUpdate->bind_param("ssi", $_POST["name"], $_POST["displayName"], $currentPersonId);
    $scouterUpdate->execute();
    header("Location: /myaccount.php?updated=true");
}

include './_head.php';

// get current scouter info
$scouterInfo = $db->prepare("SELECT `name`, `displayName`, `email` FROM `scouter` WHERE `id` = ?");
$scouterInfo->bind_param("i", $currentPersonId);
$scouterInfo->execute();
$scouterInfoResult = $scouterInfo->get_result();
$scouterInfoResultData = $scouterInfoResult->fetch_all(MYSQLI_ASSOC);

// TODO: sql to get submissions

?>
<div class="container">
    <form action="/myaccount.php" method="post">
        <h1>Account Settings</h1>
        <hr>
        
        <?php
        if (isset($_GET["updated"]) && $_GET["updated"] == "true") {
            echo('<div class="alert alert-success">Information updated.</div>');
        }

        $pendingAddressChange = $db->prepare("SELECT count(1) AS `count` FROM `addressChange` WHERE `scouterId` = ?");
        $pendingAddressChange->bind_param("i", $currentPersonId);
        $pendingAddressChange->execute();
        $pendingAddressChangeResult = $pendingAddressChange->get_result();
        $pendingAddressChangeResultData = $pendingAddressChangeResult->fetch_all(MYSQLI_ASSOC);
        ?>

        <h4>Make changes</h4>
        <div class="row">
            <div class="col-md">
                <label class="form-label" for="displayName">Display Name</label>
                <input class="form-control" name="displayName" id="displayName" value="<?php echo $scouterInfoResultData[0]["displayName"]; ?>">
                
                <label class="form-label" for="name">Name</label>
                <input class="form-control" name="name" id="name" value="<?php echo $scouterInfoResultData[0]["name"]; ?>">
            </div>
            <div class="col-md">
                <label class="form-label">Current email address</label>
                <p class="form-control text-body-tertiary mb-0"><?php echo $scouterInfoResultData[0]["email"]; ?></p>

                <label class="form-label" for="newEmail" disabled>New email address</label>
                <input type="email" class="form-control" name="newEmail" id="newEmail" placeholder="(leave blank for no change)">
                <?php if ($pendingAddressChangeResultData[0]["count"] > 0) { echo('<div class="alert alert-warning mt-4">Your address change request is pending. Please check your email at both your old and new address.</div>');} ?>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col">
                <input type="submit" />
            </div>
        </div>
    </form>

<!--
    <div class="card mt-5">
        <div class="card-header">
            <h4 class="card-title">Your Submissions</h4>
        </div>
        <div class="card-body">
            <ul>
                <li>...</li>
                <li>...</li>
            </ul>
        </div>
    </div>
-->
</div>

<hr class="mt-5">
<div class="container mb-5">
    <div class="accordion" id="formAccordion">
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#advancedOptions" aria-expanded="false" aria-controls="advancedOptions">
                    Danger Zone
                </button>
            </h2>
            <div id="advancedOptions" class="accordion-collapse collapse" data-bs-parent="#formAccordion">
                <div class="accordion-body">
                    <div><strong>Delete my account</strong></div>
                    <div class="alert alert-warning"><strong>WARNING!</strong> If you delete your account you will have to register again if you want to log in, and you won't have any data to start.</div>
                    <div class="text-end">
                        <a class="btn btn-danger" title="Delete my account" href="deleteAccount.php">Delete my account</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<?php include './_footer.php'; ?>