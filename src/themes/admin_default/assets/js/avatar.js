import { createAvatar } from '@dicebear/core';
import * as identicon from '@dicebear/identicon';

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
  const seed = hashEmail(email);

  if (!seed) {
    return null;
  }

  const avatar = createAvatar(identicon, {
    seed: seed,
    size: size,
    backgroundColor: ['transparent']
  });

  return avatar.toString();
}

export function initAvatars() {
  document.querySelectorAll('.db-avatar').forEach(container => {
    const email = container.dataset.email;
    const size = parseInt(container.dataset.size, 10) || 40;

    if (email) {
      const svg = generateAvatar(email, size);

      if (svg) {
        const encodedSvg = encodeURIComponent(svg)
          .replace(/'/g, '%27')
          .replace(/"/g, '%22');

        container.style.backgroundImage = `url("data:image/svg+xml,${encodedSvg}")`;
      }
    }
  });
}

export default { generateAvatar, initAvatars };
