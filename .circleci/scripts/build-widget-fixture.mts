import { mkdir, writeFile } from 'node:fs/promises';
import { buildWidgetScript } from '../../tests/E2E/Playwright/helpers/widget-script.ts';

await mkdir('test-results/widgets', { recursive: true });
await writeFile('test-results/widgets/script.js', await buildWidgetScript(process.cwd()));
