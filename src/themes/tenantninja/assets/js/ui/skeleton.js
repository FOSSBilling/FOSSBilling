/**
 * Skeleton loading states for the TenantNinja client theme.
 *
 * FOSSBilling renders each page server-side in one pass, so there is no
 * in-page data-fetch to shim. The perceived-speed win instead lives in the
 * navigation gap: the instant a client clicks an internal link to a heavy
 * page (dashboard, services, invoices), we swap the current main region for a
 * skeleton that mirrors the destination 1:1. The browser then paints that
 * skeleton while the server round-trips, so the next page never arrives to a
 * blank screen. Every skeleton reuses the real card chrome and matches row /
 * column sizes, so there is zero layout shift when the real page loads.
 *
 * Ported from redesign/skeletons.jsx. Pure DOM + the .tn-sk utility class.
 */

// Skeleton bar / block. `round` → pill radius.
const sk = ({ w = '100%', h = 12, r = '', round = false, style = '' } = {}) =>
  `<div class="tn-sk${round ? ' tn-sk--round' : ''}" style="width:${typeof w === 'number' ? w + 'px' : w};height:${h}px;${r ? `border-radius:${r};` : ''}${style}"></div>`;

const skRow = (children, { pad = '13px 18px', last = false } = {}) =>
  `<div class="tn-sk-row${last ? ' tn-sk-row--last' : ''}" style="padding:${pad}">${children}</div>`;

const skServiceRow = (last) => skRow(
  sk({ w: 34, h: 34, r: 'var(--r-sm)' }) +
  `<div style="display:flex;flex-direction:column;gap:7px;flex:1">${sk({ w: '42%', h: 11 })}${sk({ w: '26%', h: 9 })}</div>` +
  sk({ w: 72, h: 18, round: true }) +
  `<div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;width:120px">${sk({ w: 64, h: 9 })}${sk({ w: 48, h: 8 })}</div>`,
  { last },
);

const skStatTile = () =>
  `<div class="tn-stat">${sk({ w: '52%', h: 9 })}${sk({ w: '38%', h: 22 })}${sk({ w: '66%', h: 9 })}</div>`;

const skCard = (title, inner) =>
  `<div class="tn-card tn-card--compact mb-4">${title ? `<div class="tn-card__header">${sk({ w: 130, h: 12 })}</div>` : ''}<div>${inner}</div></div>`;

function dashboardSkeleton() {
  const tiles = Array.from({ length: 4 }, skStatTile).join('');
  const attention = [0, 1].map((i) => skRow(
    sk({ w: 32, h: 32, round: true }) +
    `<div style="display:flex;flex-direction:column;gap:7px;flex:1">${sk({ w: '58%', h: 11 })}${sk({ w: '40%', h: 9 })}</div>` +
    sk({ w: 84, h: 30, r: 'var(--r-sm)' }),
    { last: i === 1 },
  )).join('');
  const services = [0, 1, 2, 3, 4].map((i) => skServiceRow(i === 4)).join('');
  const invoices = [0, 1, 2].map((i) => skRow(
    `<div style="display:flex;flex-direction:column;gap:6px;flex:1">${sk({ w: '52%', h: 10 })}${sk({ w: '34%', h: 8 })}</div>` +
    sk({ w: 58, h: 10 }) + sk({ w: 56, h: 18, round: true }),
    { pad: '12px 18px', last: i === 2 },
  )).join('');
  return `
    <div class="tn-dashboard-header">
      <div style="display:flex;flex-direction:column;gap:10px">${sk({ w: 280, h: 24 })}${sk({ w: 380, h: 12 })}</div>
    </div>
    <div class="tn-kpi-grid">${tiles}</div>
    <div class="tn-dashboard-body">
      <div class="tn-dashboard-body__main">
        ${skCard(true, attention)}
        ${skCard(true, services)}
      </div>
      <div class="tn-dashboard-body__rail">
        ${skCard(true, invoices)}
      </div>
    </div>`;
}

function servicesSkeleton() {
  const chips = [64, 86, 78, 70].map((w) => sk({ w, h: 32, round: true })).join('');
  const rows = Array.from({ length: 8 }, (_, i) => skServiceRow(i === 7)).join('');
  return `
    <div class="tn-dashboard-header">
      <div style="display:flex;flex-direction:column;gap:10px">${sk({ w: 180, h: 24 })}${sk({ w: 320, h: 12 })}</div>
    </div>
    <div style="display:flex;gap:8px;margin-bottom:18px">${chips}</div>
    ${skCard(false, rows)}`;
}

function invoicesSkeleton() {
  const tiles = Array.from({ length: 3 }, skStatTile).join('');
  const rows = Array.from({ length: 7 }, (_, i) => skRow(
    sk({ w: 86, h: 10 }) +
    `<div style="flex:1">${sk({ w: '36%', h: 10 })}</div>` +
    sk({ w: 70, h: 10 }) + sk({ w: 58, h: 18, round: true }) + sk({ w: 72, h: 30, r: 'var(--r-sm)' }),
    { last: i === 6 },
  )).join('');
  return `
    <div class="tn-dashboard-header">
      <div style="display:flex;flex-direction:column;gap:10px">${sk({ w: 160, h: 24 })}${sk({ w: 300, h: 12 })}</div>
    </div>
    <div class="tn-kpi-grid" style="grid-template-columns:repeat(3,1fr)">${tiles}</div>
    ${skCard(false, rows)}`;
}

// Map a destination pathname to its skeleton builder. Order matters: the more
// specific service/invoice routes must win over the dashboard root match.
function skeletonFor(pathname) {
  const p = pathname.replace(/\/+$/, '') || '/';
  if (/\/order\/service(\/|$)/.test(p)) return servicesSkeleton;
  if (/\/invoice(\/|$)/.test(p)) return invoicesSkeleton;
  if (p === '/' || /\/(index|dashboard)$/.test(p)) return dashboardSkeleton;
  return null;
}

export default function initSkeleton() {
  const main = document.querySelector('section[role="main"] .content-block');
  if (!main) return;

  document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (!link) return;
    // Only intercept plain, same-tab, same-origin navigations.
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    if (link.target && link.target !== '_self') return;
    if (link.hasAttribute('data-fb-api') || link.getAttribute('href').startsWith('#')) return;
    // Theme toggler links (?theme=light|dark) target the current pathname and
    // are prevented in theme.js's own bubble-phase handler - but that runs
    // *after* this capture-phase listener, so without this guard the skeleton
    // swap already fired and is never replaced by a real navigation, leaving
    // the page stuck on a fake skeleton forever.
    if (link.classList.contains('js-theme-toggler')) return;

    let url;
    try { url = new URL(link.href, location.href); } catch { return; }
    if (url.origin !== location.origin) return;
    if (url.pathname === location.pathname && url.hash) return;

    const builder = skeletonFor(url.pathname);
    if (!builder) return;

    // Swap in the skeleton; the browser paints it during the load that follows.
    main.innerHTML = builder();
    main.scrollIntoView({ block: 'start' });
  }, true);
}
