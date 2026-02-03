<?php
require_once 'actions/db.php';
$errors = [];

if (!$conn->query("ALTER TABLE `client_visa_applications` ADD PRIMARY KEY (`id`)") ) {
  if (strpos($conn->error, 'Duplicate') === false && strpos($conn->error, 'PRIMARY') === false) {
    $errors[] = $conn->error;
  }
}

if (!$conn->query("ALTER TABLE `client_visa_applications` MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT")) {
  $errors[] = $conn->error;
}

if (empty($errors)) {
  echo "OK";
} else {
  echo implode("\n", $errors);
}
$conn->close();
