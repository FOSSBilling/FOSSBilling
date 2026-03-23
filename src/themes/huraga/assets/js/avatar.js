import { createAvatar } from '@dicebear/core';
import * as identicon from '@dicebear/identicon';

const avatarCache = new Map();

export function generateAvatar(seed, size = 40) {
  const cacheKey = `${seed}:${size}`;
  if (avatarCache.has(cacheKey)) {
    return avatarCache.get(cacheKey);
  }

  const avatar = createAvatar(identicon, {
    seed: seed,
    size: size,
    backgroundColor: ['transparent']
  });

  const result = avatar.toDataUri();
  avatarCache.set(cacheKey, result);
  return result;
}

export function initAvatars() {
  document.querySelectorAll('.db-avatar').forEach(container => {
    const seed = container.dataset.avatarSeed;
    const size = parseInt(container.dataset.avatarSize, 10) || 40;

    if (seed) {
      const svg = generateAvatar(seed, size);

      if (svg) {
        container.style.backgroundImage = `url("${svg}")`;
        container.style.backgroundSize = '100% 100%';
        container.style.backgroundPosition = 'center';
        container.style.backgroundRepeat = 'no-repeat';
      }
    }
  });
}

export default { generateAvatar };
