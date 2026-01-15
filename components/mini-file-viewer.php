<div x-show="showFileViewer" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white w-full max-w-5xl h-[80vh] rounded-md shadow-lg flex">
        <!-- Left Column: File Details -->
        <div class="w-7/10 bg-gray-100 p-6 overflow-auto">
            <h2 class="text-lg font-semibold text-gray-800">📄 Document Viewer</h2>
            <p class="text-sm text-gray-600 mt-2">File Name: <span x-text="fileViewerName"></span></p>
            <p class="text-sm text-gray-600 mt-2">File Path: <span x-text="fileViewerUrl"></span></p>

            <!-- Open File Button -->
            <a :href="fileViewerUrl" target="_blank" class="mt-4 inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                Open File
            </a>
        </div>

        <!-- Right Column: Empty Space (Future Use) -->
        <div class="w-3/10 bg-white p-6">
            <h3 class="text-lg font-semibold text-gray-800">Details</h3>
            <p class="text-sm text-gray-600">This space can be used for comments, metadata, or actions in the future.</p>
        </div>

        <!-- Close Button -->
        <button @click="closeFileViewer()" class="absolute top-4 right-4 bg-red-600 text-white px-4 py-2 rounded">
            Close
        </button>
    </div>
</div>