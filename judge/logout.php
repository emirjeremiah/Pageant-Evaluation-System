<?php
session_start();

// Unset all of the session variables.
session_unset();

// Finally, destroy the session.
session_destroy();

// Redirect to the home page after logout
header("Location: ../index.php");
exit();