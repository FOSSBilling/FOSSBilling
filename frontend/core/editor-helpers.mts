/**
 * Pure helpers for the FOSSBilling editor runtime.
 *
 * Kept free of window/document access so they can be unit tested with plain
 * stubs. See tests/editor-helpers.test.mts.
 */

/** How many times an editor may be recreated for crashes inside the window. */
export const EDITOR_CRASH_LIMIT = 3;

/** Rolling window (ms) inside which repeated crashes count as a crash loop. */
export const EDITOR_CRASH_WINDOW_MS = 5000;

export interface EditorCrashPolicyOptions {
  limit: number;
  windowMs: number;
}

export interface EditorCrashDecision {
  times: number[];
  shouldRecover: boolean;
}

/**
 * Records a crash at `now` against previous crash timestamps and decides
 * whether the editor may be recreated again. Crashes older than the rolling
 * window are forgotten; once `limit` recoveries have been spent inside the
 * window, the next crash is refused (the editor is locked instead).
 */
export function recordEditorCrash(
  previousTimes: number[],
  now: number,
  options: EditorCrashPolicyOptions,
): EditorCrashDecision {
  const times = previousTimes.filter((time) => now - time < options.windowMs);
  times.push(now);

  return { times, shouldRecover: times.length <= options.limit };
}

/**
 * Reads the current editor content from the source element. FOSSBilling keeps
 * the element in sync on every editor change, so this is the last known-good
 * copy of the content - the safe source for recreating a crashed editor.
 */
export function readElementData(element) {
  if ('value' in element) {
    return element.value;
  }

  return element.textContent;
}

export function writeElementData(element, data) {
  if ('value' in element) {
    element.value = data;
  } else {
    element.textContent = data;
  }
}

/**
 * Wraps a raw adapter editor behind the stable API used across FOSSBilling
 * (getData/setData/focus/destroy/onChange/raw).
 *
 * Crash recovery recreates the underlying instance; `replaceRaw()` repoints
 * every method at the new instance and re-attaches change listeners on the
 * `model.document` path. Adapters that implement their own `onChange()` need
 * to survive instance swaps on their own.
 */
export function normalizeEditor(element, editor) {
  if (!editor || typeof editor.getData !== 'function' || typeof editor.setData !== 'function') {
    throw new Error('Editor adapters must return getData() and setData() methods.');
  }

  let current = editor;
  const changeCallbacks = [];

  const attachChangeListeners = () => {
    if (current.model?.document?.on) {
      changeCallbacks.forEach((callback) => {
        current.model.document.on('change:data', callback);
      });
    }
  };

  attachChangeListeners();

  const normalized = {
    get raw() {
      return current.raw || current;
    },
    getData: () => current.getData(),
    setData: (value) => current.setData(value),
    focus: () => {
      if (typeof current.focus === 'function') {
        current.focus();
        return;
      }

      if (current.editing?.view?.focus) {
        current.editing.view.focus();
      }
    },
    destroy: () => {
      if (typeof current.destroy === 'function') {
        return current.destroy();
      }

      return Promise.resolve();
    },
    onChange: (callback) => {
      changeCallbacks.push(callback);

      if (typeof current.onChange === 'function') {
        return current.onChange(callback);
      }

      if (current.model?.document?.on) {
        current.model.document.on('change:data', callback);
      }
    },
    replaceRaw(next) {
      if (!next || next === current) {
        return;
      }

      current = next;
      attachChangeListeners();
    },
    element,
    name: element.getAttribute('name') || element.id || null,
    required: element.dataset.editorRequired === 'true',
  };

  return normalized;
}
