/**
 * env.js — tiny .env loader (kept intentionally simple, like the PHP one).
 * No dotenv dependency; just enough to read the leaked .env at runtime.
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export function loadEnv(file = path.join(__dirname, '.env')) {
  const env = {};
  try {
    for (const raw of fs.readFileSync(file, 'utf8').split('\n')) {
      const line = raw.trim();
      if (!line || line[0] === '#') continue;
      const eq = line.indexOf('=');
      if (eq === -1) continue;
      env[line.slice(0, eq).trim()] = line.slice(eq + 1).trim();
    }
  } catch { /* ignore */ }
  return env;
}
