<?php
$bbUserAgent = 'BotBreakdown-1.0';
// Credentials live one level above the webroot so the webserver can never
// serve them as plain text. The legacy in-webroot location is only used as a
// fallback until the server's copy has been moved (see _connectionStrings.sample.php).
if (file_exists(__DIR__ . '/../_connectionStrings.php')) {
    include __DIR__ . '/../_connectionStrings.php';
} else {
    include __DIR__ . '/_connectionStrings.cfg';
}

// Create connection
$db = mysqli_connect($servername, $username, $password, $dbname);
// Check connection
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Escape a value for safe output inside HTML text or attributes. Use this on
// anything that originated from a request or from scouter-entered data.
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Create a function I can use on any page, to consistently log history.
function logHistory($_operation, $_data, $_recordType=NULL, $_recordId=NULL) {
    global $db, $currentPersonId, $currentSessionId;
    $logHistory = $db->prepare("INSERT INTO history(`sessionId`,`scouterId`,`operation`,`data`,`recordType`,`recordId`) VALUES ('$currentSessionId', $currentPersonId, ?, ?, ?, ?);");
    $logHistory->bind_param("ssss", $_operation, $_data, $_recordType, $_recordId);
    $logHistory->execute();
}

// Get Logged in Scouter
$currentUserQuery = $db->prepare("SELECT auth.`scouterId`, s.`name`, s.`isAdmin` FROM `scouterAuth` auth INNER JOIN `scouter` s ON s.`id` = auth.`scouterId` WHERE auth.`uuid` IS NOT NULL AND auth.`uuid` = ? AND `expireDateTime` > CURRENT_TIMESTAMP();");
$currentUserQuery->bind_param("s", $_COOKIE["user"]);
$currentUserQuery->execute();
$currentUserResult = $currentUserQuery->get_result();
if ($currentUserResult->num_rows > 0) {
    $currentUserResultData = $currentUserResult->fetch_all(MYSQLI_ASSOC);
    $currentPersonId = $currentUserResultData[0]["scouterId"];
    $isAdmin = $currentUserResultData[0]["isAdmin"];
    $currentPersonName = $currentUserResultData[0]["name"];
} else {
    $currentPersonId = 0;
    $isAdmin = 0;
    $currentPersonName = '';
}

// Get IP address (NOTE: this is not a trusted value; don't use it for anything that isn't sanitized)
$currentUntrustedAddress = isset($_SERVER['HTTP_CLIENT_IP'])
                         ? $_SERVER['HTTP_CLIENT_IP']
                         : (isset($_SERVER['HTTP_X_FORWARDED_FOR'])
                           ? $_SERVER['HTTP_X_FORWARDED_FOR']
                           : $_SERVER['REMOTE_ADDR']);

// ---------------------------------------------------------
// Get current session
// ---------------------------------------------------------
$currentSessionId = isset($_COOKIE["sessionId"]) ? $_COOKIE["sessionId"] : '';

// Check whether a session exists with the designated sessionId and IP Address
$sessionQuery = $db->prepare("SELECT `sessionId` FROM `session` WHERE `sessionId` IS NOT NULL AND `sessionId` <> '' AND `sessionId` = ? AND `ipAddress` = ?");
$sessionQuery->bind_param("ss", $currentSessionId, $currentUntrustedAddress);
$sessionQuery->execute();
$sessionResult = $sessionQuery->get_result();
$sessionResultData = $sessionResult->fetch_all(MYSQLI_ASSOC);

// If a match isn't found, don't use the (blank or non-blank) session; ignore what was provided and create a NEW session.
if ($sessionResult->num_rows == 0) {
    // make the new Id (cryptographically random; uniqid() is a guessable timestamp)
    $currentSessionId = bin2hex(random_bytes(16));

    // set a session-only cookie
    setcookie("sessionId", $currentSessionId, 0, '/', 'botbreakdown.com');

    // and write the session to the database.
    $insertSession = $db->prepare("INSERT INTO `session` (`sessionId`,`ipAddress`,`userAgent`) VALUES ('$currentSessionId', ?, ?)");
    $insertSession->bind_param("ss", $currentUntrustedAddress, $_SERVER['HTTP_USER_AGENT']);
    $insertSession->execute();
}

// Now we know that $currentSessionId exists and is valid. It's ok to use that on the rest of our pages.


// ---------------------------------------------------------
// CSRF protection (double-submit cookie)
// ---------------------------------------------------------
if (!isset($_COOKIE["csrfToken"]) || strlen($_COOKIE["csrfToken"]) < 32) {
    $_COOKIE["csrfToken"] = bin2hex(random_bytes(16)); // also set in $_COOKIE so forms rendered by this same request can embed it
    setcookie("csrfToken", $_COOKIE["csrfToken"], 0, '/', '', true, true);
}
$csrfToken = $_COOKIE["csrfToken"];

// Call this at the top of any request that changes data. The posted token must
// match the cookie, which a cross-site attacker can neither read nor set.
function requireCsrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST'
        || !isset($_POST['csrf'])
        || !hash_equals($_COOKIE['csrfToken'] ?? '', $_POST['csrf'])) {
        http_response_code(403);
        die('Invalid or missing security token. Please go back, reload the page, and try again.');
    }
}


// ---------------------------------------------------------
// Get all of the app settings from the database.
// ---------------------------------------------------------
$sql = "SELECT
            (SELECT `value` FROM `setting` WHERE `key` = 'currentSeason') AS 'currentSeason'
            , (SELECT `value` FROM `setting` WHERE `key` = 'flagLimit') AS 'flagLimit'
            , (SELECT `value` FROM `setting` WHERE `key` = 'flagThreshold') AS 'flagThreshold'
            , (SELECT `value` FROM `setting` WHERE `key` = 'blueAllianceApiKey') AS 'blueAllianceApiKey'
            , (SELECT `value` FROM `setting` WHERE `key` = 'sendGridApiKey') AS 'sendGridApiKey'
            , (SELECT `value` FROM `setting` WHERE `key` = 'brevoApiKey') AS 'brevoApiKey'
            , (SELECT `value` FROM `setting` WHERE `key` = 'firstApiAuthToken') AS 'firstApiAuthToken'
        ;";
$result = $db->query($sql);
$settings = $result->fetch_assoc();

// Current year
$currentSeason = $settings["currentSeason"];

// Flagging settings
$flagLimit = $settings["flagLimit"];
$flagThreshold = $settings["flagThreshold"];

// --------------------------------------------------------------------------------
// TBA Settings
$blueAllianceApiKey = $settings["blueAllianceApiKey"];

// FIRST settings
$firstApiAuthToken = $settings["firstApiAuthToken"];

// set up FIRST CURL parameters - we will use these for multiple requests across the site.
$firstHeaders = array(
    "Authorization: Basic $firstApiAuthToken"
);
$firstCurlOpt = array(
    CURLOPT_RETURNTRANSFER => true,         // return web page
    CURLOPT_HEADER         => false,        // don't return headers
    CURLOPT_FOLLOWLOCATION => true,         // follow redirects
    CURLOPT_USERAGENT      => $bbUserAgent,
    CURLOPT_AUTOREFERER    => true,         // set referer on redirect
    CURLOPT_CONNECTTIMEOUT => 120,          // timeout on connect
    CURLOPT_TIMEOUT        => 120,          // timeout on response
    CURLOPT_MAXREDIRS      => 10,           // stop after 10 redirects
    CURLOPT_HTTPHEADER     => $firstHeaders,
    CURLOPT_VERBOSE        => 1              
);

// set up TBA CURL parameters - we may use these again in the future.
$tbaHeaders = array(
    "X-TBA-Auth-Key: $blueAllianceApiKey"
);
$tbaCurlOpt = array(
    CURLOPT_RETURNTRANSFER => true,         // return web page
    CURLOPT_HEADER         => false,        // don't return headers
    CURLOPT_FOLLOWLOCATION => true,         // follow redirects
    CURLOPT_USERAGENT      => $bbUserAgent,
    CURLOPT_AUTOREFERER    => true,         // set referer on redirect
    CURLOPT_CONNECTTIMEOUT => 120,          // timeout on connect
    CURLOPT_TIMEOUT        => 120,          // timeout on response
    CURLOPT_MAXREDIRS      => 10,           // stop after 10 redirects
    CURLOPT_HTTPHEADER     => $tbaHeaders,
    CURLOPT_VERBOSE        => 1              
);

/*
// set up SendGrid parameters too.
$sendGridApiKey = $settings["sendGridApiKey"];
$sgHeaders = array(
    "Authorization: Bearer $sendGridApiKey"
    ,"Content-Type: application/json"
);
$sgCurlOpt = array(
    CURLOPT_CUSTOMREQUEST  => "POST",
    CURLOPT_RETURNTRANSFER => true,         // return web page
    CURLOPT_HEADER         => false,        // don't return headers
    CURLOPT_FOLLOWLOCATION => true,         // follow redirects
    CURLOPT_USERAGENT      => $bbUserAgent,
    CURLOPT_AUTOREFERER    => true,         // set referer on redirect
    CURLOPT_CONNECTTIMEOUT => 120,          // timeout on connect
    CURLOPT_TIMEOUT        => 120,          // timeout on response
    CURLOPT_MAXREDIRS      => 10,           // stop after 10 redirects
    CURLOPT_HTTPHEADER     => $sgHeaders,
    CURLOPT_VERBOSE        => 1
);
*/

// replace SendGrid with Brevo
$brevoApiKey = $settings["brevoApiKey"];
$brevoHeaders = array(
    "api-key: $brevoApiKey"
    ,"Content-Type: application/json"
);
$brevoCurlOpt = array(
    CURLOPT_CUSTOMREQUEST  => "POST",
    CURLOPT_RETURNTRANSFER => true,         // return web page
    CURLOPT_HEADER         => false,        // don't return headers
    CURLOPT_FOLLOWLOCATION => true,         // follow redirects
    CURLOPT_USERAGENT      => $bbUserAgent,
    CURLOPT_AUTOREFERER    => true,         // set referer on redirect
    CURLOPT_CONNECTTIMEOUT => 120,          // timeout on connect
    CURLOPT_TIMEOUT        => 120,          // timeout on response
    CURLOPT_MAXREDIRS      => 10,           // stop after 10 redirects
    CURLOPT_HTTPHEADER     => $brevoHeaders,
    CURLOPT_VERBOSE        => 1
);

?>