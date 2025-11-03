const express = require('express');
const { db, initDB } = require('./database'); // SQLite database
const app = express();

app.use(express.json());

// CORS middleware
app.use((req, res, next) => {
  res.header('Access-Control-Allow-Origin', '*');
  res.header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
  res.header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
  if (req.method === 'OPTIONS') return res.status(200).end();
  next();
});

// Initialize database
initDB();

// GET all users (SQLite version)
app.get('/api/users', async (req, res) => {
  try {
    db.all(
      'SELECT id, uuid, email, user_type, full_name, phone, avatar_url, is_verified, is_active, created_at FROM users WHERE is_active = 1',
      (err, rows) => {
        if (err) {
          return res.status(500).json({ status: 'error', message: err.message });
        }
        
        res.json({
          status: 'success',
          data: rows,
          count: rows.length
        });
      }
    );
  } catch (error) {
    res.status(500).json({
      status: 'error',
      message: 'Database operation failed',
      error: error.message
    });
  }
});

// GET user by ID (SQLite version)
app.get('/api/users/:id', async (req, res) => {
  try {
    db.get(
      'SELECT id, uuid, email, user_type, full_name, phone, avatar_url, is_verified, is_active, created_at FROM users WHERE id = ? AND is_active = 1',
      [req.params.id],
      (err, row) => {
        if (err) {
          return res.status(500).json({ status: 'error', message: err.message });
        }
        
        if (!row) {
          return res.status(404).json({ 
            status: 'error',
            message: 'User not found' 
          });
        }
        
        res.json({ status: 'success', data: row });
      }
    );
  } catch (error) {
    res.status(500).json({ 
      status: 'error',
      message: 'Database operation failed',
      error: error.message 
    });
  }
});

// POST create user (SQLite version)
app.post('/api/users', async (req, res) => {
  try {
    const { email, password, user_type, full_name, phone, avatar_url } = req.body;
    
    if (!email || !password || !user_type || !full_name) {
      return res.status(400).json({ 
        status: 'error',
        message: 'Missing required fields' 
      });
    }
    
    // Check if email exists
    db.get(
      'SELECT id FROM users WHERE email = ?',
      [email],
      (err, existing) => {
        if (err) {
          return res.status(500).json({ status: 'error', message: err.message });
        }
        
        if (existing) {
          return res.status(409).json({ 
            status: 'error',
            message: 'Email already exists' 
          });
        }
        
        // Create user
        const uuid = 'uuid-' + Date.now();
        db.run(
          'INSERT INTO users (uuid, email, password_hash, user_type, full_name, phone, avatar_url) VALUES (?, ?, ?, ?, ?, ?, ?)',
          [uuid, email, password, user_type, full_name, phone || null, avatar_url || null],
          function(err) {
            if (err) {
              return res.status(500).json({ status: 'error', message: err.message });
            }
            
            // Get created user
            db.get(
              'SELECT id, uuid, email, user_type, full_name, phone, avatar_url, is_verified, is_active, created_at FROM users WHERE id = ?',
              [this.lastID],
              (err, newUser) => {
                if (err) {
                  return res.status(500).json({ status: 'error', message: err.message });
                }
                
                res.status(201).json({
                  status: 'success',
                  message: 'User created successfully',
                  data: newUser
                });
              }
            );
          }
        );
      }
    );
  } catch (error) {
    res.status(500).json({ 
      status: 'error',
      message: 'Database operation failed',
      error: error.message 
    });
  }
});

// Default route
app.get('/', (req, res) => {
  res.json({ 
    message: 'Konekin API is running with SQLite!',
    endpoints: {
      'GET /api/users': 'Get all users',
      'GET /api/users/:id': 'Get user by ID', 
      'POST /api/users': 'Create new user'
    }
  });
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`Server running on port ${PORT}`);
});