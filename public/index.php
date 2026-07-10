<?php
$pageTitle = "Home";
include './_dbConnection.php';
include './_head.php';
?>
    <style>
        img#sample1 {
            background-image: url('/assets/images/viewdata-bg-light.png');
            background-size: cover;
        }
        [data-bs-theme=dark] img#sample1 {
            background-image: url('/assets/images/viewdata-bg-dark.png');
        }
        img#sample2 {
            background-image: url('/assets/images/autoinput-bg-light.png');
            background-size: cover;
        }
        [data-bs-theme=dark] img#sample2 {
            background-image: url('/assets/images/autoinput-bg-dark.png');
        }
        img#sample3 {
            background-image: url('/assets/images/auto-bg-light.png');
            background-size: cover;
        }
        [data-bs-theme=dark] img#sample3 {
            background-image: url('/assets/images/auto-bg-dark.png');
        }
    </style>
    
    <div class="container">
        <div class="row my-5">
            <div class="col-sm-9">
                <div class="alert alert-info">
                    <h1>Welcome to BotBreakdown!</h1>
                    <p>Collaborative offline FRC scouting, no app required!</p>
                </div>
                <p>Brought to you by 2486 Team CocoNuts, and inspired by Team 8-Bit's Arizona Scouting Alliance.</p>
            </div>
            <div class="col-sm-3">
                <img src="/assets/images/BotBreakdown-Icon.svg" class="img-fluid" alt="I'm Bit-C: the mascot of BotBreakdown.com!" title="I'm Bit-C: the mascot of BotBreakdown.com!" />
            </div>
        </div>
        <div class="row my-5">
            <div class="col-sm-7 d-flex flex-column justify-content-center order-sm-2 checkerboard-text">
                <h3>Unifying scouters across teams anywhere</h3>
                <p>By contributing data about a robot's match, you get access to the event dashboard containing all of the data submitted for the entire event.</p>
            </div>
            <div class="col-sm-5 d-flex flex-column justify-content-center order-sm-1">
                <img src="/assets/images/left-frame.png" id="sample1" class="img-fluid" alt="Sample data from an event's dashboard on botbreakdown.com">
            </div>
        </div>
        <div class="row my-5">
            <div class="col-sm-7 d-flex flex-column justify-content-center checkerboard-text">
                <h3>Easy data entry</h3>
                <p>No wondering what icons mean- data entry on botbreakdown.com is clear and easy to use</p>
                <p>And for several seasons now we've had a visual way of capturing paths during the autonomous period, with the ability to overlay all of a team's autonomous paths across matches and scouters in a single view.</p>
            </div>
            <div class="col-sm-5 d-flex flex-column justify-content-center">
                <img src="/assets/images/right-frame.png" id="sample2" class="img-fluid" alt="Autonomous data entry tab">
            </div>
        </div>
        <div class="row my-5">
            <div class="col-sm-7 d-flex flex-column justify-content-center order-sm-2 checkerboard-text">
                <h3>Easy Signup</h3>
                <p>The only information we need from you is an email address (so nobody else can submit as you). No team accounts, invitations, or even passwords required.</p>
            </div>
            <div class="col-sm-5 d-flex flex-column justify-content-center order-sm-1">
                <img src="/assets/images/left-frame.png" id="sample3" class="img-fluid" alt="Autonomous data entry tab">
            </div>
        </div>

        <div class="alert alert-info mt-4">
            <h4>Latest Update</h4>
            <p>Wow- what a season this year! Don't forget that all of the off-season events are available for scouting here as well, as long as they are listed as events on the <i>First</i> site.</p>
            <p>In July we went through a Language Model code review and issued some bugfixes, and everything should be even better than ever. We'll be publishing a new blog post soon also, showing some patterns for analyzing the new "timing" features we introduced this season - stay tuned!</p>
        </div>
        
    </div>

<?php include './_footer.php'; ?>