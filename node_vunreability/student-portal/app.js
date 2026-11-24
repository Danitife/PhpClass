import { randomBytes, scrypt, timingSafeEqual } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';
import express from 'express';
import { rateLimit } from 'express-rate-limit';
import session from 'express-session';
import helmet from 'helmet';
import { cases } from './cases.js';
import { openDatabase, programs } from './data.js';
import {
  page, loginForm, studentOverview, registrarOverview, studentDirectory,
  profile, catalog, requests, casebook, errorContent
} from './views.js';

const deriveKey = promisify(scrypt);
const makeToken = () => randomBytes(32).toString('hex');
const cookieName = 'campus.sid';
const cookieOptions = { httpOnly: true, sameSite: 'lax', path: '/' };
const publicPath = fileURLToPath(new URL('./public/', import.meta.url));
const resources = new Map([
  ['enrollment-guide', {
    name: 'enrollment-guide.txt',
    body: 'Campus Registry enrollment guide\n\nOpen the course catalog and request enrollment.\nThe registrar reviews new requests. Check your overview for the decision.\n'
  }]
]);

function respondError(res, status, title, message) {
  return page(res, { status, title, content: errorContent(message) });
}

function requireUser(req, res, next) {
  if (!res.locals.user) return res.redirect(303, '/login');
  next();
}

function requireRole(role) {
  return (req, res, next) => {
    if (!res.locals.user) return res.redirect(303, '/login');
    if (res.locals.user.role !== role) {
      return respondError(res, 403, 'Access denied', 'Your account cannot open this part of the portal.');
    }
    next();
  };
}

function requireCsrf(req, res, next) {
  const submitted = req.body?._csrf;
  const expected = req.session.csrf;
  if (!expected || typeof submitted !== 'string' || !/^[a-f0-9]{64}$/.test(submitted) ||
      !timingSafeEqual(Buffer.from(expected, 'hex'), Buffer.from(submitted, 'hex'))) {
    return respondError(res, 403, 'Form expired or invalid', 'Open the form again and retry your request.');
  }
  next();
}

function showLogin(res, status = 200, error = '') {
  page(res, {
    title: 'Sign in',
    description: 'Open your student or registrar workspace.',
    status,
    content: loginForm(res.locals.csrf, error)
  });
}

export function createPortal({ databasePath }) {
  const db = openDatabase(databasePath);
  const app = express();
  const dummySalt = makeToken();
  const dummyHash = randomBytes(64);

  const sessionUser = db.prepare('SELECT id, display_name, role FROM users WHERE id = ?');
  const credentials = db.prepare('SELECT id, salt, password_hash FROM users WHERE username = ?');
  const studentRecord = db.prepare(`
    SELECT id, display_name, student_no, program, email FROM users WHERE id = ? AND role = 'student'
  `);
  const students = db.prepare(`
    SELECT id, display_name, student_no, program FROM users WHERE role = 'student' ORDER BY display_name
  `);
  const studentCount = db.prepare("SELECT count(*) AS total FROM users WHERE role = 'student'");
  const pendingCount = db.prepare("SELECT count(*) AS total FROM enrollments WHERE status = 'Pending'");
  const studentCourses = db.prepare(`
    SELECT c.code, c.title, c.credits, e.status
    FROM enrollments e JOIN courses c ON c.code = e.course_code
    WHERE e.student_id = ? ORDER BY c.code
  `);
  const availableCourses = db.prepare(`
    SELECT c.code, c.title, c.description, c.credits, e.status
    FROM courses c LEFT JOIN enrollments e ON e.course_code = c.code AND e.student_id = ?
    ORDER BY c.code
  `);
  const getCourse = db.prepare('SELECT code FROM courses WHERE code = ?');
  const pendingEnrollments = db.prepare(`
    SELECT e.student_id, u.display_name, u.student_no, c.code, c.title
    FROM enrollments e JOIN users u ON u.id = e.student_id JOIN courses c ON c.code = e.course_code
    WHERE e.status = 'Pending' ORDER BY u.display_name, c.code
  `);
  const insertEnrollment = db.prepare(`
    INSERT INTO enrollments (student_id, course_code) VALUES (?, ?)
    ON CONFLICT (student_id, course_code) DO NOTHING
  `);
  const approveEnrollment = db.prepare(`
    UPDATE enrollments SET status = 'Approved' WHERE student_id = ? AND course_code = ? AND status = 'Pending'
  `);
  const updateStudent = db.prepare(`
    UPDATE users SET display_name = ?, program = ? WHERE id = ? AND role = 'student'
  `);

  app.disable('x-powered-by');
  app.set('trust proxy', false);
  app.use(helmet({
    contentSecurityPolicy: {
      directives: {
        scriptSrc: ["'none'"],
        frameAncestors: ["'none'"],
        upgradeInsecureRequests: null
      }
    },
    strictTransportSecurity: false
  }));
  app.use('/assets', express.static(publicPath, { index: false, redirect: false }));
  app.use((req, res, next) => {
    res.set('Cache-Control', 'no-store');
    next();
  });
  app.use(express.urlencoded({ extended: false, limit: '8kb', parameterLimit: 12 }));
  app.use(session({
    name: cookieName,
    secret: makeToken(),
    resave: false,
    saveUninitialized: false,
    cookie: { ...cookieOptions, maxAge: 1000 * 60 * 60 * 2 }
  }));
  app.use((req, res, next) => {
    res.locals.user = req.session.userId ? sessionUser.get(req.session.userId) : undefined;
    res.locals.csrf = req.session.csrf;
    next();
  });

  const loginLimit = rateLimit({
    windowMs: 10 * 60 * 1000,
    limit: 12,
    standardHeaders: 'draft-8',
    legacyHeaders: false,
    skipSuccessfulRequests: true,
    handler: (req, res) => showLogin(res, 429, 'Too many sign-in attempts. Wait a few minutes and try again.')
  });

  app.get('/login', (req, res) => {
    if (res.locals.user) return res.redirect('/');
    req.session.csrf ??= makeToken();
    res.locals.csrf = req.session.csrf;
    showLogin(res);
  });
  app.post('/login', requireCsrf, loginLimit, async (req, res, next) => {
    const { username, password } = req.body;
    if (typeof username !== 'string' || !/^[a-z0-9]{1,32}$/.test(username) ||
        typeof password !== 'string' || password.length < 1 || password.length > 128) {
      return showLogin(res, 400, 'Enter a valid username and password.');
    }
    const account = credentials.get(username);
    const expected = account ? Buffer.from(account.password_hash, 'hex') : dummyHash;
    const candidate = await deriveKey(password, account?.salt ?? dummySalt, expected.length);
    if (!timingSafeEqual(candidate, expected) || !account) {
      return showLogin(res, 401, 'The username or password was incorrect.');
    }

    req.session.regenerate(error => {
      if (error) return next(error);
      req.session.userId = account.id;
      req.session.csrf = makeToken();
      req.session.save(error => {
        if (error) return next(error);
        res.redirect(303, '/');
      });
    });
  });
  app.post('/logout', requireUser, requireCsrf, (req, res, next) => {
    req.session.destroy(error => {
      if (error) return next(error);
      res.clearCookie(cookieName, cookieOptions).redirect(303, '/login');
    });
  });

  const studentOnly = requireRole('student');
  const registrarOnly = requireRole('registrar');

  app.get('/', requireUser, (req, res) => {
    const user = res.locals.user;
    page(res, {
      title: `Welcome, ${user.display_name}`,
      description: user.role === 'registrar' ? 'Review student records and enrollment requests.' : 'Track your requested and approved courses.',
      active: '/',
      content: user.role === 'registrar'
        ? registrarOverview(studentCount.get().total, pendingCount.get().total)
        : studentOverview(studentCourses.all(user.id))
    });
  });
  app.get('/profile', studentOnly, (req, res) => {
    page(res, {
      title: 'My profile', description: 'Your record in the campus registry.', active: '/profile',
      content: profile(studentRecord.get(res.locals.user.id))
    });
  });

  function showCatalog(res, notice, status = 200) {
    page(res, {
      title: 'Course catalog', description: 'Request enrollment in an available course.', active: '/courses',
      status, notice, content: catalog(availableCourses.all(res.locals.user.id), res.locals.csrf)
    });
  }
  app.get('/courses', studentOnly, (req, res) => {
    showCatalog(res, req.query.result === 'requested' ? 'Enrollment requested.' : undefined);
  });
  app.post('/enrollments', studentOnly, requireCsrf, (req, res) => {
    const code = req.body.courseCode;
    if (typeof code !== 'string' || !/^[A-Z]{3} [0-9]{3}$/.test(code) || !getCourse.get(code)) {
      return respondError(res, 400, 'Unknown course', 'Choose a course from the catalog.');
    }
    if (insertEnrollment.run(res.locals.user.id, code).changes === 0) {
      return showCatalog(res, 'An enrollment already exists for this course.', 409);
    }
    res.redirect(303, '/courses?result=requested');
  });

  app.get('/students', registrarOnly, (req, res) => {
    const query = typeof req.query.q === 'string' ? req.query.q.trim().slice(0, 80) : '';
    const term = query.toLowerCase();
    const matches = students.all().filter(record =>
      record.display_name.toLowerCase().includes(term) || record.student_no.toLowerCase().includes(term));
    page(res, {
      title: 'Student directory', description: 'Find and update student records.', active: '/students',
      content: studentDirectory(matches, query)
    });
  });

  function showRecord(res, record, { notice, error, values, status = 200 } = {}) {
    page(res, {
      title: record.display_name, active: '/students', status, notice,
      content: profile(record, { csrf: res.locals.csrf, values, programs, error })
    });
  }
  app.get('/students/:studentId', registrarOnly, (req, res) => {
    const record = studentRecord.get(req.params.studentId);
    if (!record) return respondError(res, 404, 'Student unavailable', 'This record could not be found.');
    showRecord(res, record, { notice: req.query.result === 'saved' ? 'Student record saved.' : undefined });
  });
  app.post('/students/:studentId', registrarOnly, requireCsrf, (req, res) => {
    const record = studentRecord.get(req.params.studentId);
    if (!record) return respondError(res, 404, 'Student unavailable', 'This record could not be found.');
    const { displayName, program } = req.body;
    const name = typeof displayName === 'string' ? displayName.trim() : '';
    if (!name || name.length > 80 || !programs.includes(program)) {
      return showRecord(res, record, {
        status: 400,
        error: 'Enter a display name up to 80 characters and choose a listed program.',
        values: { display_name: name.slice(0, 80), program: programs.includes(program) ? program : record.program }
      });
    }
    updateStudent.run(name, program, record.id);
    res.redirect(303, `/students/${encodeURIComponent(record.id)}?result=saved`);
  });

  app.get('/requests', registrarOnly, (req, res) => {
    page(res, {
      title: 'Enrollment requests', description: 'Review the courses students have requested.', active: '/requests',
      notice: req.query.result === 'approved' ? 'Enrollment approved.' : undefined,
      content: requests(pendingEnrollments.all(), res.locals.csrf)
    });
  });
  app.post('/requests/:studentId/:courseCode/approve', registrarOnly, requireCsrf, (req, res) => {
    if (approveEnrollment.run(req.params.studentId, req.params.courseCode).changes === 0) {
      return respondError(res, 409, 'Request unavailable', 'Only a pending enrollment can be approved.');
    }
    res.redirect(303, '/requests?result=approved');
  });

  app.get('/resources/:resourceId', requireUser, (req, res) => {
    const resource = resources.get(req.params.resourceId);
    if (!resource) return respondError(res, 404, 'Resource unavailable', 'This resource is absent from the catalog.');
    res.attachment(resource.name).type('text/plain').send(resource.body);
  });
  app.get('/casebook', requireUser, (req, res) => {
    page(res, {
      title: 'Security casebook',
      description: 'Review each fictional incident and use the portal to verify the expected behavior.',
      active: '/casebook',
      content: casebook(cases)
    });
  });

  app.use((req, res) => respondError(res, 404, 'Page unavailable', 'This portal page could not be found.'));
  app.use((error, req, res, next) => {
    if (res.headersSent) return next(error);
    if (error.status === 400 || error.status === 413) {
      return respondError(res, error.status, 'Invalid request', 'Check the form and try again.');
    }
    console.error(error);
    respondError(res, 500, 'Portal error', 'The portal could not complete the request.');
  });
  return { app, close: () => db.close() };
}
