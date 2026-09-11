// @ts-nocheck -- Runtime DOM/widget integration; converted to TS without changing behavior.
import backToTop from "./ui/backToTop.ts";
import { showToastMessage } from "../../../../../../frontend/core/toast.mts";
import { errorMessage } from "../../../../../../frontend/core/error-message.mts";
import { initTabDeepLinking } from "../../../../../../frontend/core/tabs.mts";

function renderTimeSeriesSparkline(...args) {
  return import("./ui/charts.ts").then(({ renderTimeSeriesSparkline: renderChart }) => renderChart(...args));
}

globalThis.FOSSBilling = Object.assign(globalThis.FOSSBilling || {}, {
  message: (message, type = "info") => showToastMessage(message, type, tabler.Toast),

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
        FOSSBilling.message(errorMessage(error), 'error');
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
    initTabDeepLinking(tabler.Tab, { enableJumpLinks: true, clearTabParam: true });

   //===== Discord community popover (shown once) =====//
   const discordBtn = document.getElementById('discord-community-btn');
   const isDiscordBtnVisible = () => {
     if (!discordBtn) {
       return false;
     }

     const style = window.getComputedStyle(discordBtn);

     return style.visibility !== 'hidden' && style.display !== 'none' && discordBtn.getClientRects().length > 0;
   };

   if (discordBtn && !localStorage.getItem('fb-discord-popover-seen') && isDiscordBtnVisible()) {
     const popover = tabler.Popover.getOrCreateInstance(discordBtn);
     localStorage.setItem('fb-discord-popover-seen', '1');
     popover.show();
     document.addEventListener('click', (e) => {
       if (!discordBtn.contains(e.target)) {
         popover.hide();
       }
     }, { once: true });
   }

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
