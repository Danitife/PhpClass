/**
 * db.js — Mongo connection + self-bootstrapping seed
 * ============================================================================
 * INTENTIONALLY INSECURE — training lab only.
 *
 * Mirrors the PHP app's database.php: connect, create collections if missing,
 * and seed four accounts (PLAINTEXT passwords) only if the users collection is
 * empty. Numeric `id` fields (1..4) are kept so you can "walk the ids" exactly
 * like the SQL version (?id=1, ?id=2, ...) for the IDOR exercises.
 *
 * Connection strategy (so the lab "just works"):
 *   1. Try MONGO_URL (default mongodb://127.0.0.1:27017) with a short timeout.
 *   2. If that fails, fall back to an in-memory MongoDB (mongodb-memory-server)
 *      when it is installed — zero external setup.
 *   3. Otherwise print how to start one with Docker and exit.
 */
import { MongoClient } from 'mongodb';
import { loadEnv } from './env.js';

const env = loadEnv();
const MONGO_URL = process.env.MONGO_URL || env.MONGO_URL || 'mongodb://127.0.0.1:27017';
const DB_NAME = process.env.DB_NAME || env.DB_NAME || 'vulnerable_bank';

const SEED = [
  { id: 1, username: 'admin',   full_name: 'System Administrator', email: 'admin@bank.local',   password: 'Admin@123',  role: 'admin', balance: 999999.99, account_number: '1000000001' },
  { id: 2, username: 'alice',   full_name: 'Alice Wonder',         email: 'alice@bank.local',   password: 'alice123',   role: 'user',  balance: 1000.00,   account_number: '1000000002' },
  { id: 3, username: 'bob',     full_name: 'Bob Builder',          email: 'bob@bank.local',     password: 'bob123',     role: 'user',  balance: 500.00,    account_number: '1000000003' },
  { id: 4, username: 'mallory', full_name: 'Mallory Malice',       email: 'mallory@bank.local', password: 'mallory123', role: 'user',  balance: 25.00,     account_number: '1000000004' },
];

async function tryConnect(url) {
  const client = new MongoClient(url, { serverSelectionTimeoutMS: 1500 });
  await client.connect();
  await client.db(DB_NAME).command({ ping: 1 });
  return client;
}

async function connect() {
  try {
    const client = await tryConnect(MONGO_URL);
    console.log(`✅ Connected to MongoDB at ${MONGO_URL}`);
    return client;
  } catch (e) {
    console.warn(`⚠️  Could not reach MongoDB at ${MONGO_URL} (${e.message}).`);
  }
  // Fallback: in-memory server, if the optional dependency is present.
  try {
    const { MongoMemoryServer } = await import('mongodb-memory-server');
    const mem = await MongoMemoryServer.create();
    const client = await tryConnect(mem.getUri());
    console.log('✅ Using in-memory MongoDB (mongodb-memory-server).');
    client.__mem = mem;
    return client;
  } catch {
    console.error(
      '\n❌ No MongoDB available.\n' +
      '   Start one with Docker:   npm run mongo:up   (docker compose up -d)\n' +
      '   or install the fallback: npm i mongodb-memory-server\n'
    );
    process.exit(1);
  }
}

const client = await connect();
const db = client.db(DB_NAME);

export const users = db.collection('users');
export const transactions = db.collection('transactions');

// Seed only if empty (self-bootstrap, like the PHP app).
if ((await users.countDocuments()) === 0) {
  await users.insertMany(SEED.map((u) => ({ ...u })));
  console.log('🌱 Seeded 4 accounts (admin / alice / bob / mallory).');
}

export { DB_NAME, MONGO_URL };
