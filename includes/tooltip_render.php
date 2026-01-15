<?php
function renderTooltipIcon(string $key, array $tooltips): string {
    $message = $tooltips[$key] ?? '';
    if (empty($message)) return '';

    return <<<HTML
    <div x-data="{ show: false }" 
         @mouseenter="show = true" 
         @mouseleave="show = false" 
         class="relative inline-flex items-center">
        <button type="button"
                class="w-4 h-4 rounded-full bg-sky-50 border-2 border-sky-500 text-sky-500 text-xs flex items-center justify-center">
            ?
        </button>
        
        <div x-show="show" 
             x-cloak
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="absolute z-50 bottom-full mb-2 left-1/2 -translate-x-1/2 w-64 p-2 bg-sky-600 text-white text-xs rounded shadow-lg">
            {$message}
            <div class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-2 h-2 rotate-45 bg-sky-600"></div>
        </div>
    </div>
HTML;
}