import './scss/fossbilling-modern.scss';

import $ from 'jquery';
import * as bootstrap from 'bootstrap';
import '../../admin_default/assets/js/tomselect';
import '../../admin_default/assets/js/fossbilling';

globalThis.$ = globalThis.jQuery = $;
globalThis.bootstrap = bootstrap;

// PWA Service Worker Registration
if ('serviceWorker' in navigator && window.location.protocol === 'https:') {
  window.addEventListener('load', async () => {
    try {
      const registration = await navigator.serviceWorker.register('/themes/fossbilling-modern/build/sw.js');
      console.log('Service Worker registered successfully:', registration.scope);
      
      // Handle service worker updates
      registration.addEventListener('updatefound', () => {
        const newWorker = registration.installing;
        newWorker.addEventListener('statechange', () => {
          if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
            // Show update notification
            showUpdateNotification();
          }
        });
      });
    } catch (error) {
      console.log('Service Worker registration failed:', error);
    }
  });
}

// PWA Install Prompt
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt = e;
  showInstallButton();
});

function showInstallButton() {
  const installButton = document.createElement('button');
  installButton.className = 'btn btn-outline-primary btn-sm position-fixed';
  installButton.style.bottom = '20px';
  installButton.style.right = '20px';
  installButton.style.zIndex = '1050';
  
  // Secure icon creation without innerHTML
  const icon = document.createElement('span');
  icon.textContent = '📱';
  const text = document.createTextNode(' Install App');
  installButton.appendChild(icon);
  installButton.appendChild(text);
  
  installButton.onclick = installPWA;
  document.body.appendChild(installButton);
}

async function installPWA() {
  if (deferredPrompt) {
    deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;
    console.log(`User response to the install prompt: ${outcome}`);
    deferredPrompt = null;
    document.querySelector('.btn[onclick="installPWA()"]')?.remove();
  }
}

function showUpdateNotification() {
  const notification = document.createElement('div');
  notification.className = 'alert alert-info alert-dismissible position-fixed';
  notification.style.top = '20px';
  notification.style.right = '20px';
  notification.style.zIndex = '1060';
  
  // Create content securely without innerHTML
  const strong = document.createElement('strong');
  strong.textContent = '🔄 Update Available!';
  notification.appendChild(strong);
  
  const text = document.createTextNode(' A new version is ready. ');
  notification.appendChild(text);
  
  const updateBtn = document.createElement('button');
  updateBtn.type = 'button';
  updateBtn.className = 'btn btn-sm btn-outline-info ms-2';
  updateBtn.textContent = '✨ Update Now';
  updateBtn.onclick = () => location.reload();
  notification.appendChild(updateBtn);
  
  const closeBtn = document.createElement('button');
  closeBtn.type = 'button';
  closeBtn.className = 'btn-close';
  closeBtn.setAttribute('data-bs-dismiss', 'alert');
  notification.appendChild(closeBtn);
  
  document.body.appendChild(notification);
}

// Modern theme enhancements
class FOSSBillingModern {
  constructor() {
    this.theme = this.detectTheme();
    this.initializeTheme();
    this.setupKeyboardNavigation();
    this.enhancePerformance();
    this.createThemeToggle();
  }

  detectTheme() {
    const saved = localStorage.getItem('fossbilling-theme');
    if (saved) return saved;
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  initializeTheme() {
    this.applyTheme(this.theme);
    
    // Watch for system theme changes (only if user hasn't set preference)
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
      if (!localStorage.getItem('fossbilling-theme')) {
        this.theme = e.matches ? 'dark' : 'light';
        this.applyTheme(this.theme);
        this.updateToggleButton();
      }
    });
  }

  applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    document.documentElement.setAttribute('data-theme', theme);
    this.theme = theme;
  }

  toggleTheme() {
    const newTheme = this.theme === 'light' ? 'dark' : 'light';
    this.applyTheme(newTheme);
    localStorage.setItem('fossbilling-theme', newTheme);
    this.updateToggleButton();
    
    // Dispatch custom event for other components
    window.dispatchEvent(new CustomEvent('themeChanged', { 
      detail: { theme: newTheme } 
    }));
  }

  createThemeToggle() {
    // Setup navbar theme toggle (if it exists)
    const navbarToggle = document.querySelector('.navbar-theme-toggle');
    if (navbarToggle) {
      navbarToggle.addEventListener('click', () => this.toggleTheme());
      navbarToggle.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          this.toggleTheme();
        }
      });
      this.navbarToggle = navbarToggle;
    }

    // Create floating theme toggle button (fallback if no navbar)
    if (!navbarToggle) {
      const toggleButton = document.createElement('button');
      toggleButton.className = 'btn btn-outline-secondary btn-sm theme-toggle position-fixed';
      toggleButton.style.cssText = `
        top: 20px;
        right: 20px;
        z-index: 1050;
        border-radius: 50px;
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem;
        box-shadow: var(--shadow-md);
        transition: var(--transition-base);
        backdrop-filter: blur(10px);
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid var(--border-color);
      `;
      toggleButton.setAttribute('title', 'Toggle theme');
      toggleButton.setAttribute('aria-label', 'Toggle dark/light theme');
      
      // Add click handler
      toggleButton.addEventListener('click', () => this.toggleTheme());
      
      // Add keyboard support
      toggleButton.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          this.toggleTheme();
        }
      });

      // Add to page
      document.body.appendChild(toggleButton);
      this.toggleButton = toggleButton;
    }
    
    // Set initial icon for both buttons
    this.updateToggleButton();

    // Add hover effects with CSS and emoji styling
    const style = document.createElement('style');
    style.textContent = `
      .theme-toggle:hover, .navbar-theme-toggle:hover {
        transform: scale(1.1);
        box-shadow: var(--shadow-lg);
      }
      
      .navbar-theme-toggle {
        border-radius: 50px;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: var(--transition-base);
      }
      
      .emoji-icon {
        font-size: 1.2em;
        display: inline-block;
        min-width: 1.5em;
        text-align: center;
        margin-right: 0.25rem;
      }
      
      .theme-icon {
        font-size: 1.1em;
        transition: var(--transition-base);
      }
      
      .navbar-theme-toggle:hover .theme-icon {
        transform: rotate(20deg);
      }
      
      [data-bs-theme="dark"] .theme-toggle,
      [data-bs-theme="dark"] .navbar-theme-toggle {
        background: rgba(30, 41, 59, 0.9) !important;
        border-color: var(--border-color) !important;
        color: var(--text-primary) !important;
      }
      
      [data-bs-theme="light"] .theme-toggle,
      [data-bs-theme="light"] .navbar-theme-toggle {
        background: rgba(255, 255, 255, 0.9) !important;
        border-color: var(--border-color) !important;
        color: var(--text-primary) !important;
      }
      
      @media (max-width: 768px) {
        .theme-toggle {
          top: 15px !important;
          right: 15px !important;
          width: 45px !important;
          height: 45px !important;
        }
        .navbar-theme-toggle {
          width: 35px !important;
          height: 35px !important;
        }
        .emoji-icon {
          font-size: 1.1em;
        }
      }
    `;
    document.head.appendChild(style);
  }

  updateToggleButton() {
    const isDark = this.theme === 'dark';
    
    // Use emoji icons with better styling and labels
    const emojiIcon = isDark ? '☀️' : '🌙';
    const title = isDark ? 'Switch to light mode' : 'Switch to dark mode';

    // Update floating toggle button securely
    if (this.toggleButton) {
      // Clear existing content
      while (this.toggleButton.firstChild) {
        this.toggleButton.removeChild(this.toggleButton.firstChild);
      }
      
      // Create and append icon span
      const iconSpan = document.createElement('span');
      iconSpan.className = 'theme-icon';
      iconSpan.textContent = emojiIcon;
      this.toggleButton.appendChild(iconSpan);
      
      this.toggleButton.setAttribute('title', title);
      this.toggleButton.setAttribute('aria-label', title);
    }

    // Update navbar toggle button securely
    if (this.navbarToggle) {
      // Clear existing content
      while (this.navbarToggle.firstChild) {
        this.navbarToggle.removeChild(this.navbarToggle.firstChild);
      }
      
      // Create and append icon span
      const iconSpan = document.createElement('span');
      iconSpan.className = 'theme-icon';
      iconSpan.textContent = emojiIcon;
      this.navbarToggle.appendChild(iconSpan);
      
      this.navbarToggle.setAttribute('title', title);
      this.navbarToggle.setAttribute('aria-label', title);
    }
  }

  setupKeyboardNavigation() {
    // Enhanced keyboard navigation for better accessibility
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        // Close modals, dropdowns, etc.
        const activeModal = document.querySelector('.modal.show');
        if (activeModal) {
          bootstrap.Modal.getInstance(activeModal)?.hide();
        }
      }
    });
  }

  enhancePerformance() {
    // Lazy load images
    const images = document.querySelectorAll('img[data-src]');
    if ('IntersectionObserver' in window) {
      const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const img = entry.target;
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
            imageObserver.unobserve(img);
          }
        });
      });
      images.forEach(img => imageObserver.observe(img));
    }

    // Optimize animations for users who prefer reduced motion
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      document.documentElement.style.setProperty('--transition-base', 'none');
    }
  }
}

document.addEventListener('DOMContentLoaded', () => {
  // Initialize modern theme enhancements
  const modernTheme = new FOSSBillingModern();

  /**
   * Enable Bootstrap Tooltip with better performance
   */
  const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
  if (tooltipTriggerList.length > 0) {
    [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl, {
      trigger: 'hover focus'
    }));
  }

  /**
   * Enhanced flash message system with better UX
   */
  globalThis.flashMessage = ({message = '', reload = false, type = 'info'}) => {
    const key = 'flash-message';
    const sessionMessage = sessionStorage.getItem(key);
    
    if (message === '' && sessionMessage) {
      FOSSBilling.message(sessionMessage, type);
      sessionStorage.removeItem(key);
      return;
    }
    
    if (message) {
      sessionStorage.setItem(key, message);
      if (typeof reload === 'boolean' && reload) {
        // Add loading state before reload
        document.body.style.opacity = '0.7';
        bb.reload();
      } else if (typeof reload === 'string') {
        document.body.style.opacity = '0.7';
        bb.redirect(reload);
      }
    }
  };
  flashMessage({});

  /**
   * Enhanced form validation and UX
   */
  const requiredInputs = document.querySelectorAll('input[required], textarea[required], select[required]');
  requiredInputs.forEach(input => {
    const label = input.previousElementSibling || input.parentElement.querySelector('label');
    const isAuth = input.closest('.auth');
    
    if (!isAuth && label && label.tagName.toLowerCase() === 'label') {
      if (!label.querySelector('.text-danger')) {
        const asterisk = document.createElement('span');
        asterisk.textContent = ' *';
        asterisk.className = 'text-danger';
        asterisk.setAttribute('aria-label', 'required field');
        label.appendChild(asterisk);
      }
    }

    // Enhanced form validation feedback with emojis
    input.addEventListener('invalid', function() {
      this.classList.add('is-invalid');
      // Add visual feedback for screen readers
      this.setAttribute('aria-describedby', this.id + '-error');
    });
    
    input.addEventListener('input', function() {
      if (this.validity.valid) {
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
        this.removeAttribute('aria-describedby');
      }
    });
  });

  /**
   * Enhanced currency selector with loading states
   */
  const currencySelector = document.querySelectorAll('select.currency_selector');
  currencySelector.forEach(select => {
    select.addEventListener('change', async function() {
      const originalText = this.parentElement.querySelector('label')?.textContent;
      const spinner = document.createElement('span');
      spinner.className = 'spinner-border spinner-border-sm ms-2';
      spinner.setAttribute('role', 'status');
      this.disabled = true;
      this.parentElement.appendChild(spinner);

      try {
        await new Promise((resolve, reject) => {
          API.guest.post('cart/set_currency', {currency: this.value}, resolve, reject);
        });
        location.reload();
      } catch (error) {
        FOSSBilling.message(error, 'error');
        this.disabled = false;
        spinner.remove();
      }
    });
  });

  /**
   * Enhanced table interactions
   */
  const tables = document.querySelectorAll('table');
  tables.forEach(table => {
    // Add keyboard navigation for table rows
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach((row, index) => {
      row.setAttribute('tabindex', '0');
      row.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          const link = row.querySelector('a');
          if (link) {
            e.preventDefault();
            link.click();
          }
        }
      });
    });
  });

  /**
   * Smooth scrolling for anchor links
   */
  const anchorLinks = document.querySelectorAll('a[href^="#"]');
  anchorLinks.forEach(link => {
    link.addEventListener('click', function(e) {
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    });
  });
});

