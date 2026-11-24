import { homedir } from 'node:os';
import { join } from 'node:path';
import { createPortal } from './app.js';

const port = Number(process.env.STUDENT_PORTAL_PORT ?? 4317);
if (!Number.isInteger(port) || port < 1 || port > 65535) {
  throw new RangeError('STUDENT_PORTAL_PORT must be an integer between 1 and 65535.');
}

const databasePath = process.env.STUDENT_PORTAL_DB ?? join(homedir(), '.student-portal', 'registry.sqlite');
const { app } = createPortal({ databasePath });
app.listen(port, '127.0.0.1', () => {
  console.log(`Campus Registry is running at http://127.0.0.1:${port}`);
});
