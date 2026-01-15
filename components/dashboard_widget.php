<?php
date_default_timezone_set('Asia/Manila');

$cacheFile = __DIR__ . '/widget_cache.json';
$cacheTTL = 6 * 60 * 60; // ⏱ Update this value to change cache duration (in seconds)

$useCache = false;
$weatherAvailable = false;

if (file_exists($cacheFile)) {
  $json = json_decode(file_get_contents($cacheFile), true);
  if ($json && (time() - ($json['timestamp'] ?? 0)) < $cacheTTL) {
    extract($json['data']);
    $useCache = true;
  }
}

if (!$useCache) {
  // 1️⃣ Location via IP (fallback city for localhost)
  $ip = $_SERVER['REMOTE_ADDR'];
  $isLocal = in_array($ip, ['127.0.0.1', '::1']);
  $geo = @json_decode(file_get_contents("https://ipinfo.io/" . ($isLocal ? "8.8.8.8" : $ip) . "/json"));

  $city    = $geo->city ?? 'Olongapo';
  $region  = $geo->region ?? '';
  $country = $geo->country ?? '';
  $location = trim("{$city}, {$region}, {$country}");

  // 2️⃣ Weather via OpenWeatherMap
  $apiKey = $_ENV['OPENWEATHER_API_KEY'] ?? '';
  if (empty($apiKey)) {
    $temp = 0;
    $condition = 'Unavailable';
    $iconUrl = '';
    $weatherAvailable = false;
  } else {
    $weatherUrl = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($city) . "&units=metric&appid={$apiKey}";
    $weatherRaw = @file_get_contents($weatherUrl);
    $weatherData = @json_decode($weatherRaw);

    if ($weatherData && isset($weatherData->main)) {
      $temp      = round($weatherData->main->temp);
      $condition = $weatherData->weather[0]->main ?? 'N/A';
      $iconCode  = $weatherData->weather[0]->icon ?? '01d';
      $iconUrl   = "https://openweathermap.org/img/wn/{$iconCode}@2x.png";
      $weatherAvailable = true;
    } else {
      $temp = 0;
      $condition = 'Unavailable';
      $iconUrl = '';
    }
  }

  $currentTime = date('g:i A');

  // Save to cache with locking
  $cachePayload = [
    'timestamp' => time(),
    'data' => compact('city', 'region', 'country', 'location', 'temp', 'condition', 'iconUrl', 'currentTime')
  ];
  $fp = fopen($cacheFile, 'c+');
  if ($fp && flock($fp, LOCK_EX)) {
    ftruncate($fp, 0);
    fwrite($fp, json_encode($cachePayload));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
  }
}
?>