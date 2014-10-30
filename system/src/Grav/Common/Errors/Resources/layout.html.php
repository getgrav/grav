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
            <p>We're sorry! The server has encountered an internal error and was unable to complete your request.
                Please contact the system administrator for more information.</p>
            <h6>For further details please review your <code>logs/</code> folder, or enable displaying of errors in your system configuration.</h6>
            <h6>Error Code: <b><?php echo $code ?></b></h6>
        </div>
    </div>
</body>
</html>
