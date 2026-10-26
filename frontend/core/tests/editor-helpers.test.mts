import assert from 'node:assert/strict';
import { describe, test } from 'node:test';

import {
  EDITOR_CRASH_LIMIT,
  EDITOR_CRASH_WINDOW_MS,
  normalizeEditor,
  readElementData,
  recordEditorCrash,
  writeElementData,
} from '../editor-helpers.mts';

function makeElement(overrides = {}) {
  return {
    getAttribute: () => null,
    id: 'editor-1',
    dataset: {},
    ...overrides,
  };
}

function makeRawEditor(overrides = {}) {
  const model = makeModel();
  return { getData: () => '', setData: () => {}, model, ...overrides };
}

function makeModel() {
  const listeners = [];
  return {
    document: {
      on: (event, callback) => {
        listeners.push([event, callback]);
      },
    },
    fire(event) {
      listeners.filter(([name]) => name === event).forEach(([, callback]) => callback());
    },
    listenerCount(event) {
      return listeners.filter(([name]) => name === event).length;
    },
  };
}

describe('recordEditorCrash', () => {
  const policy = { limit: EDITOR_CRASH_LIMIT, windowMs: EDITOR_CRASH_WINDOW_MS };

  test('recovers on the first crash', () => {
    const decision = recordEditorCrash([], 1000, policy);

    assert.deepEqual(decision.times, [1000]);
    assert.equal(decision.shouldRecover, true);
  });

  test('recovers up to the crash limit within the window', () => {
    let times: number[] = [];
    for (const now of [1000, 2000, 3000]) {
      const decision = recordEditorCrash(times, now, policy);
      times = decision.times;
      assert.equal(decision.shouldRecover, true);
    }
  });

  test('refuses to recover once the limit is spent inside the window', () => {
    let times: number[] = [];
    for (const now of [1000, 2000, 3000]) {
      times = recordEditorCrash(times, now, policy).times;
    }

    const decision = recordEditorCrash(times, 4000, policy);

    assert.equal(decision.times.length, 4);
    assert.equal(decision.shouldRecover, false);
  });

  test('forgets crashes older than the rolling window', () => {
    const decision = recordEditorCrash([1000, 2000, 3000], 3000 + EDITOR_CRASH_WINDOW_MS, policy);

    assert.deepEqual(decision.times, [3000 + EDITOR_CRASH_WINDOW_MS]);
    assert.equal(decision.shouldRecover, true);
  });
});

describe('readElementData/writeElementData', () => {
  test('uses the value property for form-field elements', () => {
    const element = { value: '<p>old</p>' };

    assert.equal(readElementData(element), '<p>old</p>');

    writeElementData(element, '<p>new</p>');
    assert.equal(element.value, '<p>new</p>');
  });

  test('falls back to textContent for non-form elements', () => {
    const element = { textContent: 'old' };

    assert.equal(readElementData(element), 'old');

    writeElementData(element, 'new');
    assert.equal(element.textContent, 'new');
  });
});

describe('normalizeEditor', () => {
  test('rejects editors without getData and setData', () => {
    assert.throws(() => normalizeEditor(makeElement(), {}), /getData\(\) and setData\(\)/);
    assert.throws(() => normalizeEditor(makeElement(), null), /getData\(\) and setData\(\)/);
  });

  test('delegates getData and setData to the current instance', () => {
    let firstSet;
    let secondSet;
    const first = makeRawEditor({ getData: () => 'first', setData: (value) => { firstSet = value; } });
    const second = makeRawEditor({ getData: () => 'second', setData: (value) => { secondSet = value; } });
    const editor = normalizeEditor(makeElement(), first);

    assert.equal(editor.getData(), 'first');

    editor.replaceRaw(second);
    assert.equal(editor.getData(), 'second');

    editor.setData('hello');
    assert.equal(secondSet, 'hello');
    assert.equal(firstSet, undefined);
  });

  test('ignores empty or identical replaceRaw calls', () => {
    const first = makeRawEditor();
    const editor = normalizeEditor(makeElement(), first);

    editor.replaceRaw(null);
    editor.replaceRaw(first);

    assert.equal(editor.raw, first);
  });

  test('raw exposes editor.raw when the adapter provides one', () => {
    const inner = { marker: true };
    const raw = makeRawEditor({ raw: inner });
    const editor = normalizeEditor(makeElement(), raw);

    assert.equal(editor.raw, inner);
  });

  test('re-binds change listeners onto the replaced instance', () => {
    const first = makeRawEditor();
    const second = makeRawEditor();
    const editor = normalizeEditor(makeElement(), first);

    const seen = [];
    editor.onChange(() => seen.push('change'));

    editor.replaceRaw(second);
    second.model.fire('change:data');

    assert.deepEqual(seen, ['change']);
    assert.equal(first.model.listenerCount('change:data'), 1);
    assert.equal(second.model.listenerCount('change:data'), 1);
  });

  test('delegates onChange to editors that provide their own', () => {
    const registered = [];
    const raw = makeRawEditor({
      onChange: (callback) => registered.push(callback),
    });
    const editor = normalizeEditor(makeElement(), raw);

    editor.onChange(() => {});

    assert.equal(registered.length, 1);
  });

  test('focus prefers editor.focus and falls back to the editing view', () => {
    let focused = 0;
    const withFocus = makeRawEditor({ focus: () => { focused += 1; } });
    normalizeEditor(makeElement(), withFocus).focus();
    assert.equal(focused, 1);

    let viewFocused = 0;
    const withView = makeRawEditor({
      editing: { view: { focus: () => { viewFocused += 1; } } },
    });
    normalizeEditor(makeElement(), withView).focus();
    assert.equal(viewFocused, 1);
  });

  test('destroy delegates to the instance', async () => {
    let destroyed = 0;
    const raw = makeRawEditor({ destroy: () => { destroyed += 1; return Promise.resolve(); } });
    const editor = normalizeEditor(makeElement(), raw);

    await editor.destroy();

    assert.equal(destroyed, 1);
  });

  test('destroy resolves when the instance has no destroy method', async () => {
    const raw = makeRawEditor();
    const editor = normalizeEditor(makeElement(), raw);

    await assert.doesNotReject(editor.destroy());
  });

  test('reads the name from the name attribute, then the id', () => {
    const byName = normalizeEditor(makeElement({ getAttribute: (key) => (key === 'name' ? 'body' : null) }), makeRawEditor());
    assert.equal(byName.name, 'body');

    const byId = normalizeEditor(makeElement({ id: 'intro' }), makeRawEditor());
    assert.equal(byId.name, 'intro');
  });

  test('tracks the required flag from the dataset', () => {
    assert.equal(normalizeEditor(makeElement(), makeRawEditor()).required, false);
    assert.equal(
      normalizeEditor(makeElement({ dataset: { editorRequired: 'true' } }), makeRawEditor()).required,
      true,
    );
  });
});
