document.addEventListener('DOMContentLoaded', () => {

  document.querySelectorAll('#theme-settings fieldset').forEach((el, index) => {
    let show = '', collapsed = '';
    index === 0 ? show = 'show' : collapsed = 'collapsed';
    let heading = el.querySelector('legend').textContent; // use textContent
  
    // Create accordion-button
    let accordionBtn = document.createElement('button');
    accordionBtn.className = `accordion-button ${collapsed}`;
    accordionBtn.type = 'button';
    accordionBtn.setAttribute('data-bs-toggle', 'collapse');
    accordionBtn.setAttribute('data-bs-target', `#collapse-body-${index}`);
    accordionBtn.setAttribute('aria-expanded', 'true');
    accordionBtn.setAttribute('aria-controls', `collapse-${index}`);
    accordionBtn.textContent = heading; // set the text content
  
    // Create accordion-collapse and its child accordion-body
    let accordionContent = document.createElement('div');
    accordionContent.id = `collapse-body-${index}`;
    accordionContent.className = `accordion-collapse collapse ${show}`;
  
    let accordionBody = document.createElement('div');
    accordionBody.className = 'accordion-body pt-0';
    accordionBody.innerHTML = el.innerHTML; // This is okay since el.innerHTML is from the DOM already
    accordionContent.appendChild(accordionBody);
  
    // Create wrap button with accordion-header class
    let wrapBtn = document.createElement('h2');
    wrapBtn.classList.add('accordion-header');
    wrapBtn.appendChild(accordionBtn); // append the button
  
    // Create accordion-item and combine all parts
    let accordionItem = document.createElement('div');
    accordionItem.classList.add('accordion-item');
    accordionItem.appendChild(wrapBtn);
    accordionItem.appendChild(accordionContent);
  
    // Replace the original element with the new structure
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
