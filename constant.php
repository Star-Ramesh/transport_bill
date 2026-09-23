<?php

$conn = mysqli_connect("localhost", "root", "", "transport_bill");

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}


// GSTINAPI key - keep this on the server and do not expose it in JavaScript.
define('GSTIN_API_KEY', 'gak_036d2a5574d44923b074433be4a45ac1');
