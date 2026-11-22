// Fails when code in src/app references a translation key that does not exist in de.json.
// Run with: node scripts/check-i18n-keys.mts   (Node runs TypeScript directly)
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';

const src = new URL('../src/', import.meta.url).pathname;
const translations = JSON.parse(readFileSync(join(src, 'i18n/de.json'), 'utf8')) as Record<string, unknown>;
const generatedUi = join(src, 'app/ui');

function exists(key: string): boolean {
  let node: unknown = translations;
  for (const part of key.split('.')) {
    if (typeof node !== 'object' || node === null || !(part in node)) {
      return false;
    }
    node = (node as Record<string, unknown>)[part];
  }
  return typeof node === 'string';
}

function sourceFiles(dir: string): string[] {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name);
    if (statSync(path).isDirectory()) {
      return path === generatedUi ? [] : sourceFiles(path);
    }
    return /\.(ts|html)$/.test(name) && !name.endsWith('.spec.ts') ? [path] : [];
  });
}

const patterns = [
  /\bt\(\s*'([a-z0-9_.]+)'/g, // i18n.t('key')
  /'([a-z0-9_.]+)'\s*\|\s*tc?\b/g, // {{ 'key' | t }} and {{ 'key' | tc: count }}
  /\bchoice\(\s*'([a-z0-9_.]+)'/g, // i18n.choice('key', n)
  /\b(?:success|error|info)\(\s*'([a-z0-9_.]+)'/g, // notify.error('key')
];

const missing: string[] = [];
for (const file of sourceFiles(join(src, 'app'))) {
  const source = readFileSync(file, 'utf8');
  for (const pattern of patterns) {
    for (const match of source.matchAll(pattern)) {
      if (!exists(match[1])) {
        missing.push(`${relative(src, file)}: ${match[1]}`);
      }
    }
  }
}

// Flavour overrides may only replace existing keys
const flavorsDir = new URL('../flavors/', import.meta.url).pathname;
function leaves(node: Record<string, unknown>, prefix = ''): string[] {
  return Object.entries(node).flatMap(([k, v]) =>
    typeof v === 'object' && v !== null ? leaves(v as Record<string, unknown>, `${prefix}${k}.`) : [`${prefix}${k}`],
  );
}
for (const flavor of readdirSync(flavorsDir)) {
  for (const file of readdirSync(join(flavorsDir, flavor, 'i18n'))) {
    const base = JSON.parse(readFileSync(join(src, 'i18n', file), 'utf8')) as Record<string, unknown>;
    const override = JSON.parse(readFileSync(join(flavorsDir, flavor, 'i18n', file), 'utf8')) as Record<string, unknown>;
    const known = new Set(leaves(base));
    for (const key of leaves(override)) {
      if (!known.has(key)) {
        missing.push(`flavors/${flavor}/i18n/${file}: ${key} (not in base texts)`);
      }
    }
  }
}

if (missing.length > 0) {
  console.error(`Unknown translation keys:\n${missing.join('\n')}`);
  process.exit(1);
}
console.log('All translation keys used in src/app exist.');
