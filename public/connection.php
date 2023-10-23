<?php
$servername = "65.109.34.91";
$username = "emailpanther_db";
$password = "G^ApgFMubxM}mW@";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully";
?>