<?php

include './_dbConnection.php';

$loginState='notLoggedIn'; // we need to check if codes are being verified by this request, here, before the body of the document starts (so we can set a cookie). If we either verify the codes and log the in, or run into an error, we'll update this.

// Do some cleanup: delete all scouterAuth records that have expired
$sessions = $db->query("DELETE FROM `scouterAuth` WHERE `expireDateTime` < NOW()");

// TODO: This isn't actually a TOTP - it's just a confirmation code. This should really be renamed.
if (isset($_POST["totp"])) {
    // totp was provided; attempt to authenticate

    // Rate limit: a 6-digit code is brute-forceable, so cap failed attempts per session.
    $recentFailures = $db->prepare("SELECT count(1) AS `failures` FROM `history` WHERE `sessionId` = ? AND `operation` = 'loginCodeFailed' AND `modifiedDateTime` > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $recentFailures->bind_param("s", $currentSessionId);
    $recentFailures->execute();
    $recentFailuresData = $recentFailures->get_result()->fetch_all(MYSQLI_ASSOC);

    // Scope the code to the email that requested it, so a code only works for its own account.
    $submittedEmail = isset($_POST["email"]) ? $_POST["email"] : '';
    $findPendingRecord = $db->prepare("SELECT auth.`scouterId` FROM `scouterAuth` auth INNER JOIN `scouter` s ON s.`id` = auth.`scouterId` WHERE auth.`totp` = ? AND s.`email` = ? AND auth.`expireDateTime` > NOW() ORDER BY auth.`expireDateTime` DESC");
    $findPendingRecord->bind_param("ss", $_POST["totp"], $submittedEmail);
    $findPendingRecord->execute();
    $pendingRecordResult = $findPendingRecord->get_result();
    $pendingRecordData = $pendingRecordResult->fetch_all(MYSQLI_ASSOC);

    if ($recentFailuresData[0]["failures"] >= 5) {
        $loginState='tooManyAttempts';
    } elseif ($pendingRecordResult->num_rows > 0) {
        
        $authIdentifier = 'bba' . bin2hex(random_bytes(20));
        $createAccount = $db->prepare("UPDATE `scouterAuth` SET `uuid` = '" . $authIdentifier . "', `totp` = null, `token` = null, `expireDateTime` = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE `totp` = ? AND `expireDateTime` > NOW() AND `scouterId` = " . $pendingRecordData[0]["scouterId"]);
        $createAccount->bind_param("s", $_POST["totp"]);
        $createAccount->execute();

        setcookie("user",$authIdentifier,time()+60*60*24*30,"/","botbreakdown.com",true,true);
        logHistory("login", $pendingRecordData[0]["scouterId"]);
        $currentPersonId = $pendingRecordData[0]["scouterId"];

        $loginState='loginComplete';
        // echo('<div class="alert alert-success">You have been logged in</div>');
        
    } else {
        logHistory("loginCodeFailed", $submittedEmail);
        $loginState='codeExpired';
        // echo('<div class="alert alert-danger">Your confirmation code may have expired. Please double-check your code or try registering again.</div>');
    }

} elseif (isset($_GET["token"])) {
    // button was clicked in email; attempt to authenticate

    $findPendingRecord = $db->prepare("SELECT `scouterId` FROM `scouterAuth` WHERE `token` = ? AND `expireDateTime` > NOW() ORDER BY `expireDateTime` DESC");
    $findPendingRecord->bind_param("s", $_GET["token"]);
    $findPendingRecord->execute();
    $pendingRecordResult = $findPendingRecord->get_result();
    $pendingRecordData = $pendingRecordResult->fetch_all(MYSQLI_ASSOC);

    if ($pendingRecordResult->num_rows > 0) {

        $authIdentifier = 'bba' . bin2hex(random_bytes(20));
        $createAccount = $db->prepare("UPDATE `scouterAuth` SET `uuid` = '" . $authIdentifier . "', `totp` = null, `token` = null, `expireDateTime` = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE `token` = ? AND `expireDateTime` > NOW() AND `scouterId` = " . $pendingRecordData[0]["scouterId"]);
        $createAccount->bind_param("s", $_GET["token"]);
        $createAccount->execute();
        
        setcookie("user",$authIdentifier,time()+60*60*24*30,"/","botbreakdown.com",true,true);
        logHistory("login", $pendingRecordData[0]["scouterId"]);
        $currentPersonId = $pendingRecordData[0]["scouterId"];

        $loginState='loginComplete';
        // echo('<div class="alert alert-success">You have been logged in</div>');
    } else {
        $loginState='codeExpired';
        // echo('<div class="alert alert-danger">Your confirmation code may have expired. Please double-check your code or try registering again.</div>');
    }
}

if ($loginState === 'loginComplete') {
    $redirectUrl = '';
    if (isset($_GET['returnurl'])) {
        $redirectUrl = rawurldecode(rawurldecode($_GET['returnurl']));
    }
    if ($redirectUrl == '') {$redirectUrl = '/myevents';}
    header('Location: ' . $redirectUrl, true, 302);
    exit;
}

include './_head.php';


// ---------------------------------- Begin body of page ---------------------------------------
echo ('<div class="container">');

    if ($loginState == 'tooManyAttempts') {
        echo('<div class="alert alert-danger">Too many incorrect codes. Please wait 15 minutes and then try logging in again.</div>');

    } elseif ($loginState == 'codeExpired') {
        echo('<div class="alert alert-danger">Your confirmation code may have expired. Please double-check your code or try logging in again.</div>');

    } else {
        // we still need info- figure out which form to show.

        if (isset($_POST["email"])) {
            // login form was submitted

            // create token and TOTP (random_bytes: unguessable and URL-safe, unlike a bcrypt hash of uniqid)
            $token = bin2hex(random_bytes(32));
            $totp = random_int(120000,989999);

            // look up the corresponding scouterId based on the email.
            $findScouterRecord = $db->prepare("SELECT `id`,`name`,`email` FROM `scouter` WHERE `email` = ? ORDER BY `id` ASC");
            $findScouterRecord->bind_param("s", $_POST["email"]);
            $findScouterRecord->execute();
            $foundScouterResult = $findScouterRecord->get_result();
            $foundScouterData = $foundScouterResult->fetch_all(MYSQLI_ASSOC);

            if ($foundScouterResult->num_rows > 0) {
                // Account already exists. Store token, TOTP, and sessionId in scouterAuth table.

                // uuid is filled in on confirmation; insert '' explicitly so the row is
                // valid under a strict sql_mode (the column is NOT NULL with no default).
                $storePending = $db->prepare("INSERT INTO scouterAuth(`uuid`,`scouterId`,`token`,`totp`,`expireDateTime`) VALUES ('', ?, '" . $token . "', '" . $totp . "', DATE_ADD(NOW(), INTERVAL 1 HOUR));");
                $storePending->bind_param("i", $foundScouterData[0]["id"]);
                $storePending->execute();

                // Send Email
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
                    "templateId":1,
                    "params":{
                        "displayName": "' . $foundScouterData[0]["name"] . '",
                        "totp": "' . $totp . '",
                        "token": "' . $token . '"
                    }
                }';

                $emailRequest = curl_init("https://api.brevo.com/v3/smtp/email");

                curl_setopt_array($emailRequest, $brevoCurlOpt);
                curl_setopt($emailRequest, CURLOPT_POSTFIELDS, $data);

                $response = curl_exec($emailRequest);

                curl_close($emailRequest);
            }


            // Now show the form where they can enter the TOTP ... even if we didn't actually send the email.
            ?>

            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-4">
                    <div class="alert alert-info mb-4">
                        <p>If your email matches an existing account, we've sent you an email to confirm your login. You can either close this tab and use the link in the email to finish logging in, or you can enter the code from the email here instead.</p>
                    </div>
                    <div class="card">
                        <div class="card-header text-center">
                            Confirmation Code
                        </div>
                        <div class="card-body">
                            <form id="confirmationForm" method="post" action="/login.php<?php if ($_SERVER['QUERY_STRING'] != '') { echo('?' . e($_SERVER['QUERY_STRING'])); } ?>">
                                <input type="hidden" id="totp" name="totp" />
                                <input type="hidden" name="email" value="<?php echo htmlspecialchars($_POST["email"], ENT_QUOTES); ?>" />
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
                            <hr>
                            <form id="resendForm" method="post" action="/login.php">
                                <input type="hidden" id="email" name="email" value="<?php echo e($_POST["email"]); ?>" />
                                Missed the message? You can resend it here:<br>
                                <input type="submit" value="Resend Code" />
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
            // display login form
            ?>


            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Login</h3>
                        </div>
                        <div class="card-body">
                            <form id="loginForm" method="post" action="/login.php<?php if ($_SERVER['QUERY_STRING'] != '') { echo('?' . e($_SERVER['QUERY_STRING'])); } ?>">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" id="email" name="email" class="form-control" placeholder="you@domain.com" required>
                                <button class="btn btn-primary float-end mt-4">Submit</button>
                            </form>
                        </div>
                        <div class="card-footer text-center">
                            <p>Or if you don't have an account yet, you can <a href="/register.php">register</a></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php }
    }
 
echo('</div>');// .container

include './_footer.php';
?>