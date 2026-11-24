class Fragment {
  constructor(value) {
    this.value = value;
  }
}

const entities = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

function render(value) {
  if (value instanceof Fragment) return value.value;
  if (Array.isArray(value)) return value.map(render).join('');
  return String(value ?? '').replace(/[&<>"']/g, character => entities[character]);
}

function html(strings, ...values) {
  return new Fragment(strings.reduce((result, string, index) =>
    result + string + render(values[index]), ''));
}

const studentLinks = [
  ['/', 'Overview'], ['/profile', 'My profile'], ['/courses', 'Course catalog']
];
const registrarLinks = [
  ['/', 'Overview'], ['/students', 'Student directory'], ['/requests', 'Enrollment requests']
];

const tokenField = csrf => html`<input type="hidden" name="_csrf" value="${csrf}">`;

export function page(res, { title, description, active = '', content, notice, status = 200 }) {
  const { user, csrf } = res.locals;
  const links = user?.role === 'registrar' ? registrarLinks : studentLinks;
  res.status(status).type('html').send(html`<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${title} · Campus Registry</title>
  <link rel="stylesheet" href="/assets/portal.css">
</head>
<body class="${user ? 'signed-in' : 'signed-out'}">
  ${user ? html`
    <a class="skip" href="#main">Skip to content</a>
    <aside class="sidebar">
      <a class="brand" href="/"><span class="brand-mark" aria-hidden="true">CR</span><span>Campus Registry</span></a>
      <nav aria-label="Portal">
        <div class="nav-group">
          ${links.map(([url, label]) => html`
            <a href="${url}" aria-current="${active === url ? 'page' : 'false'}">${label}</a>`)}
        </div>
        <div class="nav-group">
          <a href="/casebook" aria-current="${active === '/casebook' ? 'page' : 'false'}">Security casebook</a>
        </div>
      </nav>
      <div class="account">
        <div><strong>${user.display_name}</strong><span>${user.role === 'registrar' ? 'Registrar' : 'Student'}</span></div>
        <form method="post" action="/logout">${tokenField(csrf)}<button class="quiet" type="submit">Sign out</button></form>
      </div>
    </aside>` : ''}
  <main id="main" tabindex="-1">
    ${user ? '' : html`<a class="brand" href="/login"><span class="brand-mark" aria-hidden="true">CR</span><span>Campus Registry</span></a>`}
    <header class="page-header"><h1>${title}</h1>${description ? html`<p>${description}</p>` : ''}</header>
    ${notice ? html`<p class="notice" role="status">${notice}</p>` : ''}
    ${content}
  </main>
</body>
</html>`.value);
}

export function loginForm(csrf, error = '') {
  return html`
    <section class="panel login-panel" aria-label="Sign in form">
      ${error ? html`<p class="form-error" role="alert">${error}</p>` : ''}
      <form method="post" action="/login" class="stack">
        ${tokenField(csrf)}
        <label>Username <input name="username" autocomplete="username" maxlength="32" required></label>
        <label>Password <input name="password" type="password" autocomplete="current-password" maxlength="128" required></label>
        <button type="submit">Sign in</button>
      </form>
    </section>
    <p class="login-help">Use the fictional accounts listed in the project README.</p>`;
}

const badge = status => html`<span class="badge ${status === 'Approved' ? 'approved' : ''}">${status}</span>`;

export function studentOverview(enrollments) {
  return html`
    <section class="panel" aria-labelledby="enrollments-heading">
      <div class="section-header"><h2 id="enrollments-heading">My enrollments</h2><a class="button secondary" href="/courses">Browse courses</a></div>
      ${enrollments.length > 0 ? html`
        <div class="table-scroll"><table aria-labelledby="enrollments-heading">
          <thead><tr><th scope="col">Course</th><th scope="col">Credits</th><th scope="col">Status</th></tr></thead>
          <tbody>${enrollments.map(record => html`
            <tr><td><strong>${record.title}</strong><small>${record.code}</small></td><td>${record.credits}</td><td>${badge(record.status)}</td></tr>`)}</tbody>
        </table></div>` : html`<p>No courses requested yet.</p>`}
    </section>`;
}

export function registrarOverview(studentCount, pendingCount) {
  return html`
    <div class="overview-grid">
      <section class="panel summary">
        <h2>Student records</h2><p class="number">${studentCount}</p>
        <a class="button secondary" href="/students">Open directory</a>
      </section>
      <section class="panel summary">
        <h2>Pending enrollments</h2><p class="number">${pendingCount}</p>
        <a class="button secondary" href="/requests">Review requests</a>
      </section>
    </div>`;
}

export function studentDirectory(records, query) {
  return html`
    <section class="panel" aria-label="Student records">
      <form action="/students" method="get" class="search">
        <label>Search by name or student number <input name="q" type="search" maxlength="80" value="${query}"></label>
        <button type="submit">Search</button>
        ${query ? html`<a class="button secondary" href="/students">Clear</a>` : ''}
      </form>
      ${records.length > 0 ? html`
        <div class="table-scroll"><table aria-label="Directory results">
          <thead><tr><th scope="col">Student</th><th scope="col">Student number</th><th scope="col">Program</th></tr></thead>
          <tbody>${records.map(record => html`
            <tr><td><a href="/students/${encodeURIComponent(record.id)}">${record.display_name}</a></td>
            <td>${record.student_no}</td><td>${record.program}</td></tr>`)}</tbody>
        </table></div>` : html`<p>No students match your search.</p>`}
    </section>`;
}

export function profile(record, editOptions) {
  const { csrf, programs, values = record } = editOptions ?? {};
  return html`
    <section class="panel profile" aria-label="Student details">
      <dl>
        <div><dt>Student number</dt><dd>${record.student_no}</dd></div>
        <div><dt>Email</dt><dd>${record.email}</dd></div>
        <div><dt>Program</dt><dd>${record.program}</dd></div>
      </dl>
    </section>
    ${editOptions ? html`
      <section class="panel" aria-labelledby="edit-heading">
        <h2 id="edit-heading">Edit record</h2>
        ${editOptions.error ? html`<p class="form-error" role="alert">${editOptions.error}</p>` : ''}
        <form class="stack edit-form" method="post" action="/students/${encodeURIComponent(record.id)}">
          ${tokenField(csrf)}
          <label>Display name <input name="displayName" value="${values.display_name}" maxlength="80" required></label>
          <label>Program <select name="program">
            ${programs.map(program => html`<option ${program === values.program ? 'selected' : ''}>${program}</option>`)}
          </select></label>
          <button type="submit">Save record</button>
        </form>
      </section>` : ''}`;
}

export function catalog(courses, csrf) {
  return html`
    <div class="course-grid">
      ${courses.map(course => html`
        <article class="panel course">
          <p class="meta"><strong>${course.code}</strong><span>${course.credits} credits</span></p>
          <h2>${course.title}</h2><p>${course.description}</p>
          ${course.status ? html`<div>${badge(course.status)}</div>` : html`
            <form method="post" action="/enrollments">
              ${tokenField(csrf)}
              <input type="hidden" name="courseCode" value="${course.code}">
              <button class="secondary" type="submit" aria-label="Request enrollment in ${course.title}">Request enrollment</button>
            </form>`}
        </article>`)}
    </div>
    <section class="panel section-header" aria-labelledby="resources-heading">
      <h2 id="resources-heading">Course resources</h2><a class="button secondary" href="/resources/enrollment-guide">Download enrollment guide</a>
    </section>`;
}

export function requests(records, csrf) {
  return html`
    <section class="panel" aria-label="Requests awaiting review">
      ${records.length > 0 ? html`
        <div class="table-scroll"><table aria-label="Pending enrollment requests">
          <thead><tr><th scope="col">Student</th><th scope="col">Course</th><th scope="col">Decision</th></tr></thead>
          <tbody>${records.map(record => html`
            <tr>
              <td><strong>${record.display_name}</strong><small>${record.student_no}</small></td>
              <td><strong>${record.title}</strong><small>${record.code}</small></td>
              <td><form method="post" action="/requests/${encodeURIComponent(record.student_id)}/${encodeURIComponent(record.code)}/approve">
                ${tokenField(csrf)}
                <button class="secondary" type="submit" aria-label="Approve ${record.code} for ${record.display_name}">Approve</button>
              </form></td>
            </tr>`)}</tbody>
        </table></div>` : html`<p>There are no enrollment requests to review.</p>`}
    </section>`;
}

export function casebook(cases) {
  return html`
    <div class="casebook">
      ${cases.map(item => html`
        <details class="panel case">
          <summary>
            <span class="case-id">${item.id}</span>
            <span class="case-title"><small>${item.area}</small><strong>${item.title}</strong></span>
          </summary>
          <dl class="case-content">
            <div><dt>Fictional evidence</dt><dd>${item.evidence}</dd></div>
            <div><dt>Investigation</dt><dd>${item.investigation}</dd></div>
            <div><dt>Portal check</dt><dd>${item.check}</dd></div>
          </dl>
        </details>`)}
    </div>`;
}

export function errorContent(message) {
  return html`<section class="panel"><p>${message}</p><a class="button secondary" href="/">Return to overview</a></section>`;
}
