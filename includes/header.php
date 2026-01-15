<?php
require_once __DIR__ . '/../includes/log_helper.php';
require_once __DIR__ . '/../includes/auth.php';

use function Auth\getActorContext;
use function LogHelper\logUnified;

// ✅ Only log session start once
if (empty($_SESSION['session_logged'])) {
  $_SESSION['session_logged'] = true;

  $actor = getActorContext();
  logUnified(
    'SESSION_START',
    $actor['id'] ?? 0,
    'session',
    ['entry' => basename($_SERVER['SCRIPT_FILENAME'])],
    'info',
    'system',
    true
  );
}

?>