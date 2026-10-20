<?php
// logout.php (Berada di root folder evoting)

session_start();
session_unset();
session_destroy();

$redirect = "index.php";
if (isset($_GET['voted'])) {
    $redirect .= "?voted=1";
}

header("Location: " . $redirect);
exit;
?>
