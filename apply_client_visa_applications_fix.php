<?php
require_once 'actions/db.php';
$sql = "ALTER TABLE `client_visa_applications` MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT";
if ($conn->query($sql)) {
  echo "OK";
} else {
  echo $conn->error;
}
$conn->close();
