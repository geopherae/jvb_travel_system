<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
use function Auth\guard;
guard('client');

require_once __DIR__ . '/../actions/db.php';
require_once __DIR__ . '/../includes/status-helpers.php';

// 🧑 Get client ID from session
$client_id = $_SESSION['client_id'] ?? null;
if (!$client_id) {
  die('Invalid session.');
}

$client_id = $_SESSION['client']['id'] ?? null;


// 🚫 Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Client System Manual</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="font-poppins text-gray-800 overflow-hidden">


  <!-- Sidebar -->
  <?php include '../components/sidebar.php'; ?>

  <!-- Right Sidebar Panel -->
  <?php
    $isAdmin = false;
    include '../components/right-panel.php';
  ?>

  <main class="ml-0 md:ml-64 lg:mr-80 h-screen overflow-y-auto p-6 space-y-8 relative z-0 flex justify-center w-full">

  <div class="max-w-4xl py-10 px-6 space-y-10">
    <header class="text-center">
      <h1 class="text-3xl font-bold text-sky-800">Client System Manual</h1>
      <p class="text-gray-600 mt-2">Step-by-step guide for using the JVB Travel System</p>
    </header>

<!-- Navigation Table -->
<nav class="bg-white rounded-lg shadow p-6">
  <h2 class="text-lg font-semibold text-sky-700 mb-4">📌 Quick Navigation</h2>
  <ul class="list-disc list-inside space-y-2 text-sky-700">
    <li>
      <a href="#section1" class="font-semibold hover:underline">1. Logging In and Viewing the Dashboard</a>
      <ul class="list-disc list-inside ml-6 space-y-1 text-sky-600">
        <li><a href="#section1" class="hover:underline">1.1 Logging In with Access Code</a></li>
        <li><a href="#section1" class="hover:underline">1.2 Exploring the Client Dashboard</a></li>
      </ul>
    </li>
    <li>
      <a href="#section2" class="font-semibold hover:underline">2. My Itinerary</a>
      <ul class="list-disc list-inside ml-6 space-y-1 text-sky-600">
        <li><a href="#section2" class="hover:underline">2.1 Itinerary Tab</a></li>
        <li><a href="#section2" class="hover:underline">2.2 Trip Photos Tab</a></li>
      </ul>
    </li>
    <li><a href="#section3" class="font-semibold hover:underline">3. Checklist Progress</a></li>
    <li><a href="#section4" class="font-semibold hover:underline">4. Uploading Documents</a></li>
    <li><a href="#section5" class="font-semibold hover:underline">5. Trip Survey</a></li>
    <li><a href="#section6" class="font-semibold hover:underline">6. Understanding Your Status</a></li>
  </ul>
</nav>

    <!-- Section 1 -->
    <section id="section1" class="bg-white rounded-lg shadow p-6 space-y-4 scroll-mt-20">
      <h2 class="text-xl font-semibold text-sky-700">1. Logging In and Viewing the Dashboard</h2>
      <h3 class="font-medium">1.1 Logging In with Access Code</h3>
      <ol class="list-decimal list-inside space-y-1 ml-4">
        <li>Access the Client Login page using the provided system link.</li>
        <li>Enter your Access Code in the input field (format: JVBT-0000, e.g., JVBT-1023).</li>
        <li>Click the <strong>Login</strong> button.</li>
        <li>Upon successful authentication, you will be redirected to the Client Dashboard.</li>
      </ol>
      <p class="text-sm text-gray-500 italic">Note: If your access code is invalid or expired, contact your assigned travel agent for assistance.</p>

      <h3 class="font-medium mt-6">1.2 Exploring the Client Dashboard</h3>
      <ul class="list-disc list-inside ml-4 space-y-1">
        <li><strong>Sidebar Navigation</strong>: Access Dashboard, My Itinerary, Checklist Progress, Upload Documents, and Trip Survey.</li>
        <li><strong>Welcome Banner</strong>: Shows your destination, duration, booking number, and current status.</li>
        <li><strong>Documents Table</strong>: Displays uploaded files with their status (Pending, Approved, Rejected).</li>
        <li><strong>Upload Documents Button</strong>: Allows you to submit required travel documents.</li>
        <li><strong>Right Panel</strong>: Displays your name, profile photo, notifications bell, and calendar with highlighted travel dates.</li>
      </ul>
    </section>

    <!-- Section 2 -->
    <section id="section2" class="bg-white rounded-lg shadow p-6 space-y-4 scroll-mt-20">
      <h2 class="text-xl font-semibold text-sky-700">2. My Itinerary</h2>
      <h3 class="font-medium">2.1 Itinerary Tab</h3>
      <ol class="list-decimal list-inside space-y-1 ml-4">
        <li>Click the <strong>My Itinerary</strong> tab in the sidebar.</li>
        <li>View a scrollable list of itinerary cards, each showing:
          <ul class="list-disc list-inside ml-4 mt-2">
            <li>Day Number (e.g., Day 1)</li>
            <li>Day Title (e.g., Arrival & Hotel Check-in)</li>
            <li>Activity List with optional time slots and descriptions</li>
          </ul>
        </li>
        <li>Scroll through the cards to view your full itinerary.</li>
        <li>If no itinerary is available, a message will appear: “No itinerary data available for this package.”</li>
      </ol>

      <h3 class="font-medium mt-6">2.2 Trip Photos Tab</h3>
      <ol class="list-decimal list-inside space-y-1 ml-4">
        <li>Click the <strong>Trip Photos</strong> tab under My Itinerary.</li>
        <li>View Day Cards for each itinerary day with up to 6 photo slots.</li>
        <li>Click an empty slot to upload a photo (JPG, PNG, max 2MB).</li>
        <li>Uploaded photos are marked as Pending, Approved, or Rejected after admin review.</li>
      </ol>
    </section>

    <!-- Section 3 -->
    <section id="section3" class="bg-white rounded-lg shadow p-6 space-y-4 scroll-mt-20">
      <h2 class="text-xl font-semibold text-sky-700">3. Checklist Progress</h2>
      <ol class="list-decimal list-inside space-y-1 ml-4">
        <li>Click the <strong>Checklist Progress</strong> tab in the sidebar.</li>
        <li>View tasks with completion status:
          <ul class="list-disc list-inside ml-4 mt-2">
            <li>First Login Survey</li>
            <li>ID Uploaded</li>
            <li>ID Approved</li>
            <li>Itinerary Confirmed</li>
            <li>Trip Photos Uploaded</li>
            <li>Trip Completion Survey</li>
            <li>Facebook Page Visited</li>
          </ul>
        </li>
        <li>Completed tasks show timestamps; incomplete ones are grayed out.</li>
      </ol>
    </section>

    <!-- Section 4 -->
    <section id="section4" class="bg-white rounded-lg shadow p-6 space-y-4 scroll-mt-20">
      <h2 class="text-xl font-semibold text-sky-700">4. Uploading Documents</h2>
      <ol class="list-decimal list-inside space-y-1 ml-4">
        <li>Click the <strong>Upload Documents</strong> tab in the sidebar.</li>
        <li>Select the document type (e.g., Passport, ID Card).</li>
        <li>Click the upload area to select a file (JPG, JPEG, PNG, max 3MB).</li>
        <li>Click <strong>Submit</strong> to upload.</li>
        <li>Your document will appear in the Documents Table with its status.</li>
      </ol>
      <p class="text-sm text-gray-500 italic">Tip: Rejected documents will require re-upload. Admins may leave notes if feedback is enabled.</p>
    </section>

    <!-- Section 5 -->
    <section id="section5" class="bg-white rounded-lg shadow p-6 space-y-4 scroll-mt-20">
      <h2 class="text-xl font-semibold text-sky-700">5. Trip Survey</h2>
      <ol class="list-decimal list-inside space-y-1 ml-4">
        <li>Click the <strong>Trip Survey</strong> tab (visible after trip completion).</li>
        <li>Answer questions about your travel experience.</li>
        <li>Click <strong>Submit</strong> to send your feedback.</li>
      </ol>
    </section>

    <!-- Section 6 -->
    <section id="section6" class="bg-white rounded-lg shadow p-6 space-y-4 scroll-mt-20">
      <h2 class="text-xl font-semibold text-sky-700">6. Understanding Your Status</h2>
      <p>Your current status is shown on the dashboard and reflects your progress:</p>
      <ul class="list-disc list-inside ml-4 space-y-1">
        <li><strong>Awaiting Docs</strong>: No documents uploaded yet.</li>
        <li><strong>Under Review</strong>: Documents uploaded, pending approval.</li>
        <li><strong>Confirmed</strong>: ID or passport approved.</li>
<li><strong>Resubmit Files</strong>: One or more documents were rejected and need to be uploaded again.</li>
<li><strong>Trip Ongoing</strong>: You are currently traveling based on your scheduled trip dates.</li>
<li><strong>Trip Completed</strong>: Your trip has ended. You may now be asked to complete a feedback survey.</li>
<li><strong>No Assigned Package</strong>: You haven’t been linked to any travel package yet. Contact your travel agent to begin onboarding.</li>
<li><strong>Cancelled</strong>: Your trip has been cancelled. This status is manually set by your travel agent.</li>

</ul>
<p class="text-sm text-gray-500 italic mt-2">Statuses are updated automatically based on your activity. You don’t need to change them manually.</p>
</section>

<footer class="text-center text-xs text-gray-400 mt-12">
  JVB Travel System Manual — Client Side | Last updated: September 2025
</footer>
</div>
</main>
</body>
</html>