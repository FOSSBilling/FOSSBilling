export interface CurrencyFormatOptions {
  code: string;
  format_pattern?: string | null;
  fraction_digits?: number | null;
}

function getFractionDigits(options: CurrencyFormatOptions): number | undefined {
  const value = options.fraction_digits;

  return Number.isInteger(value) && value >= 0 && value <= 6 ? value : undefined;
}

export function formatCurrencyAmount(
  amount: number,
  options: CurrencyFormatOptions,
  locale?: string | string[],
): string {
  const fractionDigits = getFractionDigits(options);
  const fractionOptions = fractionDigits === undefined ? {} : {
    minimumFractionDigits: fractionDigits,
    maximumFractionDigits: fractionDigits,
  };
  const pattern = options.format_pattern?.trim();

  if (!pattern || pattern.split('{amount}').length !== 2) {
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency: options.code,
      currencyDisplay: 'narrowSymbol',
      ...fractionOptions,
    }).format(amount);
  }

  let decimalFractionOptions = fractionOptions;
  if (fractionDigits === undefined) {
    const currencyOptions = new Intl.NumberFormat(locale, {
      style: 'currency',
      currency: options.code,
    }).resolvedOptions();
    decimalFractionOptions = {
      minimumFractionDigits: currencyOptions.minimumFractionDigits,
      maximumFractionDigits: currencyOptions.maximumFractionDigits,
    };
  }

  const formattedAmount = new Intl.NumberFormat(locale, {
    style: 'decimal',
    ...decimalFractionOptions,
  }).format(amount);

  return pattern.replace('{amount}', formattedAmount);
}
