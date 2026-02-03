#!/usr/bin/env python3
import sys

# Update submit_visa_document.php
file_path = r'c:\xampp\htdocs\jvb_travel_system\actions\submit_visa_document.php'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

old_str = """  http_response_code(200);
  echo json_encode([
    'success' => true,
    'message' => 'Document uploaded successfully by: ' . $uploaderName
  ]);"""

new_str = """  // Set session status for toast notification
  $_SESSION['modal_status'] = 'upload_success';
  
  http_response_code(200);
  echo json_encode([
    'success' => true,
    'message' => 'Document uploaded successfully by: ' . $uploaderName
  ]);"""

if old_str in content:
    content = content.replace(old_str, new_str)
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)
    print("✓ Updated submit_visa_document.php")
else:
    print("✗ Could not find matching string in submit_visa_document.php")

# Update visa-file-viewer-modal.php
file_path2 = r'c:\xampp\htdocs\jvb_travel_system\components\visa-file-viewer-modal.php'
with open(file_path2, 'r', encoding='utf-8') as f:
    content2 = f.read()

old_modal = """        <a :href="viewer.path"
           target="_blank"
           class="mt-3 inline-block text-sm text-sky-600 hover:text-sky-700 hover:underline touch-manipulation">
          Open in Full Screen
        </a>
        
        <button @click="deleteDocument()"
                class="mt-2 inline-block text-sm text-red-600 hover:text-red-700 hover:underline touch-manipulation">
          Delete File
        </button>"""

new_modal = """        <!-- Links stacked vertically -->
        <div class="space-y-3 mt-4">
          <a :href="viewer.path"
             target="_blank"
             class="block text-sm text-sky-600 hover:text-sky-700 hover:underline touch-manipulation">
            Open in Full Screen
          </a>
          
          <button @click="deleteDocument()"
                  class="block text-sm text-red-600 hover:text-red-700 hover:underline touch-manipulation">
            Delete File
          </button>
        </div>"""

if old_modal in content2:
    content2 = content2.replace(old_modal, new_modal)
    with open(file_path2, 'w', encoding='utf-8') as f:
        f.write(content2)
    print("✓ Updated visa-file-viewer-modal.php")
else:
    print("✗ Could not find matching string in visa-file-viewer-modal.php")
