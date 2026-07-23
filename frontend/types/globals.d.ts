type ApiParams = Record<string, unknown> | FormData | string | null | undefined;
type ApiSuccessHandler = (result: unknown) => void;
type ApiErrorHandler = (error: { message: string; code?: string }) => void;

interface FOSSBillingApiNamespace {
  get(endpoint: string, params?: ApiParams, successHandler?: ApiSuccessHandler, errorHandler?: ApiErrorHandler): void;
  post(endpoint: string, params?: ApiParams, successHandler?: ApiSuccessHandler, errorHandler?: ApiErrorHandler): void;
  put(endpoint: string, params?: ApiParams, successHandler?: ApiSuccessHandler, errorHandler?: ApiErrorHandler): void;
  patch(endpoint: string, params?: ApiParams, successHandler?: ApiSuccessHandler, errorHandler?: ApiErrorHandler): void;
  delete(endpoint: string, params?: ApiParams, successHandler?: ApiSuccessHandler, errorHandler?: ApiErrorHandler): void;
}

interface FOSSBillingEditorInstance {
  destroy?: () => void | Promise<void>;
  getData: () => string;
  setData: (data: string) => void;
  model?: {
    document?: {
      on?: (event: string, callback: () => void) => void;
    };
  };
}

interface FOSSBillingEditorAdapter {
  create: (element: HTMLElement, options?: Record<string, unknown>) => Promise<FOSSBillingEditorInstance>;
}

interface FOSSBillingEditorRegistry {
  all(): FOSSBillingEditorInstance[];
  create(element: HTMLElement, options?: Record<string, unknown>): Promise<FOSSBillingEditorInstance>;
  get(elementOrName: HTMLElement | string): FOSSBillingEditorInstance | null;
  registerAdapter(name: string, adapter: FOSSBillingEditorAdapter): void;
  syncForm(form: HTMLFormElement, formData?: FormData): void;
  validateForm(form: HTMLFormElement): boolean;
}

interface FOSSBillingRuntime {
  api?: {
    admin: FOSSBillingApiNamespace;
    client: FOSSBillingApiNamespace;
    guest: FOSSBillingApiNamespace;
    makeRequest: (
      method: string,
      url: string,
      params?: ApiParams,
      successHandler?: ApiSuccessHandler,
      errorHandler?: ApiErrorHandler,
      enableLoader?: boolean,
      timeoutMs?: number,
      timeoutMessage?: string | null,
    ) => void;
    _afterComplete: (element: Element, result: unknown) => void;
  };
  backToTop?: () => void;
  charts?: {
    renderTimeSeriesSparkline: (target: HTMLElement | string, data: unknown, options?: Record<string, unknown>) => unknown;
  };
  cookieNames?: {
    locale: string;
    timezone: string;
  };
  cookieCreate?: (name: string, value: string, days?: number) => void;
  cookieRead?: (name: string) => string | null;
  detectTimezone?: () => string;
  editor?: FOSSBillingEditorRegistry;
  initTimezone?: () => void;
  message?: (message: string, type?: string) => void;
  ready?: (callback: (runtime: FOSSBillingRuntime) => void) => void;
  tools?: {
    getBaseURL: (url: string) => string;
    getCSRFToken: () => string | null;
    isJSON: (jsonString: string) => boolean;
    serializeFormData: (formData: FormData) => string;
    serializeFormDataToJSON: (formData: FormData) => string;
    serializeFormDataToObject: (formData: FormData) => Record<string, unknown>;
  };
  ui?: {
    notify: (message: string, type?: string) => void;
  };
}

interface ModalCreateOptions {
  confirmButton?: string;
  confirmButtonColor?: string;
  confirmCallback?: () => void;
  content?: string;
  label?: string;
  promptConfirmCallback?: (value: string) => void;
  title?: string;
  type?: string;
  value?: string;
}

interface ModalsRuntime {
  create(options: ModalCreateOptions): unknown;
  create(type: string, options: ModalCreateOptions): unknown;
  hideAll(): void;
}

interface BootstrapConstructor<T = unknown> {
  new (element: Element, options?: Record<string, unknown>): T;
  getInstance?: (element: Element) => T | null;
  getOrCreateInstance?: (element: Element) => T;
}

interface BootstrapRuntime {
  Collapse: BootstrapConstructor;
  Modal: BootstrapConstructor<{ hide: () => void; show: () => void }>;
  Popover: BootstrapConstructor<{ hide: () => void; show: () => void }>;
  Tab: BootstrapConstructor<{ show: () => void }>;
  Toast: BootstrapConstructor<{ show: () => void }>;
  Tooltip: BootstrapConstructor<{ dispose: () => void; hide: () => void; show: () => void }>;
}

declare global {
  const bootstrap: BootstrapRuntime;
  const FOSSBilling: FOSSBillingRuntime;
  const Modals: ModalsRuntime | undefined;

  interface Window {
    FOSSBilling: FOSSBillingRuntime;
  }

  interface Error {
    code?: string;
    rawBody?: string;
    status?: number;
  }

  var bootstrap: BootstrapRuntime;
  var flashMessage: ((options: { message?: string; reload?: boolean | string; type?: string }) => void) | undefined;
  var FOSSBilling: FOSSBillingRuntime;
  var Modals: ModalsRuntime | undefined;
  var TomSelect: unknown;
}

declare module '*.css' {
  const css: string;
  export default css;
}

declare module '*?raw' {
  const content: string;
  export default content;
}

declare module '@ckeditor/*/dist/*.css' {
  const css: string;
  export default css;
}

declare module 'bootstrap/dist/js/bootstrap.esm.js' {
  export const Collapse: BootstrapRuntime['Collapse'];
  export const Modal: BootstrapRuntime['Modal'];
  export const Popover: BootstrapRuntime['Popover'];
  export const Tab: BootstrapRuntime['Tab'];
  export const Toast: BootstrapRuntime['Toast'];
  export const Tooltip: BootstrapRuntime['Tooltip'];
}

declare module 'sortable-tablesort/dist/sortable.min.js';

export {};
