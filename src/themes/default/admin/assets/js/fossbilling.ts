// @ts-nocheck -- Runtime DOM/widget integration; converted to TS without changing behavior.
import { showToast } from '../../../../../../frontend/ui/tabler-toast.ts';
import backToTop from "./ui/backToTop.ts";
import initTabs from "./ui/tabs.ts";

function renderTimeSeriesSparkline(...args) {
  return import("./ui/charts.ts").then(({ renderTimeSeriesSparkline: renderChart }) => renderChart(...args));
}

globalThis.FOSSBilling = Object.assign(globalThis.FOSSBilling || {}, {
  message: (message, type = "info") => {
    const titles = {
      error: "Error",
      warning: "Warning",
      success: "Success",
    };
    showToast(message, type, {
      title: titles[type] || 'Info',
      closeLabel: 'Close',
      assertive: true,
    });
  },

  charts: {
    renderTimeSeriesSparkline,
  }
});

  document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
      return;
    }

    const trigger = event.target.closest('a[data-transaction-error]');
    if (!trigger) {
      return;
    }

    event.preventDefault();
    FOSSBilling.message(trigger.dataset.transactionError, trigger.dataset.transactionErrorCode);
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

   // Shared Tabler deep links; admin list namespaces stay in the theme adapter.
   initTabs();

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
