import { randomBytes, scryptSync } from 'node:crypto';
import { mkdirSync } from 'node:fs';
import { dirname } from 'node:path';
import { DatabaseSync } from 'node:sqlite';

export const programs = ['Computer Science', 'Information Systems', 'Cybersecurity'];

const people = [
  ['u-amina', 'amina', 'Amina Bello', 'student', 'ST-1001', 'Computer Science', 'amina@class.example', 'Amina-Classroom-27'],
  ['u-tobi', 'tobi', 'Tobi Okafor', 'student', 'ST-1002', 'Information Systems', 'tobi@class.example', 'Tobi-Classroom-27'],
  ['u-zainab', 'zainab', 'Zainab Musa', 'student', 'ST-1003', 'Cybersecurity', 'zainab@class.example', 'Zainab-Classroom-27'],
  ['u-registrar', 'registrar', 'Nneka Adeyemi', 'registrar', null, null, 'registrar@class.example', 'Registrar-Classroom-27']
];

const courses = [
  ['CSC 214', 'Web Application Development', 'Design HTTP routes and build server-rendered applications.', 3],
  ['SEC 210', 'Application Security', 'Review access decisions, validation and safe data handling.', 3],
  ['INF 208', 'Database Systems', 'Model records, enforce constraints and query relational data.', 2],
  ['STA 202', 'Applied Statistics', 'Interpret data and communicate findings with clear evidence.', 2]
];

export function openDatabase(path) {
  if (path !== ':memory:') {
    mkdirSync(dirname(path), { recursive: true, mode: 0o700 });
  }
  const db = new DatabaseSync(path);
  db.exec(`
    PRAGMA busy_timeout = 5000;
    CREATE TABLE IF NOT EXISTS users (
      id TEXT PRIMARY KEY,
      username TEXT NOT NULL UNIQUE,
      display_name TEXT NOT NULL,
      role TEXT NOT NULL CHECK (role IN ('student', 'registrar')),
      student_no TEXT UNIQUE,
      program TEXT,
      email TEXT NOT NULL UNIQUE,
      salt TEXT NOT NULL,
      password_hash TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS courses (
      code TEXT PRIMARY KEY,
      title TEXT NOT NULL,
      description TEXT NOT NULL,
      credits INTEGER NOT NULL CHECK (credits > 0)
    );
    CREATE TABLE IF NOT EXISTS enrollments (
      student_id TEXT NOT NULL REFERENCES users(id),
      course_code TEXT NOT NULL REFERENCES courses(code),
      status TEXT NOT NULL DEFAULT 'Pending' CHECK (status IN ('Pending', 'Approved')),
      PRIMARY KEY (student_id, course_code)
    );
  `);

  db.exec('BEGIN IMMEDIATE');
  try {
    if (db.prepare('SELECT count(*) AS total FROM users').get().total === 0) {
      const insertPerson = db.prepare(`
        INSERT INTO users (id, username, display_name, role, student_no, program, email, salt, password_hash)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
      `);
      for (const [id, username, name, role, studentNumber, program, email, password] of people) {
        const salt = randomBytes(16).toString('hex');
        const hash = scryptSync(password, salt, 64).toString('hex');
        insertPerson.run(id, username, name, role, studentNumber, program, email, salt, hash);
      }

      const insertCourse = db.prepare('INSERT INTO courses VALUES (?, ?, ?, ?)');
      for (const course of courses) insertCourse.run(...course);

      const insertEnrollment = db.prepare('INSERT INTO enrollments VALUES (?, ?, ?)');
      insertEnrollment.run('u-amina', 'CSC 214', 'Approved');
      insertEnrollment.run('u-amina', 'SEC 210', 'Pending');
      insertEnrollment.run('u-tobi', 'INF 208', 'Approved');
      insertEnrollment.run('u-zainab', 'SEC 210', 'Pending');
    }
    db.exec('COMMIT');
  } catch (error) {
    db.exec('ROLLBACK');
    db.close();
    throw error;
  }
  return db;
}
