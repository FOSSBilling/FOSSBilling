import Litepicker from 'litepicker';

type LitepickerOptions = ConstructorParameters<typeof Litepicker>[0];

interface LitepickerDate {
  format: (format: string) => string;
}

type DateRangeInput = HTMLInputElement & {
  dataset: DOMStringMap & {
    nameFrom: string;
    nameTo: string;
  };
};

export default function initDatepickers() {
  document.querySelectorAll<HTMLInputElement>('.datepicker').forEach(element => {
    element.autocomplete = 'off';
    new Litepicker({
      element: element,
      resetButton: true,
      autoRefresh: true,
      format: 'YYYY-MM-DD',
      dropdowns: {
        minYear: 1930,
        months: !!element.dataset.pickMonth,
        years: !!element.dataset.pickYear
      } as LitepickerOptions['dropdowns'],
      singleMode: !isRanger(element),
      delimiter: ' to ',
      buttonText: {
        previousMonth: '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M15 6l-6 6l6 6"></path></svg>',
        nextMonth: '<svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M9 6l6 6l-6 6"></path></svg>',
        reset: '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-clock-x" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M20.926 13.15a9 9 0 1 0 -7.835 7.784"></path><path d="M12 7v5l2 2"></path><path d="M22 22l-5 -5"></path><path d="M17 22l5 -5"></path></svg>',
      } as LitepickerOptions['buttonText'],
      setup: (picker) => {
        picker.on('selected', (date: LitepickerDate, dateTo?: LitepickerDate) => {
          if (isRanger(element) && dateTo) {
            setDateToEl(element.dataset.nameFrom, date.format('YYYY-MM-DD'), element);
            setDateToEl(element.dataset.nameTo, dateTo.format('YYYY-MM-DD'), element);
          }
        });
      }
    });

  });
}

function isRanger(element: HTMLInputElement): element is DateRangeInput {
  return Boolean(element.dataset.nameFrom && element.dataset.nameTo);
}

function setDateToEl(name: string, value: string, siblingEl: HTMLElement) {
  let element = document.getElementById(name) as HTMLInputElement | null;
  if (element === null) {
    element = document.createElement('input');
    element.type = 'hidden';
    element.id = name;
    element.name = name;
    element.value = value;
    siblingEl.parentNode!.insertBefore(element, siblingEl.nextSibling);
    return;
  }
  element.value = value;
}
