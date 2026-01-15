<?php
require_once __DIR__ . '/../actions/db.php';

function getClients($conn) {
  $clients = [];
  $result = $conn->query("SELECT id, full_name FROM clients ORDER BY full_name ASC");
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $clients[] = [
        'id' => $row['id'],
        'full_name' => $row['full_name']
      ];
    }
    $result->free();
  }
  return $clients;
}
?>