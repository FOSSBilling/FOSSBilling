document.addEventListener('DOMContentLoaded', () => {

  $('#theme-settings fieldset').each(function (index, value) {
    let show = '', collapsed = '';
    index === 0 ? show = 'show' : collapsed = 'collapsed';
    let heading = $(this).find('legend').text();
    let accordionBtn = `<button class="accordion-button ${collapsed}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-body-${index}" aria-expanded="true" aria-controls="collapse-${index}">${heading}</button>`;
    let accordionContent = `<div id="collapse-body-${index}" class="accordion-collapse collapse ${show}"><div class="accordion-body pt-0">${value.innerHTML}</div></div>`;
    $(this).wrapAll('<div class="accordion-item">');
    $('<h2 class="accordion-header"/>').html(accordionBtn).insertBefore($(this));
    $(this).parent().append(accordionContent);
  });

  $('#theme-settings table').each(function () {
    $(this).addClass('w-100');
    $(this).children().children('tr').each(function () {
      $(this).addClass('ps-3 row');
    })
  });

  $('#theme-settings textarea').each(function () {
    $(this).addClass('form-control').parent('td').addClass('col-sm-6 mb-2');
  });

  $('#theme-settings select').each(function () {
    $(this).addClass('form-select').parent().addClass('col-sm-6 mb-2');
  });

  $('#theme-settings label').each(function () {
    if (!$(this).siblings().is('input') && !$(this).children().is('input')) {
      $(this).addClass('form-label').parent('td').addClass('col-sm-3');
    }
  });

  $('#theme-settings h3').each(function () {
    $(this).before('<hr class="my-3" />');
    $(this).replaceWith(`<h4 class="mb-3">${$(this).text()}</h4>`);
  });

  $('#theme-settings input').each(function () {
    switch (($(this).attr('type'))) {
      case 'checkbox':
      case 'radio':
        $(this).addClass('form-check-input').parent('td').addClass('col-sm-6 mb-2');
        $(this).addClass('form-check-input').parent('label').parent('td').addClass('col-sm-6 mb-2');
        if ($(this).parent('label').parent().children('label').length > 2) {
          $(this).addClass('form-check-input').parent('label').addClass('form-check');
        } else if ($(this).parent('label').parent('td').children().length === 4) {
          let formLabel = $(this).addClass('form-check-input').parent('label');
          formLabel.addClass('form-check mb-0 d-flex align-items-center');
          let svg = `<svg class="icon"><use xlink:href="#arrow-right" /></svg>`;
          formLabel.siblings('span').addClass('d-flex align-items-center').html(svg)
        } else {
          $(this).addClass('form-check-input').parent('label').addClass('form-check form-check-inline');
        }
        break;
      case 'text':
        $(this).addClass('form-control').parent('td').addClass('col-sm-6 mb-2 d-flex gap-2');
    }
  });

});
