import { createAvatar } from '@dicebear/core';
import * as identicon from '@dicebear/identicon';

const avatarCache = new Map();

function hashEmail(email) {
  if (!email || typeof email !== 'string') {
    return null;
  }

  let hash = 5381;
  const normalizedEmail = email.toLowerCase().trim();

  for (let i = 0; i < normalizedEmail.length; i++) {
    hash = ((hash << 5) + hash) + normalizedEmail.charCodeAt(i);
  }

  return (hash >>> 0).toString(16);
}

export function generateAvatar(email, size = 40) {
  const cacheKey = `${email}:${size}`;
  if (avatarCache.has(cacheKey)) {
    return avatarCache.get(cacheKey);
  }

  const seed = hashEmail(email);
  if (!seed) {
    return null;
  }

  const avatar = createAvatar(identicon, {
    seed: seed,
    size: size,
    backgroundColor: ['transparent']
  });

  const result = avatar.toString();
  avatarCache.set(cacheKey, result);
  return result;
}

export function initAvatars() {
  document.querySelectorAll('.db-avatar').forEach(container => {
    const email = container.dataset.email;
    const size = parseInt(container.dataset.size, 10) || 40;

    if (email) {
      const svg = generateAvatar(email, size);

      if (svg) {
        const encodedSvg = encodeURIComponent(svg);

        container.style.backgroundImage = `url("data:image/svg+xml,${encodedSvg}")`;
      }
    }
  });
}

export default { generateAvatar, initAvatars };
