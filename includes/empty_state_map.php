<?php
// includes/empty_state_map.php

function getEmptyStateSvg($name) {
  $illustrations = [
    'no-documents-found' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16" viewBox="0 0 64 64" fill="none">
                              <rect x="16" y="12" width="32" height="40" rx="4" fill="#4cb2ffff"/>
                              <path d="M24 20 L40 20 M24 28 L40 28 M24 36 L40 36" stroke="#e2f3ffff" stroke-width="3" stroke-linecap="round"/>
                              <circle cx="48" cy="48" r="12" fill="#ffffffff"/>
                              <path d="M44 44 L52 52 M52 44 L44 52" stroke="#4cb2ffff" stroke-width="3" stroke-linecap="round"/>
                            </svg>',
    'no-tour-packages' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16" viewBox="0 0 64 64" fill="none">
                            <rect x="12" y="20" width="40" height="32" rx="4" fill="#327CEA"/>
                            <rect x="24" y="12" width="16" height="8" rx="2" fill="#A5D6D9"/>
                            <path d="M20 44 L44 20" stroke="#F8C8DC" stroke-width="4" stroke-linecap="round"/>
                          </svg>',
    'no-itineraries-found' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16" viewBox="0 0 64 64" fill="none">
                               <rect x="12" y="12" width="40" height="40" rx="4" fill="#4cb2ffff"/>
                               <path d="M32 20 C36 20 40 24 40 28 C40 32 36 36 32 36 C28 36 24 32 24 28" stroke="#F9E2AF" stroke-width="3" stroke-linecap="round"/>
                               <circle cx="32" cy="28" r="2" fill="#ffffffff"/>
                             </svg>',
    'no-inclusions-listed' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16" viewBox="0 0 64 64" fill="none">
                               <rect x="16" y="12" width="32" height="40" rx="4" fill="#327CEA"/>
                               <path d="M24 24 L28 28 L24 32 M36 24 L40 28 L36 32" stroke="#A5D6D9" stroke-width="3" stroke-linecap="round"/>
                               <circle cx="48" cy="48" r="12" fill="#F9E2AF"/>
                               <path d="M44 44 L52 52 M52 44 L44 52" stroke="#327CEA" stroke-width="3" stroke-linecap="round"/>
                             </svg>',
    'no-clients-found' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16" viewBox="0 0 64 64" fill="none">
                            <circle cx="32" cy="32" r="28" fill="#fff3d9ff"/>
                            <path d="M24 24 C24 20.686 26.686 18 30 18 C33.314 18 36 20.686 36 24 C36 27.314 33.314 30 30 30 C26.686 30 24 27.314 24 24 Z M28 36 C28 34.895 28.895 34 30 34 C31.105 34 32 34.895 32 36 V40 C32 41.105 31.105 42 30 42 C28.895 42 28 41.105 28 40 V36 Z M36 36 C36 34.895 36.895 34 38 34 C39.105 34 40 34.895 40 36 V40 C40 41.105 39.105 42 38 42 C36.895 42 36 41.105 36 40 V36 Z" fill="#327CEA"/>
                            <path d="M20 44 L44 20" stroke="#F8C8DC" stroke-width="4" stroke-linecap="round"/>
                          </svg>'
  ];

  return $illustrations[$name] ?? '';
}
?>