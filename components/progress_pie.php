<?php
function renderProgressPie(
  int $percent = 0,
  int $size = 96,
  int $strokeWidth = 6,
  string $color = '#0ea5e9',
  string $background = '#e5e7eb'
): string {
  $percent = max(0, min($percent, 100));
  $radius = ($size - $strokeWidth) / 2;
  $circumference = 2 * M_PI * $radius;
  $offset = $circumference * (1 - ($percent / 100));
  $center = $size / 2;

  return <<<HTML
  <div class="relative inline-block" style="width:{$size}px; height:{$size}px;">
    <svg width="{$size}" height="{$size}" viewBox="0 0 {$size} {$size}" class="rotate-[-90deg]">
      <circle cx="{$center}" cy="{$center}" r="{$radius}" fill="none" stroke="{$background}" stroke-width="{$strokeWidth}" />
      <circle cx="{$center}" cy="{$center}" r="{$radius}" fill="none" stroke="{$color}" stroke-width="{$strokeWidth}"
        stroke-dasharray="{$circumference}" stroke-dashoffset="{$offset}" stroke-linecap="round" />
    </svg>
    <div class="absolute inset-0 flex items-center justify-center">
      <span class="text-xs text-slate-700 font-medium">{$percent}%</span>
    </div>
  </div>
HTML;
}
?>