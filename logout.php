<?php
session_start();      // Start the session to access session variables
session_unset();      // Unset all session variables (e.g., user_id, user_name, user_type)
session_destroy();    // Destroy the session (deletes the session file on the server)
header("Location: index.php"); // Redirect the user to the login page (index.php)
exit;                 // Stop script execution immediately after redirection
?>