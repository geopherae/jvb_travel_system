<!-- System Updates Modal -->
<style>
  @keyframes gradientShift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
  }
  
  @keyframes fadeInUp {
    from {
      opacity: 0;
      transform: translateY(20px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
  
  @keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
  }
  
  @keyframes pulse-glow {
    0%, 100% { box-shadow: 0 0 20px rgba(14, 165, 233, 0.3); }
    50% { box-shadow: 0 0 30px rgba(14, 165, 233, 0.5); }
  }
  
  .animate-gradient {
    background-size: 200% 200%;
    animation: gradientShift 3s ease infinite;
  }
  
  .animate-fade-in-up {
    animation: fadeInUp 0.6s ease-out forwards;
  }
  
  .animate-float {
    animation: float 3s ease-in-out infinite;
  }
  
  .animate-pulse-glow {
    animation: pulse-glow 2s ease-in-out infinite;
  }
  
  .delay-100 { animation-delay: 0.1s; }
  .delay-200 { animation-delay: 0.2s; }
  .delay-300 { animation-delay: 0.3s; }
  .delay-400 { animation-delay: 0.4s; }
  .delay-500 { animation-delay: 0.5s; }
</style>

<div x-show="showUpdatesModal" @keydown.escape="showUpdatesModal = false"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
     style="display: none;"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
  
  <!-- Modal Container -->
  <div class="bg-white rounded-xl shadow-2xl w-[800px] min-h-[600px] max-h-[600px] overflow-hidden flex flex-col">
    
    <!-- Header -->
    <div class="flex items-center justify-between px-8 py-7 border-b border-gray-100 bg-white">
      <h3 class="text-xl font-semibold text-gray-900">System Updates</h3>
      <button @click="showUpdatesModal = false" class="text-gray-500 hover:text-gray-700 hover:bg-gray-100 p-2 rounded-lg transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
      </button>
    </div>
    
    <!-- Content Pages -->
    <div class="overflow-y-auto flex-1 px-8 py-10 space-y-4">
      
      <!-- Welcome Page (Page 0) -->
      <div x-show="currentUpdatePage === 0" class="space-y-4">
        <div class="text-center space-y-4">
          <!-- Logo with glow effect 
          <div class="relative inline-block mx-auto">
            <div class="absolute inset-0 bg-gradient-to-r from-blue-400 via-green-400 to-purple-400 rounded-2xl blur-xl opacity-30 -z-10"></div>
            <img src="../images/JVB_Logo_outlined.png" alt="JVB Travel" class="w-40 h-18 mx-auto rounded-2xl object-contain">
          </div>-->
          
          <!-- Main heading -->
          <div class="space-y-0 min-w-[400px]">
            <p class="text-slate-400 font-semibold text-sm uppercase tracking-wide animate-fade-in-up opacity-0 delay-100 mb-0">Complete visa packages when all requirements are met</p>
            <h2 class="p-2 text-4xl font-bold bg-gradient-to-r from-sky-500 via-sky-600 to-blue-600 bg-clip-text text-transparent animate-gradient animate-fade-in-up opacity-0 delay-200">
              Exciting Updates!
            </h2>
            <p class="mb-4 text-xl text-slate-600 font-medium animate-fade-in-up opacity-0 delay-300">
              Discover what features have been added.
            </p>
          </div>
        </div>
        
        <!-- Feature highlights grid -->
        <div class="pt-8 pb-4 grid grid-cols-3 gap-4 animate-fade-in-up opacity-0 delay-400">
          <div class="bg-blue-50 rounded-lg p-4 text-center border border-blue-200 hover:shadow-lg transition-shadow duration-300 animate-float" style="animation-delay: 0.5s;">
            <div class="text-2xl font-bold text-blue-600 mb-2">3</div>
            <p class="text-xs text-gray-600 font-medium">Major Features</p>
          </div>
          <div class="bg-green-50 rounded-lg p-4 text-center border border-green-200 hover:shadow-lg transition-shadow duration-300 animate-float" style="animation-delay: 0.7s;">
            <div class="text-2xl font-bold text-green-600 mb-2">1</div>
            <p class="text-xs text-gray-600 font-medium">Major Upgrade</p>
          </div>
          <div class="bg-purple-50 rounded-lg p-4 text-center border border-purple-200 hover:shadow-lg transition-shadow duration-300 animate-float" style="animation-delay: 0.9s;">
            <div class="text-2xl font-bold text-purple-600 mb-2">∞</div>
            <p class="text-xs text-gray-600 font-medium">Possibilities</p>
          </div>
        </div>
        
        <!-- Call to action -->
        <div class="bg-gradient-to-r from-blue-50 to-purple-50 border border-blue-100 rounded-lg p-8 text-center space-y-4 animate-fade-in-up opacity-0 delay-500" style="box-shadow: 0 0 20px rgba(14, 165, 233, 0.2);">
          <p class="text-sky-600 font-semibold text-lg">Ready to explore?</p>
          <p class="text-gray-700 text-sm leading-relaxed">
            We've focused on delivering features that matter to you. Navigate through the updates to learn about powerful new capabilities in visa processing, messaging, and package management.
          </p>
        </div>
      </div>
      
      <!-- Visa Processing Feature (Page 1) -->
      <div x-show="currentUpdatePage === 1" class="space-y-6">
        <div class="text-center space-y-3">
          <svg class="w-14 h-14 mx-auto text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          <div>
            <h3 class="text-2xl font-semibold text-gray-900">Visa Processing</h3>
            <p class="text-xs font-medium text-blue-600 mt-2 uppercase tracking-wide">Featured Addition</p>
          </div>
        </div>
        <p class="text-gray-700 text-sm leading-relaxed">
          Visa Processing is now enabled. Manage your clients' visa applications with the same ease as the booking system.
        </p>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 space-y-4">
          <p class="font-semibold text-gray-900 text-sm">You can now:</p>
          <ul class="space-y-3">
            <li class="flex items-start gap-3">
              <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mt-2 flex-shrink-0"></span>
              <span class="text-gray-700 text-sm">Create individual or grouped visa applications</span>
            </li>
            <li class="flex items-start gap-3">
              <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mt-2 flex-shrink-0"></span>
              <span class="text-gray-700 text-sm">Add companions to applications</span>
            </li>
            <li class="flex items-start gap-3">
              <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mt-2 flex-shrink-0"></span>
              <span class="text-gray-700 text-sm">Assign visa packages with specific requirements</span>
            </li>
            <li class="flex items-start gap-3">
              <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mt-2 flex-shrink-0"></span>
              <span class="text-gray-700 text-sm">Upload and manage visa-related documents</span>
            </li>
            <li class="flex items-start gap-3">
              <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mt-2 flex-shrink-0"></span>
              <span class="text-gray-700 text-sm">Update document statuses and track progress</span>
            </li>
            <li class="flex items-start gap-3">
              <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mt-2 flex-shrink-0"></span>
              <span class="text-gray-700 text-sm">Upload client's visa when requirements are complete.</span>
            </li>
          </ul>
        </div>
      </div>
      
      <!-- Revamped Messages Feature (Page 2) -->
      <div x-show="currentUpdatePage === 2" class="space-y-6">
        <div class="text-center space-y-3">
          <svg class="w-14 h-14 mx-auto text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
          </svg>
          <div>
            <h3 class="text-2xl font-semibold text-gray-900">Revamped Messages</h3>
            <p class="text-xs font-medium text-green-600 mt-2 uppercase tracking-wide">Featured Addition</p>
          </div>
        </div>
        <p class="text-gray-700 text-sm leading-relaxed">
          Our messaging system is now more powerful and user-friendly than ever.
        </p>
        <div class="bg-green-50 border border-green-200 rounded-lg p-6 space-y-4">
          <p class="font-semibold text-gray-900 text-sm">What's new:</p>
          <ul class="space-y-3">
            <li class="flex items-start gap-3">
              <span class="w-1.5 h-1.5 bg-green-500 rounded-full mt-2 flex-shrink-0"></span>
              <div class="text-sm text-gray-700">
                <span class="font-medium">Online/Offline Indicators</span>
                <span class="block text-gray-600">Know when team members and clients are available</span>
              </div>
            </li>
            <li class="flex items-start gap-3">
              <span class="w-1.5 h-1.5 bg-green-500 rounded-full mt-2 flex-shrink-0"></span>
              <div class="text-sm text-gray-700">
                <span class="font-medium">Mobile Support</span>
                <span class="block text-gray-600">Seamless messaging on all devices</span>
              </div>
            </li>
            <li class="flex items-start gap-3">
              <span class="w-1.5 h-1.5 bg-green-500 rounded-full mt-2 flex-shrink-0"></span>
              <div class="text-sm text-gray-700">
                <span class="font-medium">Message Sounds</span>
                <span class="block text-gray-600">Get notified with audio alerts for incoming messages</span>
              </div>
            </li>
            <li class="flex items-start gap-3">
              <span class="w-1.5 h-1.5 bg-green-500 rounded-full mt-2 flex-shrink-0"></span>
              <div class="text-sm text-gray-700">
                <span class="font-medium">Toast Notifications</span>
                <span class="block text-gray-600">Gentle notifications when receiving messages</span>
              </div>
            </li>
          </ul>
        </div>
      </div>
      
      <!-- Client Access Updates (Page 3) -->
      <div x-show="currentUpdatePage === 3" class="space-y-6">
        <div class="text-center space-y-3">
          <svg class="w-14 h-14 mx-auto text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"></path>
          </svg>
          <div>
            <h3 class="text-2xl font-semibold text-gray-900">Client Access Updates</h3>
            <p class="text-xs font-medium text-sky-600 mt-2 uppercase tracking-wide">Platform Experience</p>
          </div>
        </div>
        <div class="bg-sky-50 border border-sky-200 rounded-lg p-6 space-y-3">
          <p class="font-semibold text-gray-900 text-sm">Client Dashboards</p>
          <p class="text-gray-700 text-sm leading-relaxed">
            Clients now have their own dashboard experience. For accounts created with the Travel Booking &amp; Visa Processing type,
            the system provides two dashboards that keep services connected and reinforce the platform as a single, centralized hub.
          </p>
        </div>
        <div class="bg-sky-50 border border-sky-200 rounded-lg p-6 space-y-3">
          <p class="font-semibold text-gray-900 text-sm">Group Applications Access</p>
          <p class="text-gray-700 text-sm leading-relaxed">
            Group applications now generate a group access code and an individual access code. Log in with the group code to view the
            group documents and overall status, or use an individual code to access only personal files and progress.
          </p>
        </div>
      </div>

      <!-- Small Improvements (Page 4) -->
      <div x-show="currentUpdatePage === 4" class="space-y-6">
        <div class="text-center space-y-3">
          <svg class="w-14 h-14 mx-auto text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
          </svg>
          <div>
            <h3 class="text-2xl font-semibold text-gray-900">Small Improvements</h3>
            <p class="text-xs font-medium text-purple-600 mt-2 uppercase tracking-wide">Quality of Life Updates</p>
          </div>
        </div>
        <div class="space-y-4">
          <div class="bg-purple-50 border border-purple-200 rounded-lg p-6">
            <p class="font-semibold text-gray-900 text-sm mb-3">Tour & Visa Packages Management</p>
            <p class="text-gray-700 text-sm leading-relaxed">
              Tour and Visa Packages now have Archive and Delete features. Better control over your package library with streamlined management.
            </p>
          </div>
        </div>
      </div>
      
    </div>
    
    <!-- Footer with Navigation -->
    <div class="bg-gray-50 border-t border-gray-100 px-8 py-6 flex items-center justify-between">
      
      <!-- Left Button -->
      <button @click="currentUpdatePage > 0 && (currentUpdatePage--)"
              :disabled="currentUpdatePage === 0"
              class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-200 rounded-lg transition disabled:opacity-40 disabled:cursor-not-allowed">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
      </button>
      
      <!-- Pagination Dots -->
      <div class="flex items-center gap-2">
        <button @click="currentUpdatePage = 0" :class="currentUpdatePage === 0 ? 'bg-blue-500' : 'bg-gray-300'" 
                class="w-2 h-2 rounded-full transition hover:bg-blue-400"></button>
        <button @click="currentUpdatePage = 1" :class="currentUpdatePage === 1 ? 'bg-blue-500' : 'bg-gray-300'" 
                class="w-2 h-2 rounded-full transition hover:bg-blue-400"></button>
        <button @click="currentUpdatePage = 2" :class="currentUpdatePage === 2 ? 'bg-blue-500' : 'bg-gray-300'" 
                class="w-2 h-2 rounded-full transition hover:bg-blue-400"></button>
        <button @click="currentUpdatePage = 3" :class="currentUpdatePage === 3 ? 'bg-blue-500' : 'bg-gray-300'" 
                class="w-2 h-2 rounded-full transition hover:bg-blue-400"></button>
        <button @click="currentUpdatePage = 4" :class="currentUpdatePage === 4 ? 'bg-blue-500' : 'bg-gray-300'" 
          class="w-2 h-2 rounded-full transition hover:bg-blue-400"></button>
      </div>
      
      <!-- Right Button -->
            <button @click="currentUpdatePage < 4 && (currentUpdatePage++)"
              :disabled="currentUpdatePage === 4"
              class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-200 rounded-lg transition disabled:opacity-40 disabled:cursor-not-allowed">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
        </svg>
      </button>
      
    </div>
    
  </div>
  
</div>
