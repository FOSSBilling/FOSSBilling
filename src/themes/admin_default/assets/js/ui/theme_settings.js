document.addEventListener('DOMContentLoaded', () => {

  document.querySelectorAll('#theme-settings fieldset').forEach((el, index) => {
    let show = '', collapsed = '';
    index === 0 ? show = 'show' : collapsed = 'collapsed';
    let heading = el.querySelector('legend').innerText;
    let accordionBtn = `<button class="accordion-button ${collapsed}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-body-${index}" aria-expanded="true" aria-controls="collapse-${index}">${heading}</button>`;
    let accordionContent = `<div id="collapse-body-${index}" class="accordion-collapse collapse ${show}"><div class="accordion-body pt-0">${el.innerHTML}</div></div>`;
    let wrapBtn = document.createElement('h2');
    wrapBtn.classList.add('accordion-header');
    wrapBtn.innerHTML = accordionBtn;
    let accordionItem = document.createElement('div');
    accordionItem.classList.add('accordion-item');
    accordionItem.innerHTML = wrapBtn.outerHTML + accordionContent;
    el.after(accordionItem);
    el.remove();
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
    headingEl.innerText = el.innerText;
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
              spanEl.innerHTML = `<svg class="icon"><use xlink:href="#arrow-right" /></svg>`;
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

});
