const sqlite3 = require('sqlite3').verbose();
const path = require('path');

const dbPath = path.join(__dirname, 'konekin.db');
const db = new sqlite3.Database(dbPath);

// Initialize database with your schema
const initDB = () => {
  db.serialize(() => {
    // Create users table based on your MySQL schema
    db.run(`CREATE TABLE IF NOT EXISTS users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      email TEXT NOT NULL UNIQUE,
      password_hash TEXT NOT NULL,
      user_type TEXT CHECK(user_type IN ('creative', 'umkm')) NOT NULL,
      full_name TEXT NOT NULL,
      phone TEXT,
      avatar_url TEXT,
      is_verified BOOLEAN DEFAULT 0,
      is_active BOOLEAN DEFAULT 1,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )`);

    // Insert sample data
    db.run(`INSERT OR IGNORE INTO users (uuid, email, password_hash, user_type, full_name) 
            VALUES 
            ('uuid-1', 'creative@example.com', 'hashed123', 'creative', 'Creative User'),
            ('uuid-2', 'umkm@example.com', 'hashed456', 'umkm', 'UMKM User')`);
  });
};

module.exports = { db, initDB };