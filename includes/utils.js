function handleImageError(image) {
  if (!image.dataset.fallbackApplied) {
    const fallback = image.src.includes('admin_photo')
      ? '../uploads/admin_photo/default_admin_profile.png'
      : '../images/default_client_profile.png';
    image.src = fallback;
    image.dataset.fallbackApplied = 'true';
  }
}

function getTimeAgo(dateString) {
  const now = new Date();
  const then = new Date(dateString);
  const diffMs = now - then;
  const diffSec = Math.floor(diffMs / 1000);
  const diffMin = Math.floor(diffSec / 60);
  const diffHr  = Math.floor(diffMin / 60);
  const diffDay = Math.floor(diffHr / 24);

  if (diffSec < 60) return 'Just now';
  if (diffMin < 60) return `${diffMin} min${diffMin > 1 ? 's' : ''} ago`;
  if (diffHr < 24)  return `${diffHr} hour${diffHr > 1 ? 's' : ''} ago`;
  if (diffDay === 1) return 'Yesterday';
  if (diffDay < 7)  return `${diffDay} day${diffDay > 1 ? 's' : ''} ago`;

  return then.toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
}