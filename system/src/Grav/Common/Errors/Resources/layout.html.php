<?php
/**
 * Layout template file for Whoops's pretty error output.
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Whoops there was an error!</title>
    <style><?php echo $stylesheet ?></style>
</head>
<body>
    <div class="container">
        <div class="details">
            <header>
                Server Error
            </header>



            <p>Sorry, something went terribly wrong!</p>

            <h3><?php echo $code ?> - <?php echo $message ?></h3>

            <h5>For further details please review your <code>logs/</code> folder, or enable displaying of errors in your system configuration.</h5>
        </div>
    </div>
</body>
</html>
