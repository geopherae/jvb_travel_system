<?php
$isAdmin  = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$isClient = isset($_SESSION['client_id']) && !empty($_SESSION['client_id']);

$profileName  = 'Guest';
$profilePhoto = '../images/default_client_profile.png';

if ($isAdmin) {
  $admin = $_SESSION['admin'] ?? [];
  $photo = $admin['admin_photo'] ?? '';
  $photoPath = __DIR__ . '/../uploads/admin_photo/' . basename($photo);

  if ($photo && file_exists($photoPath)) {
    $profilePhoto = '../uploads/admin_photo/' . basename($photo);
  }

  $profileName = $admin['first_name'] ?? 'Admin';
}

elseif ($isClient) {
  $clientSession = $_SESSION['client'] ?? [];
  $photo = $clientSession['client_profile_photo'] ?? '';
  $photoPath = __DIR__ . '/../uploads/client_profiles/' . basename($photo);

  if ($photo && file_exists($photoPath)) {
    $profilePhoto = '../uploads/client_profiles/' . basename($photo);
  }

  $profileName = $clientSession['full_name'] ?? 'Client';
}
?>