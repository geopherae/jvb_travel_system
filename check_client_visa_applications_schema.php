<?php
require_once 'actions/db.php';
$r = $conn->query('SHOW CREATE TABLE client_visa_applications');
if ($r) {
  $row = $r->fetch_assoc();
  echo $row['Create Table'];
} else {
  echo $conn->error;
}
$conn->close();
