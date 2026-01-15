<?php
/**
 * Fetch checklist template by ID.
 * Returns full row with decoded checklist JSON.
 */
function getChecklistByTemplateId(int $templateId, mysqli $conn): ?array {
  $stmt = $conn->prepare("SELECT * FROM checklist_templates WHERE id = ?");
  if (!$stmt) return null;

  $stmt->bind_param("i", $templateId);
  $stmt->execute();
  $result = $stmt->get_result();
  $raw = $result->fetch_assoc();

  if (!$raw) return null;

  // Decode checklist JSON
  $raw['checklist_data'] = json_decode($raw['checklist_json'] ?? '[]', true);

  return $raw;
}