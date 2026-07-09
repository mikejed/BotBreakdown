<?php
$authRequired = true;
$pageTitle = "Delete Account";
include './_dbConnection.php';
include './_head.php';


echo ('<div class="container">');

if (isset($_POST["confirmDelete"]) && $_POST["confirmDelete"] == "true" && $currentPersonId > 0) {
    requireCsrf();
    $deleteScouter = $db->prepare("DELETE FROM `scouter` WHERE `id` = ?");
    $deleteScouter->bind_param("i", $currentPersonId);
    $deleteScouter->execute();

    $currentPersonId = '';
    ?>

    <div class="alert alert-success">Account deleted.</div>
    <a href="/" class="btn btn-primary">Return home</a>

<?php
} else {
?>

    <h1>Delete Account</h1>
    <hr>
    <p>This will immediately and permanently delete your account and if you want to log in you'll have to register again and start over without any data.</p>
    <p><span class="label label-danger">Are you sure this is what you want to do?</span></p>
    <form method="post">
        <input type="hidden" name="csrf" value="<?php echo $csrfToken; ?>">
        <input type="checkbox" name="confirmDelete" value="true" id="confirmDelete" required>
        <label for="confirmDelete">Yes, I want to delete my account</label>
        <br>
        <input type="submit" value="Delete my account" class="btn btn-danger">
    </form>

<?php
}
echo('</div>');


include './_footer.php';
?>