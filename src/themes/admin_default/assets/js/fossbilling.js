import backToTop from "./ui/backToTop";
import { renderTimeSeriesSparkline } from "./ui/charts";

globalThis.FOSSBilling = Object.assign(globalThis.FOSSBilling || {}, {
  message: (message, type = "info") => {
    const titles = {
      error: "Error",
      warning: "Warning",
      success: "Success",
    };
    const title = titles[type] || "Info";
    let color;
    switch (type) {
      case "error":
        color = "danger";
        break;
      case "warning":
        color = "warning";
        break;
      case "success":
        color = "success";
        break;
      default:
        color = "primary";
    }

    const container = document.querySelector(".toast-container");

    const element = document.createElement("div");
    container.appendChild(element);
    element.classList.add("toast", "show");
    element.setAttribute("role", "alert");
    element.setAttribute("aria-live", "assertive");
    element.setAttribute("aria-atomic", "true");

    const headerDiv = document.createElement("div");
    headerDiv.className = "toast-header";

    const spanEl = document.createElement("span");
    spanEl.className = `p-2 border border-light bg-${color} rounded-circle me-2`;
    headerDiv.appendChild(spanEl);

    const strongEl = document.createElement("strong");
    strongEl.className = "me-auto";
    strongEl.textContent = title;
    headerDiv.appendChild(strongEl);

    const closeButton = document.createElement("button");
    closeButton.type = "button";
    closeButton.className = "btn-close";
    closeButton.setAttribute("data-bs-dismiss", "toast");
    closeButton.setAttribute("aria-label", "Close");
    headerDiv.appendChild(closeButton);

    element.appendChild(headerDiv);

    const bodyDiv = document.createElement("div");
    bodyDiv.className = "toast-body";
    bodyDiv.textContent = message;
    element.appendChild(bodyDiv);

    element.addEventListener("hidden.bs.toast", () => {
      container.removeChild(element);
    });

    const toast = new bootstrap.Toast(element);
    toast.show();
  },

  charts: {
    renderTimeSeriesSparkline,
  }
});

  document.addEventListener('DOMContentLoaded', function() {
    // Global error handler for unhandled Promise rejections (API-related only)
    window.addEventListener('unhandledrejection', function(event) {
      const error = event.reason;
      if (error && typeof error === 'object' && error.code) {
        event.preventDefault();
        const message = error.message || error.code || 'An unexpected error occurred';
        FOSSBilling.message(message, 'error');
      }
    });

    // Initialize backToTop
    FOSSBilling.backToTop = backToTop;
    FOSSBilling.backToTop();

    document.addEventListener("click", function(event) {
      const target = event.target;
      if (target.matches("div.msg span.close") || target.closest("div.msg span.close")) {
        event.preventDefault();
        const parent = target.parentElement;

        // Simple slide up effect
        const originalHeight = parent.offsetHeight;
        parent.style.overflow = "hidden";
        parent.style.transition = "height 70ms";
        parent.style.height = originalHeight + "px";

        setTimeout(() => {
          parent.style.height = "0";
          setTimeout(() => {
            parent.style.display = "none";
          }, 70);
        }, 10);

        return false;
      }
    });

   //===== Information boxes =====//
   document.querySelectorAll('.hideit').forEach(element => {
     element.addEventListener('click', function() {
       // Simple fade out effect
       let opacity = 1;
       const fadeEffect = setInterval(() => {
         if (opacity > 0) {
           opacity -= 0.1;
           this.style.opacity = opacity;
         } else {
           clearInterval(fadeEffect);
           this.style.display = 'none';
         }
       }, 40); // 40ms * 10 steps ~= 400ms duration
     });
   });

   //===== Tab deep-linking and persistence =====//
   const tabTriggers = document.querySelectorAll('[data-bs-toggle="tab"], [data-bs-toggle="pill"]');

   const getTabTargetSelector = (tabTrigger) => {
     const dataTarget = tabTrigger.getAttribute('data-bs-target');
     if (dataTarget && dataTarget.startsWith('#')) {
       return dataTarget;
     }

     const hrefTarget = tabTrigger.getAttribute('href');
     if (hrefTarget && hrefTarget.startsWith('#')) {
       return hrefTarget;
     }

     return null;
   };

   const findTabTrigger = (tabId) => {
     if (!tabId) {
       return null;
     }

     return document.querySelector(
       `[data-bs-toggle="tab"][data-bs-target="#${tabId}"], ` +
       `[data-bs-toggle="pill"][data-bs-target="#${tabId}"], ` +
       `[data-bs-toggle="tab"][href="#${tabId}"], ` +
       `[data-bs-toggle="pill"][href="#${tabId}"]`
     );
   };

   const showTabById = (tabId) => {
     const tabTrigger = findTabTrigger(tabId);
     if (!tabTrigger) {
       return false;
     }

     const tab = bootstrap.Tab.getOrCreateInstance(tabTrigger);
     tab.show();

     return true;
   };

   const syncTabUrl = (tabId) => {
     if (!tabId) {
       return;
     }

     const url = new URL(window.location.href);
     url.hash = tabId;
     url.searchParams.delete('tab');
     window.history.replaceState({}, '', url);
   };

   const hashTabId = window.location.hash.startsWith('#') ? window.location.hash.slice(1) : '';
   showTabById(hashTabId);

   tabTriggers.forEach((tabTrigger) => {
     tabTrigger.addEventListener('shown.bs.tab', function() {
       const targetSelector = getTabTargetSelector(this);
       if (targetSelector) {
         syncTabUrl(targetSelector.slice(1));
       }
     });
   });

   window.addEventListener('hashchange', () => {
     const nextTabId = window.location.hash.startsWith('#') ? window.location.hash.slice(1) : '';
     showTabById(nextTabId);
   });

   //===== Search filter toggle state =====//
   const syncSearchFilterToggleState = (toggle, panel) => {
     const targetSelector = toggle.getAttribute('data-bs-target');
     if (!targetSelector || !targetSelector.startsWith('#')) {
       return;
     }

     const isOpen = panel?.classList.contains('show') || toggle.getAttribute('aria-expanded') === 'true';
     toggle.classList.toggle('text-primary', isOpen);
     toggle.classList.toggle('text-secondary', !isOpen);

     const summary = document.querySelector('.filter-panel-summary');
     if (summary) {
       summary.classList.toggle('d-none', isOpen);
     }
   };

   document.querySelectorAll('.search-filter-toggle[data-bs-target]').forEach((toggle) => {
     const targetSelector = toggle.getAttribute('data-bs-target');
     if (!targetSelector || !targetSelector.startsWith('#')) {
       return;
     }

     const panel = document.querySelector(targetSelector);
     if (!panel) {
       return;
     }

     syncSearchFilterToggleState(toggle, panel);

     panel.addEventListener('shown.bs.collapse', () => syncSearchFilterToggleState(toggle, panel));
     panel.addEventListener('hidden.bs.collapse', () => syncSearchFilterToggleState(toggle, panel));
   });
 });
