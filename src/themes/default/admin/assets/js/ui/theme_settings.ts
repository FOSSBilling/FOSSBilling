export default function initThemeSettings() {

  document.querySelectorAll<HTMLFieldSetElement>('#theme-settings fieldset').forEach((el, index) => {
    if (el.dataset.fbAccordionInitialized) return;
    const legend = el.querySelector('legend');
    if (!legend) return;
    el.dataset.fbAccordionInitialized = 'true';
    const open = index === 0;
    const panelId = `collapse-body-${index}`;
    const headingId = `theme-settings-heading-${index}`;
    const button = document.createElement('button');
    button.className = `accordion-button${open ? '' : ' collapsed'}`;
    button.type = 'button';
    button.id = headingId;
    button.setAttribute('data-bs-toggle', 'collapse');
    button.setAttribute('data-bs-target', `#${panelId}`);
    button.setAttribute('aria-expanded', String(open));
    button.setAttribute('aria-controls', panelId);
    button.textContent = legend.textContent;

    const panel = document.createElement('div');
    panel.id = panelId;
    panel.className = `accordion-collapse collapse${open ? ' show' : ''}`;
    panel.setAttribute('aria-labelledby', headingId);
    const body = document.createElement('div');
    body.className = 'accordion-body pt-0';
    panel.append(body);
    const heading = document.createElement('h2');
    heading.className = 'accordion-header';
    heading.append(button);
    const item = document.createElement('div');
    item.className = 'accordion-item';
    item.append(heading, panel);
    el.before(item);
    // Move the original fields so IDs, values and extension listeners survive.
    legend.classList.add('visually-hidden');
    el.classList.add('border-0', 'p-0', 'm-0');
    body.append(el);
  });

  document.querySelectorAll('#theme-settings table').forEach((el) => {
    el.classList.add('w-100');
    el.querySelectorAll('tr').forEach((trEl) => {
      trEl.classList.add('ps-3', 'row');
    })
  });

  document.querySelectorAll('#theme-settings textarea').forEach((el) => {
    el.classList.add('form-control');
    if (el.parentElement.matches('td')) {
      el.parentElement.classList.add('col-sm-6', 'mb-2');
    }
  });

  document.querySelectorAll('#theme-settings select').forEach((el) => {
    el.classList.add('form-select');
    if (el.parentElement.matches('td')) {
      el.parentElement.classList.add('col-sm-6', 'mb-2');
    }
  });

  document.querySelectorAll('#theme-settings label').forEach((el) => {
    if (!el.querySelectorAll('input').length && !el.parentNode.querySelectorAll('input').length) {
      el.classList.add('form-label');
      if (el.parentElement.matches('td')) {
        el.parentElement.classList.add('col-sm-3');
      }
    }
  });

  document.querySelectorAll('#theme-settings h3').forEach((el) => {
    let hrEl = document.createElement('hr'),
      headingEl = document.createElement('h4');
    hrEl.classList.add('my-3');
    headingEl.classList.add('mb-3');
    el.before(hrEl); el.before(headingEl);
    headingEl.textContent = el.textContent;
    el.remove();
  });

  document.querySelectorAll('#theme-settings input').forEach((el) => {
    switch (el.getAttribute('type')) {
      case 'checkbox':
      case 'radio':
        el.classList.add('form-check-input');
        if (el.parentElement.matches('label')) {
          if (el.parentElement.parentElement.querySelectorAll('label').length > 2) {
            el.parentElement.classList.add('form-check');
          } else if (el.parentElement.parentElement.matches('td') && el.parentElement.parentElement.children.length === 4) {
            el.parentElement.classList.add('form-check', 'mb-0', 'd-flex', 'align-items-center');
            let spanEl = el.parentElement.parentElement.children[2];
            if (spanEl.matches('span')) {
              spanEl.classList.add('d-flex', 'align-items-center');
              spanEl.innerHTML = `<svg class="icon"><use href="#arrow-right" /></svg>`;
            }
          } else {
            el.parentElement.classList.add('form-check', 'form-check-inline');
          }
          if (el.parentElement.parentElement.matches('td')) {
            el.parentElement.parentElement.classList.add('col-sm-6', 'mb-2');
          }
        }
        break;
      case 'text':
        el.classList.add('form-control');
        if (el.parentElement.matches('td')) {
          el.parentElement.classList.add('col-sm-6', 'mb-2', 'd-flex', 'gap-2');
        }
    }
  });

}
