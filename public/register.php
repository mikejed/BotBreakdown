<?php

include './_dbConnection.php';

$loginState='notLoggedIn'; // we need to check if codes are being verified by this request, here, before the body of the document starts (so we can set a cookie). If we either verify the codes and log the in, or run into an error, we'll update this.

// Do some cleanup: delete all pendingScouter records that have expired
$sessions = $db->query("DELETE FROM `pendingScouter` WHERE `expireDateTime` < NOW()");

if (isset($_POST["totp"])) {
    // totp was provided; attempt to authenticate

    $findPendingRecord = $db->prepare("SELECT `email`,`name`,`displayName` FROM `pendingScouter` WHERE `totp` = ? AND `sessionId` = '" . $currentSessionId . "' AND `expireDateTime` > NOW() ORDER BY `expireDateTime` DESC");
    $findPendingRecord->bind_param("s", $_POST["totp"]);
    $findPendingRecord->execute();
    $pendingRecordResult = $findPendingRecord->get_result();
    $pendingRecordData = $pendingRecordResult->fetch_all(MYSQLI_ASSOC);

    if ($pendingRecordResult->num_rows > 0) {
        // First, make sure the account doesn't already exist.
        $findDuplicateRecord = $db->prepare("SELECT `id` FROM `scouter` WHERE `email` = ?");
        $findDuplicateRecord->bind_param("s", $pendingRecordData[0]["email"]);
        $findDuplicateRecord->execute();
        $duplicateRecordResult = $findDuplicateRecord->get_result();
        $duplicateRecordData = $duplicateRecordResult->fetch_all(MYSQLI_ASSOC);

        if ($duplicateRecordResult->num_rows > 0) {
            $loginState='duplicateAccount';
            // echo('<div class="alert alert-warning"><h3>Oops</h3>This account has already been created. Please <a href="/login.php">log in</a> instead.</div>');
        } else {
            $createAccount = $db->prepare("INSERT INTO `scouter` (`name`,`displayName`,`email`) VALUES (?,?,?)");
            $createAccount->bind_param("sss", $pendingRecordData[0]["name"], $pendingRecordData[0]["displayName"], $pendingRecordData[0]["email"]);
            $createAccount->execute();

            $newScouter = $db->prepare("SELECT `id` FROM `scouter` WHERE `email` = ?");
            $newScouter->bind_param("s", $pendingRecordData[0]["email"]);
            $newScouter->execute();
            $newScouterResult = $newScouter->get_result();
            $newScouterData = $newScouterResult->fetch_all(MYSQLI_ASSOC);

            // Now delete all pending records for this email address.
            $sessions = $db->query("DELETE FROM `pendingScouter` WHERE `email` = '" . $pendingRecordData[0]["email"] . "'");

            $authIdentifier = uniqid("bba");
            $logIn = $db->prepare("INSERT INTO `scouterAuth` (`uuid`,`scouterId`,`expireDateTime`) VALUES ('" . $authIdentifier . "', ?, DATE_ADD(NOW(), INTERVAL 30 DAY))");
            $logIn->bind_param("i",$newScouterData[0]["id"]);
            $logIn->execute();
            
            setcookie("user",$authIdentifier,time()+60*60*24*30,"/","botbreakdown.com",true,true);
            $currentPersonId = $newScouterData[0]["id"];
            logHistory("register", $pendingRecordData[0]["name"]);

            $loginState='loginComplete';
            // echo('<div class="alert alert-success">Your account has been created</div>');

        }
    } else {
        $loginState='codeExpired';
        // echo('<div class="alert alert-danger">Your confirmation code may have expired. Please double-check your code or try registering again.</div>');
    }

} elseif (isset($_GET["token"])) {
    // button was clicked in email; attempt to authenticate

    $findPendingRecord = $db->prepare("SELECT `email`,`name`,`displayName` FROM `pendingScouter` WHERE `token` = ? AND `expireDateTime` > NOW() ORDER BY `expireDateTime` DESC");
    $findPendingRecord->bind_param("s", $_GET["token"]);
    $findPendingRecord->execute();
    $pendingRecordResult = $findPendingRecord->get_result();
    $pendingRecordData = $pendingRecordResult->fetch_all(MYSQLI_ASSOC);

    if ($pendingRecordResult->num_rows > 0) {
        // First, make sure the account doesn't already exist.
        $findDuplicateRecord = $db->prepare("SELECT `email` FROM `scouter` WHERE `email` = ?");
        $findDuplicateRecord->bind_param("s", $pendingRecordData[0]["email"]);
        $findDuplicateRecord->execute();
        $duplicateRecordResult = $findDuplicateRecord->get_result();
        $duplicateRecordData = $duplicateRecordResult->fetch_all(MYSQLI_ASSOC);

        if ($duplicateRecordResult->num_rows > 0) {
            $loginState='duplicateAccount';
            // echo('<div class="alert alert-warning"><h3>Oops</h3>This account has already been created. Please <a href="/login.php">log in</a> instead.</div>');
        } else {
            $createAccount = $db ->prepare("INSERT INTO `scouter` (`name`,`displayName`,`email`) VALUES (?,?,?)");
            $createAccount->bind_param("sss", $pendingRecordData[0]["name"], $pendingRecordData[0]["displayName"], $pendingRecordData[0]["email"]);
            $createAccount->execute();

            $newScouter = $db->prepare("SELECT `id` FROM `scouter` WHERE `email` = ?");
            $newScouter->bind_param("s", $pendingRecordData[0]["email"]);
            $newScouter->execute();
            $newScouterResult = $newScouter->get_result();
            $newScouterData = $newScouterResult->fetch_all(MYSQLI_ASSOC);

            // Now delete all pending records for this email address.
            $sessions = $db->query("DELETE FROM `pendingScouter` WHERE `email` = '" . $pendingRecordData[0]["email"] . "'");

            $authIdentifier = uniqid("bba");
            $logIn = $db->prepare("INSERT INTO `scouterAuth` (`uuid`,`scouterId`,`expireDateTime`) VALUES ('" . $authIdentifier . "', ?, DATE_ADD(NOW(), INTERVAL 30 DAY))");
            $logIn->bind_param("i",$newScouterData[0]["id"]);
            $logIn->execute();
            
            setcookie("user",$authIdentifier,time()+60*60*24*30,"/","botbreakdown.com",true,true);
            $currentPersonId = $newScouterData[0]["id"];
            logHistory("register", $pendingRecordData[0]["name"]);

            $loginState='loginComplete';
            // echo('<div class="alert alert-success">Your account has been created</div>');
        }
    } else {
        $loginState='codeExpired';
        // echo('<div class="alert alert-danger">Your confirmation code may have expired. Please double-check your code or try registering again.</div>');
    }
} 

include './_head.php';

// ---------------------------------- Begin body of page ---------------------------------------
echo ('<div class="container">');

    if ($loginState == 'loginComplete') {
        echo('<div class="alert alert-success"><p>Your account has been created.</p><ul><li><a href="/">Return Home</a></li><li><a href="/myevents">Go to My Events page to start entering data</a></li></div>');

    } elseif ($loginState == 'duplicateAccount') {
        echo('<div class="alert alert-warning"><h3>Oops</h3>This account has already been created. Please <a href="/login.php">log in</a> instead.</div>');

    } elseif ($loginState == 'codeExpired') {
        echo('<div class="alert alert-danger">Your confirmation code may have expired. Please double-check your code or try registering again.</div>');

    } else {
        // we still need info- figure out which form to show.

        if (isset($_POST["email"])) {
            // register form was submitted

            // First, make sure the account doesn't already exist.
            $findDuplicateRecord = $db->prepare("SELECT `id` FROM `scouter` WHERE `email` = ? ORDER BY `id` DESC");
            $findDuplicateRecord->bind_param("s", $_POST["email"]);
            $findDuplicateRecord->execute();
            $duplicateRecordResult = $findDuplicateRecord->get_result();
            $duplicateRecordData = $duplicateRecordResult->fetch_all(MYSQLI_ASSOC);

            if ($findDuplicateRecord->num_rows > 0) {
                echo('<div class="alert alert-info"><h3>Oops</h3>This account already exists. Please <a href="/login.php">log in</a> instead.</div>');
            } else {

                if ($_POST["email"] == $_POST["emailConfirm"] && strlen($_POST["email"] > 6)) {
                    // email addresses match. Create auth codes, send email, and prompt for TOTP

                    // create token and TOTP
                    $token = password_hash(uniqid(rand() . "bba"), PASSWORD_DEFAULT);
                    $totp = random_int(120000,989999);
                    
                    // Store token, TOTP, and sessionId in pendingScouter table.
                    $name = !empty($_POST["name"]) ? $_POST["name"] : "Anonymous Scouter";
                    $displayName = !empty($_POST["displayName"]) ? $_POST["displayName"] : "Scouter";
                    $storePending = $db->prepare("INSERT INTO pendingScouter(`sessionid`,`name`,`displayName`,`email`,`token`,`totp`,`expireDateTime`) VALUES ('" . $currentSessionId . "',?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR));");
                    $storePending->bind_param("sssss", $name, $displayName, $_POST["email"], $token, $totp);
                    $storePending->execute();

                    // Send Email
                    $data = '{
                        "sender":{
                            "email":"account@botbreakdown.com",
                            "name":"BotBreakdown Auth"
                        },
                        "to":[
                            {
                                "email":"' . $_POST["email"] . '"
                            }
                        ],
                        "templateId":2,
                        "params":{
                            "name": "' . $name . '",
                            "totp": "' . $totp . '",
                            "token": "' . $token . '"
                        }
                    }';

                    $emailRequest = curl_init("https://api.brevo.com/v3/smtp/email");

                    curl_setopt_array($emailRequest, $brevoCurlOpt);
                    curl_setopt($emailRequest, CURLOPT_POSTFIELDS, $data);

                    $response = curl_exec($emailRequest);

                    curl_close($emailRequest);

                    // Now show the form where they can enter the TOTP
                    ?>

                    <div class="row justify-content-center">
                        <div class="col-md-6 col-lg-4">
                            <div class="alert alert-info mb-4">
                                <p>We've sent an email to the address you provided. Get that email and you can either close this tab and use the link in the email to confirm your account, or you can enter the code from the email here instead.</p>
                            </div>
                            <div class="card">
                                <div class="card-header text-center">
                                    Confirmation Code
                                </div>
                                <div class="card-body">
                                    <form id="confirmationForm" method="post" action="/register.php">
                                        <input type="hidden" id="totp" name="totp" />
                                        <div class="form-group d-flex justify-content-center mb-4">
                                            <input type="text" class="form-control totp-digit" maxlength="1" pattern="\d" required>
                                            <input type="text" class="form-control totp-digit" maxlength="1" pattern="\d" required>
                                            <input type="text" class="form-control totp-digit" maxlength="1" pattern="\d" required>
                                            <input type="text" class="form-control totp-digit" maxlength="1" pattern="\d" required>
                                            <input type="text" class="form-control totp-digit" maxlength="1" pattern="\d" required>
                                            <input type="text" class="form-control totp-digit" maxlength="1" pattern="\d" required>
                                        </div>
                                        <a href="javascript:doSubmit();" class="btn btn-primary">Submit</a>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', (event) => {
                            const inputs = document.querySelectorAll('.totp-digit');
                            inputs.forEach((input, index) => {
                                input.addEventListener('input', () => {
                                    if (input.value.length === 1 && index < inputs.length - 1) {
                                        inputs[index + 1].focus();
                                    }
                                });

                                input.addEventListener('paste', (event) => {
                                    const paste = event.clipboardData.getData('text').trim().substring(0, 6);
                                    if (/^\d{6}$/.test(paste)) {
                                        event.preventDefault();
                                        inputs.forEach((input, i) => {
                                            input.value = paste[i];
                                        });
                                    }
                                });
                            });

                            const form = document.getElementById('confirmationForm');
                            form.addEventListener('submit', (event) => {
                                if ([...inputs].some(input => input.value.length !== 1)) {
                                    event.preventDefault();
                                    alert('Please enter a valid 6-digit code.');
                                }
                            });
                        });
                        function doSubmit() {
                            const inputs = document.querySelectorAll('.totp-digit');
                            fullCode = "";
                            inputs.forEach((input, index) => {
                                fullCode += input.value;
                            });
                            $('input#totp').val(fullCode);
                            $('form#confirmationForm').submit();
                        }
                    </script>
                <?php
                } else {
                    echo('<div class="alert alert-warning">Oops, your emails didn\'t seem to match; please try again</div>');
                }
            }
        } else {
            // display registration form
            ?>
            <div class="alert alert-info">
                <h2>Welcome aboard!</h2>
                <p>We're excited you want to help contribute data for FRC events! We need just a few pieces of information and we can get started.</p>
            </div>
            <form action="/register.php" method="post">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label" for="name">Your Name <sup><i class="fa-solid fa-circle-info help-icon" title="The name you'll see for your account or in login emails we send you. Other scouters won\'t see this.'"></i></sup></label>
                        <input class="form-control" id="name" name="name">
                        <label class="form-label" for="displayName">Display Name <sup><i class="fa-solid fa-circle-info help-icon" title="The name other scouters will see associated with your contributions.'"></i></sup></label>
                        <input class="form-control" id="displayName" name="displayName">
                        <label class="form-label required" for="email">Email Address</label>
                        <input class="form-control" id="email" name="email" required>
                        <label class="form-label required" for="emailConfirm">Confirm Email Address</label>
                        <input class="form-control" id="emailConfirm" name="emailConfirm" required>
                    </div>
                </div>
                <input type="submit" value="Let's get started!" class="btn btn-primary my-4">
            </form>
            <div class="alert alert-secondary">Wondering why we need your email address? Mostly to keep track of which events you have access to. But if you're interested, you can read all the details in our really easy <a href="/privacy.php">Privacy Policy</a></div>
            <?php
        }
    }

echo ('</div>'); // .container

include './_footer.php';
?>