<?php
// includes/icon_map.php

function getIconSvg($name) {
  $icons = [
    'chart-bar' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="#327CEA" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>',
    'chat-alt' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="#327CEA" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd" />
                    </svg>',
    'briefcase' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="#327CEA" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M6 6V5a3 3 0 013-3h2a3 3 0 013 3v1h2a2 2 0 012 2v3.57A22.952 22.952 0 0110 13a22.95 22.95 0 01-8-1.43V8a2 2 0 012-2h2zm2-1a1 1 0 011-1h2a1 1 0 011 1v1H8V5zm1 5a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2a1 1 0 01-1-1v-4z" clip-rule="evenodd" />
                    </svg>',
    'cog' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="#327CEA" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.532 1.532 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
              </svg>',
    'clock' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="#327CEA" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 001.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                </svg>',
    'location' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="#327CEA" viewBox="0 0 20 20">
                     <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                   </svg>',
    'weather' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="#327CEA" viewBox="0 0 20 20">
                   <path fill-rule="evenodd" d="M5.25 9a6.5 6.5 0 0113 0 6.5 6.5 0 01-6.5 6.5c-.334 0-.665-.026-.993-.077a1.725 1.725 0 00-2.799 1.085A2.077 2.077 0 018 18.25c0 .414-.336.75-.75.75H5.5a.75.75 0 01-.75-.75c0-1.022.258-1.983.708-2.824A1.725 1.725 0 004.743 13.6c-.81-.34-1.1-1.367-.708-2.099C4.517 10.483 5.25 9 5.25 9z" clip-rule="evenodd" />
                 </svg>',
    'document' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="#327CEA" viewBox="0 0 20 20">
                     <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                   </svg>',
    'itinerary' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="#327CEA" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M12 1.586l-4 4v12.828l4-4V1.586zM3.707 3.293A1 1 0 002 4v10a1 1 0 001.707.707L8 10.414V3.586L3.707 3.293zM17.707 5.293L13.414 1A1 1 0 0012 1v10.414l4.293-4.293a1 1 0 000-1.414z" clip-rule="evenodd" />
                    </svg>',
    'camera' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="#327CEA" viewBox="0 0 20 20">
                   <path fill-rule="evenodd" d="M4 5a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.414-1.414A1 1 0 0010.586 3h-1.17a1 1 0 00-.707.293L7.293 4.707A1 1 0 016.586 5H4zm6 7a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                 </svg>',
    'email' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="#327CEA" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M2.94 6.412A2 2 0 002 8.108V16a2 2 0 002 2h12a2 2 0 002-2V8.108a2 2 0 00-.94-1.696l-6-3.75a2 2 0 00-2.12 0l-6 3.75zm2.615 2.423a1 1 0 00-1.41 1.41l4 4a1 1 0 001.41 0l4-4a1 1 0 00-1.41-1.41L10 10.297 5.555 8.835z" clip-rule="evenodd" />
                </svg>',
    'phone' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="#327CEA" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M2 3.5A1.5 1.5 0 013.5 2h1.148a1.5 1.5 0 011.465 1.175l.716 3.223a1.5 1.5 0 01-1.052 1.767l-.933.267c-.722.206-.834.986-.477 1.53l2.087 3.203a1.5 1.5 0 01-.305 1.897l-1.337 1.337a1.5 1.5 0 01-1.854.174A13.585 13.585 0 012 13.386c-.33-1.373.395-2.768 1.645-3.288l1.258-.36-.514-2.313H3.5zm12.5 2a1.5 1.5 0 011.5 1.5v.318a1.5 1.5 0 01-.945 1.395l-1.255.502a1.5 1.5 0 01-1.874-.404l-.835-1.252a1.5 1.5 0 01.175-1.897l1.337-1.337a1.5 1.5 0 011.897-.305l.502 1.255z" clip-rule="evenodd" />
                </svg>',
    'home' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="#327CEA" viewBox="0 0 20 20">
                 <path fill-rule="evenodd" d="M10.707 2.293a1 1 0 00-1.414 0l-7 7A1 1 0 003 11h1v6a2 2 0 002 2h8a2 2 0 002-2v-6h1a1 1 0 00.707-1.707l-7-7zM14 17H6v-6h8v6z" clip-rule="evenodd" />
               </svg>',
    'calendar' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="#327CEA" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                  </svg>',
    'folder' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="#327CEA" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4l2 2h4a2 2 0 012 2v7a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd" />
                </svg>',
    'star' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="#327CEA" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
              </svg>',
    'settings' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="#327CEA" viewBox="0 0 20 20">
                     <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.532 1.532 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                   </svg>',
    'messages' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="#327CEA" viewBox="0 0 20 20">
                     <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd" />
                   </svg>',
    'map' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="#327CEA" viewBox="0 0 20 20">
               <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd" />
             </svg>',
    'no-active-clients' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 20 20">
                              <circle cx="10" cy="10" r="9" fill="#dbeafe" />
                              <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" fill="#327CEA" clip-rule="evenodd" />
                            </svg>',
    'active-clients' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="#327CEA" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                        </svg>',
    'no-favorites' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="#327CEA" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                      </svg>',
    'no-tour-packages' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="#327CEA" viewBox="0 0 20 20">
                             <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                           </svg>',
    'temperature' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="#327CEA" viewBox="0 0 20 20">
                       <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1.323l3.954 1.582 1.496-.769a1 1 0 011.414 1.414l-1.496.769A5 5 0 0014 14.645V17a1 1 0 11-2 0v-2.355a5 5 0 00-3.954-4.769l-1.496.769a1 1 0 01-1.414-1.414l1.496-.769A5 5 0 008 5.355V3a1 1 0 011-1zm-3 5a3 3 0 106 0 3 3 0 00-6 0v6a3 3 0 006 0V7z" clip-rule="evenodd" />
                     </svg>'
  ];

  return $icons[$name] ?? '';
}