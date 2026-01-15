<?php
session_start();

// 🔐 Auth check
if (empty($_SESSION['admin']['id'])) {
  header("Location: admin_login.php");
  exit();
}

// 📦 Includes
include_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/status-helpers.php';
require_once __DIR__ . '/../components/status_alert.php';


// 🚫 Disable caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// 🛠 DB connection
require_once __DIR__ . '/../actions/db.php';


$adminId = $_SESSION['admin']['id'] ?? null;

// 👤 Admin info
$isAdmin = true;
$adminName = $_SESSION['first_name'] ?? 'Admin';

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin System Manual</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <style>
    .priority-critical { @apply bg-red-100 text-red-700 border-red-200; }
    .priority-high { @apply bg-orange-100 text-orange-700 border-orange-200; }
    .priority-medium { @apply bg-blue-100 text-blue-700 border-blue-200; }
    .priority-low { @apply bg-gray-100 text-gray-700 border-gray-200; }
  </style>
</head>
<body class="font-poppins text-gray-800 overflow-hidden" x-data="{ searchQuery: '', activeSection: '' }">

  <!-- Sidebar -->
  <?php include '../components/admin_sidebar.php'; ?>

  <!-- Right Sidebar Panel -->
  <?php
    $isAdmin = false;
    include '../components/right-panel.php';
  ?>

  <main class="ml-0 md:ml-64 lg:mr-80 h-screen overflow-y-auto p-6 space-y-8 relative z-0">

<div class="max-w-5xl mx-auto py-8 px-4 space-y-8">
  <!-- Header -->
  <header class="text-center space-y-4">
    <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-sky-500 to-blue-600 rounded-2xl shadow-lg">
      <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
      </svg>
    </div>
    <h1 class="text-4xl font-bold text-gray-900">JVB Travel System</h1>
    <p class="text-lg text-gray-600">Admin User Manual</p>
    <p class="text-sm text-gray-500 max-w-2xl mx-auto">A comprehensive guide to managing clients, bookings, documents, and tour packages efficiently.</p>
  </header>

  <!-- Priority Legend -->
  <div class="bg-gradient-to-r from-sky-50 to-blue-50 rounded-xl p-6 shadow-sm border border-sky-100">
    <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
      <svg class="w-5 h-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      Understanding Priority Levels
    </h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
      <div class="flex items-center gap-2 bg-white px-3 py-2 rounded-lg border border-red-200">
        <span class="px-2 py-0.5 bg-red-100 text-red-700 text-xs font-bold rounded">CRITICAL</span>
        <span class="text-xs text-gray-600">Learn first</span>
      </div>
      <div class="flex items-center gap-2 bg-white px-3 py-2 rounded-lg border border-orange-200">
        <span class="px-2 py-0.5 bg-orange-100 text-orange-700 text-xs font-bold rounded">HIGH</span>
        <span class="text-xs text-gray-600">Core features</span>
      </div>
      <div class="flex items-center gap-2 bg-white px-3 py-2 rounded-lg border border-blue-200">
        <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-xs font-bold rounded">MEDIUM</span>
        <span class="text-xs text-gray-600">Regular tasks</span>
      </div>
      <div class="flex items-center gap-2 bg-white px-3 py-2 rounded-lg border border-gray-200">
        <span class="px-2 py-0.5 bg-gray-100 text-gray-700 text-xs font-bold rounded">LOW</span>
        <span class="text-xs text-gray-600">Optional</span>
      </div>
    </div>
  </div>

  <!-- Quick Start Guide -->
  <div class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden">
    <div class="bg-gradient-to-r from-sky-600 to-blue-600 px-6 py-4">
      <h2 class="text-xl font-bold text-white flex items-center gap-2">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
        </svg>
        Quick Start: Your First 5 Minutes
      </h2>
    </div>
    <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
      <a href="#section1" class="group hover:shadow-lg transition-all p-4 border border-gray-200 rounded-lg hover:border-sky-400">
        <div class="flex items-start gap-3">
          <div class="w-8 h-8 bg-sky-100 text-sky-600 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0">1</div>
          <div>
            <h3 class="font-semibold text-gray-900 group-hover:text-sky-600">Login</h3>
            <p class="text-sm text-gray-600 mt-1">Access the system</p>
          </div>
        </div>
      </a>
      <a href="#section3" class="group hover:shadow-lg transition-all p-4 border border-gray-200 rounded-lg hover:border-sky-400">
        <div class="flex items-start gap-3">
          <div class="w-8 h-8 bg-sky-100 text-sky-600 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0">2</div>
          <div>
            <h3 class="font-semibold text-gray-900 group-hover:text-sky-600">Add Client</h3>
            <p class="text-sm text-gray-600 mt-1">Create first booking</p>
          </div>
        </div>
      </a>
      <a href="#section7" class="group hover:shadow-lg transition-all p-4 border border-gray-200 rounded-lg hover:border-sky-400">
        <div class="flex items-start gap-3">
          <div class="w-8 h-8 bg-sky-100 text-sky-600 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0">3</div>
          <div>
            <h3 class="font-semibold text-gray-900 group-hover:text-sky-600">Track Status</h3>
            <p class="text-sm text-gray-600 mt-1">Monitor progress</p>
          </div>
        </div>
      </a>
    </div>
  </div>

  <!-- Navigation Table -->
<nav class="bg-white rounded-xl shadow-md p-6 border border-gray-200">
  <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
    <svg class="w-6 h-6 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
    </svg>
    Complete Guide Navigation
  </h2>
  <div class="space-y-3">
    <a href="#section1" class="flex items-center justify-between p-3 hover:bg-red-50 rounded-lg transition-colors group border border-transparent hover:border-red-200">
      <div class="flex items-center gap-3">
        <span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-bold rounded">CRITICAL</span>
        <span class="font-semibold text-gray-800 group-hover:text-red-700">1. System Login</span>
      </div>
      <svg class="w-5 h-5 text-gray-400 group-hover:text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
      </svg>
    </a>

    <a href="#section7" class="flex items-center justify-between p-3 hover:bg-red-50 rounded-lg transition-colors group border border-transparent hover:border-red-200">
      <div class="flex items-center gap-3">
        <span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-bold rounded">CRITICAL</span>
        <span class="font-semibold text-gray-800 group-hover:text-red-700">2. Understanding Client Statuses</span>
      </div>
      <svg class="w-5 h-5 text-gray-400 group-hover:text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
      </svg>
    </a>

    <a href="#section3" class="flex items-center justify-between p-3 hover:bg-orange-50 rounded-lg transition-colors group border border-transparent hover:border-orange-200">
      <div class="flex items-center gap-3">
        <span class="px-2 py-1 bg-orange-100 text-orange-700 text-xs font-bold rounded">HIGH</span>
        <span class="font-semibold text-gray-800 group-hover:text-orange-700">3. Managing Clients & Documents</span>
      </div>
      <svg class="w-5 h-5 text-gray-400 group-hover:text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
      </svg>
    </a>

    <a href="#section4" class="flex items-center justify-between p-3 hover:bg-blue-50 rounded-lg transition-colors group border border-transparent hover:border-blue-200">
      <div class="flex items-center gap-3">
        <span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-bold rounded">MEDIUM</span>
        <span class="font-semibold text-gray-800 group-hover:text-blue-700">4. Tour Packages</span>
      </div>
      <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
      </svg>
    </a>

    <a href="#section2" class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg transition-colors group border border-transparent hover:border-gray-200">
      <div class="flex items-center gap-3">
        <span class="px-2 py-1 bg-gray-100 text-gray-700 text-xs font-bold rounded">LOW</span>
        <span class="font-semibold text-gray-800 group-hover:text-gray-700">5. Dashboard Overview</span>
      </div>
      <svg class="w-5 h-5 text-gray-400 group-hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
      </svg>
    </a>

    <a href="#section5" class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg transition-colors group border border-transparent hover:border-gray-200">
      <div class="flex items-center gap-3">
        <span class="px-2 py-1 bg-gray-100 text-gray-700 text-xs font-bold rounded">LOW</span>
        <span class="font-semibold text-gray-800 group-hover:text-gray-700">6. System Settings</span>
      </div>
      <svg class="w-5 h-5 text-gray-400 group-hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
      </svg>
    </a>

    <a href="#section6" class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg transition-colors group border border-transparent hover:border-gray-200">
      <div class="flex items-center gap-3">
        <span class="px-2 py-1 bg-gray-100 text-gray-700 text-xs font-bold rounded">LOW</span>
        <span class="font-semibold text-gray-800 group-hover:text-gray-700">7. Right Panel & Audit Logs</span>
      </div>
      <svg class="w-5 h-5 text-gray-400 group-hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
      </svg>
    </a>
  </div>
</nav>

  <!-- Section 1: Login -->
  <section id="section1" class="bg-white rounded-xl shadow-md p-8 border-l-4 border-red-500 scroll-mt-20">
    <div class="flex items-start justify-between mb-6">
      <div>
        <div class="flex items-center gap-3 mb-2">
          <span class="px-3 py-1 bg-red-100 text-red-700 text-xs font-bold rounded-full">CRITICAL</span>
          <h2 class="text-2xl font-bold text-gray-900">1. System Login</h2>
        </div>
        <p class="text-gray-600">Your entry point to the JVB Travel System</p>
      </div>
      <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
        </svg>
      </div>
    </div>

    <div class="bg-sky-50 border border-sky-200 rounded-lg p-4 mb-6">
      <div class="flex gap-3">
        <svg class="w-5 h-5 text-sky-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div>
          <p class="font-semibold text-sky-900 mb-1">Why this matters</p>
          <p class="text-sm text-sky-800">Secure authentication ensures only authorized travel agents can access sensitive client information.</p>
        </div>
      </div>
    </div>

    <div class="mb-6 border border-gray-200 rounded-xl overflow-hidden shadow-sm">
      <img src="../images/system_manual/login.jpeg" alt="Admin Login Screen" class="w-full h-auto">
    </div>

    <div class="space-y-4">
      <div class="flex items-start gap-3 p-4 bg-gray-50 rounded-lg">
        <div class="w-8 h-8 bg-sky-600 text-white rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0">1</div>
        <div class="flex-1">
          <p class="text-gray-800"><strong>Navigate</strong> to the Admin Login page using your designated system URL</p>
        </div>
      </div>
      <div class="flex items-start gap-3 p-4 bg-gray-50 rounded-lg">
        <div class="w-8 h-8 bg-sky-600 text-white rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0">2</div>
        <div class="flex-1">
          <p class="text-gray-800"><strong>Enter</strong> your registered email address and password</p>
        </div>
      </div>
      <div class="flex items-start gap-3 p-4 bg-gray-50 rounded-lg">
        <div class="w-8 h-8 bg-sky-600 text-white rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0">3</div>
        <div class="flex-1">
          <p class="text-gray-800"><strong>Click</strong> the Login button to authenticate</p>
        </div>
      </div>
      <div class="flex items-start gap-3 p-4 bg-green-50 rounded-lg border border-green-200">
        <svg class="w-6 h-6 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div class="flex-1">
          <p class="text-gray-800"><strong>Success!</strong> You'll be redirected to the Admin Dashboard automatically</p>
        </div>
      </div>
    </div>

    <div class="mt-6 bg-amber-50 border border-amber-200 rounded-lg p-4">
      <div class="flex gap-3">
        <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <div>
          <p class="font-semibold text-amber-900 mb-1">Security Tip</p>
          <p class="text-sm text-amber-800">Your session will automatically expire after a period of inactivity. You can adjust this timeout in System Settings.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Section 2 -->
  <section id="section2" class="bg-white rounded-lg shadow p-6 space-y-4 scroll-mt-20">
    <h2 class="text-xl font-semibold text-sky-700">2. Navigating the Admin Dashboard</h2>
    <ol class="list-decimal list-inside space-y-1 ml-4">
      <li>Review the summary cards displaying key statistics, such as total clients and pending uploads.</li>
      <li>Use the sidebar or top navigation to access the following modules:
        <ul class="list-disc list-inside ml-4 mt-2">
          <li>Tour Packages</li>
          <li>Clients</li>
          <li>Documents</li>
          <li>System Settings</li>
          <li>Audit Logs</li>
        </ul>
      </li>
      <li>Address any modal alerts or notifications that appear on the dashboard.</li>
    </ol>
  </section>

  <!-- Section 3 -->
  <section id="section3" class="bg-white rounded-lg shadow p-6 space-y-4 scroll-mt-20">
    <h2 class="text-xl font-semibold text-sky-700">3. Managing Clients</h2>

    <h3 id="section3.1 class="font-medium">3.1 Adding a Client</h3>
        <div class="my-6 border border-gray-200 rounded-lg overflow-hidden shadow-sm bg-white">
  <figure class="p-4">
    <img src="../images/system_manual/add_client_modal.jpeg" alt="Descriptive alt text for accessibility"
         class="w-full h-auto rounded-md border border-gray-100 shadow-sm">
  </figure>
</div>
    <ol class="list-decimal list-inside space-y-1 ml-4">
      <li>From the Admin Dashboard, navigate to the Clients module and locate the Clients Table.</li>
      <li>Click the <strong>Add Client</strong> button.</li>
      <li>Complete the required fields in the client registration form:
        <ul class="list-disc list-inside ml-4 mt-2">
          <li>Full Name</li>
          <li>Email Address</li>
          <li>Phone Number (must start with "09" and contain 11 digits)</li>
          <li>Address</li>
          <li>Access Code (used for client login)</li>
          <li>Assigned Package (optional)</li>
          <li>Booking Number (optional)</li>
          <li>Travel Dates (Departure and Return)</li>
          <li>Booking Date</li>
          <li>Assigned Admin (defaults to the current admin if not specified)</li>
        </ul>
      </li>
      <li>Upload a Profile Photo (accepted formats: JPG, PNG; maximum size: 2MB).</li>
      <li>Click <strong>Add Client</strong> to save the client record.</li>
    </ol>

    <h3 id="section3.2" class="font-medium mt-6">3.2 Viewing a Client Profile</h3>
    <ol class="list-decimal list-inside space-y-1 ml-4">
      <li>From the Admin Dashboard, navigate to the Clients module.</li>
      <li>Use the Search Bar or filters to locate a client by name, email, or status.</li>
      <li>In the Clients Table, under the Actions column, click the <strong>View</strong> button for the desired client.</li>
      <li>You will be redirected to the Client Overview Page.</li>
    </ol>

    <h3 id="section3.3" class="font-medium mt-6">3.3 Navigating the Client Overview Page</h3>
    <p>The Client Overview Page consists of three main tabs:</p>
            <div class="my-6 border border-gray-200 rounded-lg overflow-hidden shadow-sm bg-white">
  <figure class="p-4">
    <img src="../images/system_manual/client_overview_tabs.jpeg" alt="Descriptive alt text for accessibility"
         class="w-auto h-10 rounded-md border border-gray-100 shadow-sm">
  </figure>
</div>
    <ul class="list-disc list-inside ml-4">
      <li>Client & Tour Info</li>
      <li>Itinerary</li>
      <li>Client Trip Photos</li>
    </ul>

    <h4 id="section3.3.1" class="font-medium mt-4">3.3.1 Client & Tour Info Tab</h4>
<p class="mb-4">The tab is divided into two panels:</p>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
  <!-- Left Panel -->
  <div>
    <h5 class="font-semibold text-sky-700 mb-2">Left Panel: Client Information</h5>
    <ul class="list-disc list-inside ml-4 space-y-1">
      <li>Full Name</li>
      <li>Trip Status</li>
      <li>Access Code</li>
      <li>Email Address</li>
      <li>Phone Number</li>
      <li>Address</li>
    </ul>
  </div>

  <!-- Right Panel -->
  <div>
    <h5 class="font-semibold text-sky-700 mb-2">Right Panel: Assigned Tour Details</h5>
    <ul class="list-disc list-inside ml-4 space-y-1">
      <li>Destination</li>
      <li>Assigned Travel Agent</li>
      <li>Booking Number</li>
      <li>Travel Dates</li>
      <li>Duration</li>
    </ul>
  </div>
</div>

    <h4 class="font-medium mt-6">Actions in the Client & Tour Info Tab</h4>
    <ol class="list-decimal list-inside space-y-1 ml-4">
      <li><strong>Updating Client Information</strong>
        <ul class="list-disc list-inside ml-4 mt-1">
          <li>Click the dropdown icon and select <strong>Update Client</strong>.</li>
          <li>Edit Full Name, Phone Number, Email Address, or Address.</li>
          <li>Click <strong>Save</strong> to apply updates.</li>
        </ul>
      </li>
      <li><strong>Archiving a Client</strong>
        <ul class="list-disc list-inside ml-4 mt-1">
          <li>Select <strong>Archive Client</strong> from the dropdown.</li>
          <li>Confirm the action when prompted.</li>
          <li>The client will be moved to the archived list but remain accessible for audit and recovery.</li>
        </ul>
      </li>
      <li><strong>Updating Client Booking Details</strong>
        <ul class="list-disc list-inside ml-4 mt-1">
          <li>Select <strong>Edit Booking Details</strong> from the dropdown.</li>
          <li>Modify Booking Number, Dates, or Assigned Travel Agent.</li>
          <li>Click <strong>Save</strong> to update.</li>
        </ul>
      </li>
      <li><strong>Reassigning a Tour Package</strong>
        <ul class="list-disc list-inside ml-4 mt-1">
          <li>Click <strong>Reassign Package</strong> or <strong>Assign Package</strong>.</li>
        <li>Select a new package and confirm.</li>
        <li>The client’s itinerary and checklist will update based on the new package, replacing any existing itinerary.</li>
        </ul>
        </li>

        <li><strong>Unassigning a Tour Package</strong>
        <ul class="list-disc list-inside ml-4 mt-1">
            <li>In the Tour Information panel, click the dropdown icon and select <strong>Unassign Package</strong>.</li>
            <li>Confirm the action when prompted.</li>
            <li>The client’s current itinerary and booking-related fields will be permanently removed and reset.</li>
        </ul>
        </li>
        </ol>

        <!-- Section 3.3.2 -->
<h4 id="section3.3.2" class="font-medium mt-6">3.3.2 Itinerary Tab</h4>
<p>The Itinerary tab displays a scrollable list of Day Cards, each representing one day of the trip. Each Day Card includes:</p>
<ul class="list-disc list-inside ml-4 space-y-1">
  <li><strong>Day Number</strong>: Displayed in a colored badge (e.g., “Day 1”).</li>
  <li><strong>Day Title</strong>: A short label describing the day (e.g., “Arrival & Hotel Check-in”).</li>
  <li><strong>Activity List</strong>: A bullet-point breakdown of scheduled events, each including:
    <ul class="list-disc list-inside ml-4 mt-1">
      <li><strong>Time</strong>: Optional time slot (e.g., “08:00 AM”).</li>
      <li><strong>Title</strong>: Description of the activity (e.g., “Breakfast at hotel”).</li>
    </ul>
  </li>
</ul>
<p class="mt-2">To customize the itinerary:</p>
<ul class="list-disc list-inside ml-4 space-y-1">
  <li>Add or remove days based on the trip duration.</li>
  <li>Edit the Day Title and add or remove activities for each day.</li>
</ul>

<h4 id="section3.3.3" class="font-medium mt-6">3.3.3 Client Trip Photos Tab</h4>
<ol class="list-decimal list-inside space-y-1 ml-4">
  <li>From the Client Overview Page, select the <strong>Trip Photos</strong> tab.</li>
  <li>The tab displays Day Cards corresponding to each day in the client’s itinerary. Each card includes:
    <ul class="list-disc list-inside ml-4 mt-1">
      <li>Day Number and Day Title</li>
      <li>A grid of up to 6 photo slots per day, with empty slots visually indicated</li>
    </ul>
  </li>
  <li>Click on a photo to open a modal with the following options:
    <ul class="list-disc list-inside ml-4 mt-1">
      <li><strong>Approve</strong>: Marks the photo as verified and ready for inclusion in trip records.</li>
      <li><strong>Reject</strong>: Flags the photo as invalid or inappropriate.</li>
    </ul>
  </li>
  <li>Confirm the selected action.</li>
</ol>

<h3 id="section3.4" class="font-medium mt-10">3.4 Managing Client Documents</h3>

<h4 class="font-medium mt-4">3.4.1 Viewing the Client Documents Table</h4>
<ol class="list-decimal list-inside space-y-1 ml-4">
  <li>From the Client Overview Page, scroll to the <strong>Documents to Review</strong> section.</li>
  <li>If documents have been uploaded, each file will be listed with:
    <ul class="list-disc list-inside ml-4 mt-1">
      <li>File Type</li>
      <li>Upload Date</li>
      <li>Status (Pending, Approved, or Rejected)</li>
    </ul>
  </li>
  <li>Click on a document to preview it.</li>
  <li>Use the status dropdown to mark the document as Approved, Rejected, or Pending.</li>
  <li>If no documents are present, click the <strong>Upload Document</strong> button to manually add a file on behalf of the client.</li>
</ol>

<h4 id="section3.4.2" class="font-medium mt-6">3.4.2 Viewing a Document</h4>
<ol class="list-decimal list-inside space-y-1 ml-4">
  <li>In the Documents Table, locate the desired document.</li>
  <li>Click the <strong>View</strong> button to open a modal displaying:
    <ul class="list-disc list-inside ml-4 mt-1">
      <li>Document Preview</li>
      <li>Upload Timestamp</li>
      <li>File Name and Type</li>
      <li>Current Status (Pending, Approved, or Rejected)</li>
    </ul>
  </li>
</ol>

<h4 id="section3.4.3" class="font-medium mt-6">3.4.3 Approving a Document</h4>
<ol class="list-decimal list-inside space-y-1 ml-4">
  <li>While viewing the document or directly from the Documents Table, locate the Status dropdown or the checkmark action button.</li>
  <li>Select <strong>Approved</strong> from the available options.</li>
  <li>Confirm the action when prompted to update the document status to Approved.</li>
</ol>

<h4 id="section3.4.4" class="font-medium mt-6">3.4.4 Rejecting a Document</h4>
<ol class="list-decimal list-inside space-y-1 ml-4">
  <li>While viewing the document or directly from the Documents Table, locate the Status dropdown or the “X” action button.</li>
  <li>Select <strong>Reject</strong> from the available options.</li>
  <li>Provide a rejection reason for the client to review.</li>
  <li>Confirm the action when prompted to update the document status to Rejected.</li>
</ol>
</section>

<!-- Section 4 -->
<section id="section4" class="bg-white rounded-lg shadow p-6 space-y-4 scroll-mt-20">
  <h2 class="text-xl font-semibold text-sky-700">4. Managing Tour Packages</h2>

  <h3 id="section4.1" class="font-medium">4.1 Accessing the Tour Packages Page</h3>
  <ol class="list-decimal list-inside space-y-1 ml-4">
    <li>From the Admin Dashboard, click <strong>Tour Packages</strong> in the sidebar navigation.</li>
  </ol>

  <h3 id="section4.2" class="font-medium mt-6">4.2 Viewing Available Tour Packages</h3>
  <p>The Tour Packages page is divided into two sections:</p>
  <ol class="list-decimal list-inside space-y-1 ml-4">
    <li><strong>Popular Picks Section</strong>
      <ul class="list-disc list-inside ml-4 mt-1">
        <li>Displays up to 3 packages marked as favorites.</li>
        <li>Each package appears as a large card with:
          <ul class="list-disc list-inside ml-4 mt-1">
            <li>Banner Image</li>
            <li>Package Name</li>
            <li>Destination</li>
            <li>Quick Summary</li>
          </ul>
        </li>
      </ul>
    </li>
    <li><strong>Other Packages Section</strong>
      <ul class="list-disc list-inside ml-4 mt-1">
        <li>Displays all remaining packages as list rows.</li>
        <li>Each row includes:
          <ul class="list-disc list-inside ml-4 mt-1">
            <li>Package Name</li>
            <li>Destination</li>
            <li>Duration</li>
            <li>Assigned Admins</li>
          </ul>
        </li>
      </ul>
    </li>
  </ol>
  <p class="mt-2">To browse packages:</p>
  <ul class="list-disc list-inside ml-4 space-y-1">
    <li>Scroll through both sections to view available packages.</li>
    <li>Click on a card or list row to open a <strong>View Modal</strong> displaying full package details (description, itinerary, inclusions, etc.).</li>
  </ul>

  <h3 id="section4.3" class="font-medium mt-6">4.3 Adding a New Tour Package</h3>
  <ol class="list-decimal list-inside space-y-1 ml-4">
    <li>On the Tour Packages page, click the <strong>Add Tour Package</strong> button.</li>
    <li>A modal will appear with input fields and tabs.</li>
    <li><strong>Upload Banner Image</strong>
      <ul class="list-disc list-inside ml-4 mt-1">
        <li>Click the upload area to select a JPG, JPEG, or PNG file.</li>
        <li>The image will be compressed and previewed automatically.</li>
      </ul>
    </li>
    <li><strong>Enter Package Details</strong>
      <ul class="list-disc list-inside ml-4 mt-1">
        <li>Fill in the following fields:
          <ul class="list-disc list-inside ml-4 mt-1">
            <li>Package Name</li>
            <li>Description</li>
            <li>Origin Airport (select from dropdown)</li>
            <li>Destination Airport (select from dropdown)</li>
            <li>Price</li>
            <li>Duration (number of days and nights)</li>
          </ul>
        </li>
        <li>Optional: Toggle <strong>Mark as Favorite</strong> to feature the package in the Popular Picks section.</li>
      </ul>
    </li>
    <li><strong>Add Itinerary</strong>
      <ul class="list-disc list-inside ml-4 mt-1">
        <li>Navigate to the <strong>Itinerary</strong> tab.</li>
        <li>Add day-by-day entries with:
          <ul class="list-disc list-inside ml-4 mt-1">
            <li>Day Number</li>
            <li>Day Title</li>
            <li>List of activities (with optional time slots)</li>
          </ul>
        </li>
        <li>Follow the same logic as described in the Itinerary section (<a href="#section3-3-2" class="text-sky-600 hover:underline">3.3.2</a>).</li>
      </ul>
    </li>
    <li><strong>Add Inclusions</strong>
      <ul class="list-disc list-inside ml-4 mt-1">
        <li>Navigate to the <strong>Inclusions</strong> tab.</li>
        <li>Add up to 6 items describing what’s included in the package (e.g., hotel stay, airport transfer).</li>
      </ul>
    </li>
    <li><strong>Save Package</strong>
      <ul class="list-disc list-inside ml-4 mt-1">
        <li>Click the <strong>Save Package</strong> button to finalize.</li>
        <li>The new package will appear in the list or Popular Picks section (if marked as favorite).</li>
        <li>An audit log entry will record the creation.</li>
      </ul>
    </li>
  </ol>
</section>

<!-- Section 5 -->
<section id="section5" class="bg-white rounded-lg shadow p-6 space-y-4 scroll-mt-20">
  <h2 class="text-xl font-semibold text-sky-700">5. Managing System Settings</h2>

  <h3 id="section5.1"class="font-medium">5.1 Accessing the Settings Page</h3>
  <ol class="list-decimal list-inside space-y-1 ml-4">
    <li>From the Admin Dashboard, click <strong>Settings</strong> in the sidebar navigation.</li>
    <li>The system will load the <strong>Admin Profile & Settings</strong> page.</li>
  </ol>

  <h3 id="section5.2" class="font-medium mt-6">5.2 Updating Admin Profile Information</h3>
  <ol class="list-decimal list-inside space-y-1 ml-4">
    <li>In the Admin Profile & Settings section, review the following fields:
      <ul class="list-disc list-inside ml-4 mt-1">
        <li>Profile Photo</li>
        <li>Full Name</li>
        <li>Phone Number</li>
        <li>Email Address</li>
        <li>Messenger Link (e.g., Facebook Messenger)</li>
        <li>Admin Bio (short description or role summary)</li>
      </ul>
    </li>
    <li>Click the <strong>Edit Profile</strong> button.</li>
    <li>Modify any of the fields as needed.</li>
    <li>Upload a new profile photo if desired (accepted formats: JPG, JPEG, PNG).</li>
    <li>Click <strong>Save Changes</strong> to apply updates.</li>
    <li>A confirmation message will appear, and the audit log will record the update.</li>
  </ol>

  <h3 id="section5.3" class="font-medium mt-6">5.3 Managing Login Information</h3>
  <ol class="list-decimal list-inside space-y-1 ml-4">
    <li>In the Admin Profile & Settings section, scroll to the <strong>Login Information</strong> section.</li>
    <li>Review your current username and, if needed, update your password.</li>
    <li>Click <strong>Save All Changes</strong> to apply updates.</li>
  </ol>

  <h3 id="section5.4" class="font-medium mt-6">5.4 Adding Another Admin</h3>
  <ol class="list-decimal list-inside space-y-1 ml-4">
    <li>In the Admin Profile & Settings section, click the <strong>Add Admin</strong> button.</li>
    <li>Complete all required fields in the form.</li>
    <li>Note that the default role is set to <strong>Admin</strong>.</li>
    <li>Click <strong>Add Admin</strong> to finalize the addition.</li>
  </ol>
</section>

<!-- Section 6 -->
<section id="section6" class="bg-white rounded-lg shadow p-6 space-y-4 scroll-mt-20">
  <h2 class="text-xl font-semibold text-sky-700">6. Navigating the Right Panel and Audit Log Dashboard</h2>

  <h3 id="section6.1" class="font-medium">6.1 Navigating the Right Panel</h3>
  <ol class="list-decimal list-inside space-y-1 ml-4">
    <li>From the Admin Dashboard, locate the <strong>Right Panel</strong>, typically positioned beside the main content area.</li>
    <li>Review the following components:
      <ul class="list-disc list-inside ml-4 mt-1">
        <li><strong>Logged-In Admin Info</strong>: Displays the name and profile photo of the currently logged-in administrator.</li>
        <li><strong>Notifications Bell</strong>: Shows a count of unread system alerts or updates.</li>
        <li><strong>Calendar Widget</strong>: Provides a visual overview of scheduled events or deadlines.</li>
        <li><strong>Admin Metrics</strong>:
          <ul class="list-disc list-inside ml-4 mt-1">
            <li>Active Clients: Total number of clients currently engaged in a trip or onboarding process.</li>
            <li>Pending Documents: Number of uploaded documents awaiting admin review.</li>
          </ul>
        </li>
      </ul>
    </li>
  </ol>

  <h3 id="section6.2" class="font-medium mt-6">6.2 Accessing the Audit Log Dashboard</h3>
  <ol class="list-decimal list-inside space-y-1 ml-4">
    <li>In the Right Panel, click the <strong>View Full Audit Dashboard</strong> button.</li>
    <li>The system will redirect to the <strong>Audit Log Dashboard</strong>, a dedicated page for performance tracking and system transparency.</li>
  </ol>

  <h3 id="section6.3" class="font-medium mt-6">6.3 Audit Log Dashboard Overview</h3>
  <p>The Audit Log Dashboard includes the following components:</p>
  <ol class="list-decimal list-inside space-y-1 ml-4">
    <li><strong>Summary Metrics</strong>
      <ul class="list-disc list-inside ml-4 mt-1">
        <li>Active Clients: Total number of clients currently onboarded.</li>
        <li>Trips Completed: Number of clients who have completed their travel.</li>
        <li>Client Conversion Rate: Percentage of clients who reached trip completion.</li>
      </ul>
    </li>
    <li><strong>KPI Distribution Bar Graph</strong>
      <ul class="list-disc list-inside ml-4 mt-1">
        <li>Onboarding Velocity: Measures the average time taken for clients to upload their first document after being onboarded.</li>
        <li>Visualized as a bar graph showing average upload time per client.</li>
      </ul>
    </li>
    <li><strong>Audit Table Logs</strong>
      <ul class="list-disc list-inside ml-4 mt-1">
        <li>A searchable, filterable table of all system actions, including:
          <ul class="list-disc list-inside ml-4 mt-1">
            <li>Timestamp</li>
            <li>Actor (admin or system)</li>
            <li>Action Type (e.g., document approval, client creation)</li>
            <li>Affected Entity</li>
            <li>Source File</li>
          </ul>
        </li>
      </ul>
    </li>
    <li><strong>Document Status Pie Graph</strong>
      <ul class="list-disc list-inside ml-4 mt-1">
        <li>Displays the distribution of document statuses:
          <ul class="list-disc list-inside ml-4 mt-1">
            <li>Pending</li>
            <li>Approved</li>
            <li>Rejected</li>
          </ul>
        </li>
        <li>Visualized as a pie chart for quick reference.</li>
      </ul>
    </li>
    <li><strong>Approval Time Card Metric</strong>
      <ul class="list-disc list-inside ml-4 mt-1">
        <li>Shows the average time between document upload and admin approval.</li>
        <li>Helps identify bottlenecks or delays in the review process.</li>
      </ul>
    </li>
  </ol>
</section>

<!-- Section 7 -->
<section id="section7" class="bg-white rounded-lg shadow p-6 space-y-4 scroll-mt-20">
  <h2 class="text-xl font-semibold text-sky-700">7. Understanding Client Statuses</h2>

  <h3 id="section7.1" class="font-medium">7.1 Client Status Definitions</h3>
  <p>The system assigns statuses to clients to reflect their progress in the booking and travel process. Below are the available statuses and their meanings:</p>
  <ul class="list-disc list-inside ml-4 space-y-1">
    <li><strong>Awaiting Docs</strong>: The client has not yet uploaded any documents. This is the default status after adding a client to the system.</li>
    <li><strong>Under Review</strong>: The client has uploaded documents, but they are pending admin approval. This status indicates that action may be required soon.</li>
    <li><strong>Confirmed</strong>: The client’s identification documents (e.g., passport or ID card) have been uploaded and approved, indicating the client is ready to travel.</li>
    <li><strong>Resubmit Files</strong>: One or more critical documents, such as IDs or passports, have been rejected. The client must re-upload corrected files.</li>
    <li><strong>Trip Ongoing</strong>: The client is currently traveling. This status is set automatically when the current date falls within the client’s scheduled trip dates.</li>
    <li><strong>Trip Completed</strong>: The client’s trip has ended. Upon reaching this status, the system checks if the client has completed the post-trip survey and assigns it if necessary.</li>
    <li><strong>No Assigned Package</strong>: The client has no travel package linked to their profile, indicating they need further onboarding.</li>
    <li><strong>Cancelled</strong>: The trip or booking has been manually canceled. Admins can set this status if the client is no longer proceeding with their travel plans.</li>
  </ul>

  <h3 id="section7.2" class="font-medium mt-6">7.2 Automatic Status Updates</h3>
  <p>The system automatically updates client statuses based on specific conditions, using a helper function that evaluates uploaded documents, assigned packages, and trip dates. The process is as follows:</p>
  <ul class="list-disc list-inside ml-4 space-y-1">
    <li>If no documents are uploaded, the status is set to <strong>Awaiting Docs</strong>.</li>
    <li>If all uploaded documents are pending approval, the status changes to <strong>Under Review</strong>.</li>
    <li>If identification documents (e.g., ID or passport) are approved, the status becomes <strong>Confirmed</strong>.
      <ul class="list-disc list-inside ml-6 mt-1">
        <li><strong>Visa Requirement Check</strong>: If the assigned tour package requires a visa, the system will additionally verify that both a <strong>Visa</strong> document and a <strong>Valid ID</strong> document have been uploaded and approved before setting the status to Confirmed.</li>
      </ul>
    </li>
    <li>If identification documents are rejected, the status switches to <strong>Resubmit Files</strong>.</li>
    <li>If no travel package is assigned, the status is set to <strong>No Assigned Package</strong>.</li>
    <li>If the current date falls within the client’s travel dates, the status is updated to <strong>Trip Ongoing</strong>.</li>
    <li>If the trip has ended, the status changes to <strong>Trip Completed</strong>, and the system checks whether to assign the post-trip survey.</li>
    <li>Each status change is logged in the audit trail, recording the previous status, new status, and timestamp of the change.</li>
  </ul>
</section>

<footer class="text-center py-8 mt-16 border-t border-gray-200">
  <p class="text-sm text-gray-600">JVB Travel System — Admin User Manual</p>
  <p class="text-xs text-gray-400 mt-1">Last updated: January 2026 | Version 2.0</p>
</footer>

</div>
      </main>
</body>
</html>