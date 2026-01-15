<!-- Message Form Container -->
<form id="messageForm" class="space-y-3 mt-4" autocomplete="off">
  <!-- Message Field -->
  <div>
    <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Message</label>
    <textarea
      id="message"
      name="content"
      rows="3"
      required
      maxlength="1000"
      placeholder="Type your message..."
      class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-sky-500 focus:border-sky-500 resize-none"
    ></textarea>
  </div>

  <!-- Submit Button -->
  <div class="flex justify-end">
    <button type="submit"
            class="px-4 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-md shadow-sm text-sm font-medium transition">
      Send
    </button>
  </div>
</form>

<!-- Message Display Container -->
<div id="chatBox" class="mt-6 space-y-2 overflow-y-auto max-h-96 rounded border border-gray-200 p-3 bg-white text-sm shadow-sm"></div>

<script>
  const form = document.getElementById('messageForm');
  const chatBox = document.getElementById('chatBox');
  const textarea = form.querySelector('textarea');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const content = textarea.value.trim();
    if (!content) return;

    const formData = new FormData(form);
    formData.append('client_id', <?= $client['id'] ?>);

    try {
      const res = await fetch('../actions/send_message.php', {
        method: 'POST',
        body: formData
      });

      if (res.ok) {
        form.reset();
        await loadMessages(); // refresh view
      } else {
        alert("Message failed to send.");
      }
    } catch (err) {
      console.error(err);
      alert("A network error occurred.");
    }
  });

  async function loadMessages() {
    const res = await fetch('../actions/fetch_messages.php?client_id=<?= $client['id'] ?>');
    chatBox.innerHTML = await res.text();
    chatBox.scrollTo({ top: chatBox.scrollHeight, behavior: 'smooth' });
  }

  // Load and refresh messages
  loadMessages();
  setInterval(loadMessages, 10000);
</script>