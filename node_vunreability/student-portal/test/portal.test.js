import assert from 'node:assert/strict';
import { once } from 'node:events';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';
import { createPortal } from '../app.js';

async function serve(t, databasePath = ':memory:') {
  const portal = createPortal({ databasePath });
  const server = portal.app.listen(0, '127.0.0.1');
  await once(server, 'listening');
  t.after(async () => {
    await new Promise((resolve, reject) => server.close(error => error ? reject(error) : resolve()));
    portal.close();
  });
  return `http://127.0.0.1:${server.address().port}`;
}

function browser(base) {
  let cookie;
  let token;

  async function visit(path, fields, { csrf = true } = {}) {
    const headers = new Headers();
    if (cookie) headers.set('Cookie', cookie);
    const options = { headers, redirect: 'manual' };
    if (fields) {
      options.method = 'POST';
      options.body = new URLSearchParams(fields);
      if (csrf && token) options.body.set('_csrf', token);
    }
    const response = await fetch(base + path, options);
    const issuedCookie = response.headers.get('set-cookie');
    if (issuedCookie) cookie = issuedCookie.split(';', 1)[0];
    const body = await response.text();
    const field = body.match(/name="_csrf" value="([a-f0-9]{64})"/);
    if (field) token = field[1];
    return { status: response.status, headers: response.headers, body };
  }

  async function signIn(username, password) {
    const form = await visit('/login');
    assert.equal(form.status, 200);
    const firstCookie = cookie;
    const result = await visit('/login', { username, password });
    assert.equal(result.status, 303, result.body);
    assert.notEqual(cookie, firstCookie);
    assert.equal(result.headers.get('location'), '/');
    const dashboard = await visit('/');
    assert.equal(dashboard.status, 200);
    return dashboard;
  }

  return { visit, signIn };
}

test('student requests a course and a registrar approves it once', async t => {
  const base = await serve(t);
  const student = browser(base);
  const registrar = browser(base);
  await student.signIn('amina', 'Amina-Classroom-27');

  const ownProfile = await student.visit('/profile');
  assert.match(ownProfile.body, /ST-1001/);
  assert.doesNotMatch(ownProfile.body, /ST-1002/);
  assert.equal((await student.visit('/students')).status, 403);
  assert.equal((await student.visit('/students/u-tobi')).status, 403);

  const enrollment = { courseCode: 'STA 202' };
  assert.equal((await student.visit('/enrollments', enrollment, { csrf: false })).status, 403);
  assert.equal((await student.visit('/enrollments', { courseCode: 'NIL 100' })).status, 400);
  assert.equal((await student.visit('/enrollments', enrollment)).status, 303);
  assert.equal((await student.visit('/enrollments', enrollment)).status, 409);
  assert.match((await student.visit('/')).body, /STA 202<[^\n]+>Pending</);

  const approval = '/requests/u-amina/STA%20202/approve';
  assert.equal((await student.visit(approval, {})).status, 403);
  await registrar.signIn('registrar', 'Registrar-Classroom-27');
  assert.match((await registrar.visit('/requests')).body, /STA 202/);
  assert.equal((await registrar.visit(approval, {})).status, 303);
  assert.equal((await registrar.visit(approval, {})).status, 409);
  assert.doesNotMatch((await registrar.visit('/requests')).body, /STA 202/);
  const overview = await student.visit('/');
  assert.match(overview.body, /STA 202<[^\n]+>Approved</);
  assert.equal(overview.body.match(/STA 202/g).length, 1);

  await student.visit('/logout', {});
  assert.equal((await student.visit('/profile')).headers.get('location'), '/login');
});

test('registrar edits and searches records while names render as text', async t => {
  const registrar = browser(await serve(t));
  await registrar.signIn('registrar', 'Registrar-Classroom-27');
  const filtered = await registrar.visit('/students?q=ST-1001');
  assert.match(filtered.body, /Amina Bello/);
  assert.doesNotMatch(filtered.body, /Tobi Okafor/);

  const record = '/students/u-amina';
  const edited = await registrar.visit(record, { displayName: 'Amina <Honours>', program: 'Cybersecurity' });
  assert.equal(edited.status, 303);
  assert.match((await registrar.visit(record)).body, /Amina &lt;Honours&gt;/);
  const directory = await registrar.visit('/students?q=honours');
  assert.match(directory.body, /Amina &lt;Honours&gt;/);
  assert.doesNotMatch(directory.body, /<Honours>/);
});

test('casebook and catalog downloads work while private documents are outside the public routes', async t => {
  const student = browser(await serve(t));
  assert.equal((await student.visit('/casebook')).headers.get('location'), '/login');
  assert.equal((await student.visit('/assets/portal.css')).status, 200);
  await student.signIn('tobi', 'Tobi-Classroom-27');
  const cases = await student.visit('/casebook');
  assert.equal(cases.status, 200);
  assert.equal(cases.body.match(/class="case-id"/g).length, 12);
  const guide = await student.visit('/resources/enrollment-guide');
  assert.equal(guide.status, 200);
  assert.match(guide.headers.get('content-disposition'), /attachment/);
  assert.equal((await student.visit('/resources/unknown-guide')).status, 404);
  assert.equal((await student.visit('/INSTRUCTOR.md')).status, 404);
  assert.equal((await student.visit('/assets/app.js')).status, 404);
});

test('saved enrollment remains available after restarting with the same database', async t => {
  const directory = await mkdtemp(join(tmpdir(), 'campus-registry-test-'));
  const databasePath = join(directory, 'registry.sqlite');
  try {
    await t.test('writes an enrollment', async instance => {
      const student = browser(await serve(instance, databasePath));
      await student.signIn('tobi', 'Tobi-Classroom-27');
      assert.equal((await student.visit('/enrollments', { courseCode: 'STA 202' })).status, 303);
    });
    await t.test('loads the saved enrollment', async instance => {
      const student = browser(await serve(instance, databasePath));
      const dashboard = await student.signIn('tobi', 'Tobi-Classroom-27');
      assert.match(dashboard.body, /STA 202<.+>Pending/);
    });
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});
