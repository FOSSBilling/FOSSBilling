import intlTelInput from 'intl-tel-input';

export default function initPhoneInput() {
  const inputs = document.querySelectorAll('.js-phone-input');

  if (inputs.length === 0) {
    return;
  }

  const countries = intlTelInput.getAllCountries();
  const supportedCountries = new Set(countries.map((country) => country.iso2));

  inputs.forEach((input) => {
    const form = input.closest('form') || document;
    const countryCodeInput = form.querySelector('input[name="phone_cc"], input[name$="[phone_cc]"]');

    if (!countryCodeInput) {
      return;
    }

    const initialPhoneCountryCode = countryCodeInput.value.trim();
    const countrySelect = form.querySelector('select[name="country"], select[name$="[country]"]');
    const selectedCountry = (countrySelect?.value || input.dataset.initialCountry || '').toLowerCase();
    const initialCountry = countries.find((country) => country.dialCode === initialPhoneCountryCode)?.iso2
      || (supportedCountries.has(selectedCountry) ? selectedCountry : '');

    const iti = intlTelInput(input, {
      initialCountry,
      separateDialCode: true,
      loadUtils: () => import('intl-tel-input/utils'),
    });

    const syncFields = () => {
      const countryData = iti.getSelectedCountry();
      const dialCode = countryData?.dialCode || countryCodeInput.value;
      countryCodeInput.value = input.value.trim() || initialPhoneCountryCode ? dialCode : '';

      if (input.value.trim().startsWith('+') && dialCode) {
        const internationalNumber = iti.getNumber();
        if (internationalNumber.startsWith(`+${dialCode}`)) {
          input.value = internationalNumber.replace(`+${dialCode}`, '').trim();
        }
      }
    };

    countrySelect?.addEventListener('change', () => {
      const country = countrySelect.value.toLowerCase();
      if (supportedCountries.has(country)) {
        iti.setSelectedCountry(country);
        syncFields();
      }
    });

    input.addEventListener('countrychange', syncFields);
    input.addEventListener('input', syncFields);
    input.form?.addEventListener('submit', syncFields, true);

    syncFields();
  });
}
