<?php
/**
 * Returns the itinerary day number for today,
 * or null if today is outside the trip date range.
 */
function getTodayItineraryDay(?string $tripStartDate, ?string $tripEndDate): ?int {
  $today = date("Y-m-d");

  // Validate input
  if (!$tripStartDate || !$tripEndDate) return null;

  if ($today < $tripStartDate || $today > $tripEndDate) {
    return null; // Today is outside the trip range
  }

  try {
    $start = new DateTime($tripStartDate);
    $now   = new DateTime($today);
    return $start->diff($now)->days + 1;
  } catch (Exception $e) {
    // Fallback if date parsing fails
    return null;
  }
}