const express = require('express');
const mysql = require('mysql2/promise');
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

// Database connection
const getConnection = async () => {
  return mysql.createConnection({
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'konekin',
    ssl: process.env.NODE_ENV === 'production' ? { rejectUnauthorized: false } : false
  });
};

// GET all users
app.get('/api/users', async (req, res) => {
  try {
    const connection = await getConnection();
    const [rows] = await connection.execute(
      'SELECT id, uuid, email, user_type, full_name, phone, avatar_url, is_verified, is_active, created_at FROM users WHERE is_active = 1'
    );
    await connection.end();
    
    res.json({
      status: 'success',
      data: rows,
      count: rows.length
    });
  } catch (error) {
    console.error('Database error:', error);
    res.status(500).json({
      status: 'error',
      message: 'Database operation failed',
      error: error.message
    });
  }
});

// GET user by ID
app.get('/api/users/:id', async (req, res) => {
  try {
    const connection = await getConnection();
    const [rows] = await connection.execute(
      'SELECT id, uuid, email, user_type, full_name, phone, avatar_url, is_verified, is_active, created_at FROM users WHERE id = ? AND is_active = 1',
      [req.params.id]
    );
    await connection.end();
    
    if (rows.length === 0) {
      return res.status(404).json({ 
        status: 'error',
        message: 'User not found' 
      });
    }
    
    res.json({ status: 'success', data: rows[0] });
  } catch (error) {
    res.status(500).json({ 
      status: 'error',
      message: 'Database operation failed',
      error: error.message 
    });
  }
});

// POST create user
app.post('/api/users', async (req, res) => {
  try {
    const { email, password, user_type, full_name, phone, avatar_url } = req.body;
    
    if (!email || !password || !user_type || !full_name) {
      return res.status(400).json({ 
        status: 'error',
        message: 'Missing required fields' 
      });
    }
    
    const connection = await getConnection();
    
    // Check if email exists
    const [existing] = await connection.execute(
      'SELECT id FROM users WHERE email = ?',
      [email]
    );
    
    if (existing.length > 0) {
      await connection.end();
      return res.status(409).json({ 
        status: 'error',
        message: 'Email already exists' 
      });
    }
    
    // Create user
    const [result] = await connection.execute(
      'INSERT INTO users (uuid, email, password_hash, user_type, full_name, phone, avatar_url) VALUES (UUID(), ?, ?, ?, ?, ?, ?)',
      [email, password, user_type, full_name, phone || null, avatar_url || null]
    );
    
    // Get created user
    const [rows] = await connection.execute(
      'SELECT id, uuid, email, user_type, full_name, phone, avatar_url, is_verified, is_active, created_at FROM users WHERE id = ?',
      [result.insertId]
    );
    
    await connection.end();
    
    res.status(201).json({
      status: 'success',
      message: 'User created successfully',
      data: rows[0]
    });
  } catch (error) {
    res.status(500).json({ 
      status: 'error',
      message: 'Database operation failed',
      error: error.message 
    });
  }
});

// DELETE user (soft delete)
app.delete('/api/users/:id', async (req, res) => {
  try {
    const connection = await getConnection();
    
    // Check if user exists and is active
    const [existing] = await connection.execute(
      'SELECT id FROM users WHERE id = ? AND is_active = 1',
      [req.params.id]
    );
    
    if (existing.length === 0) {
      await connection.end();
      return res.status(404).json({ 
        status: 'error',
        message: 'User not found or already deleted' 
      });
    }
    
    // Soft delete - set is_active to 0
    await connection.execute(
      'UPDATE users SET is_active = 0 WHERE id = ?',
      [req.params.id]
    );
    
    await connection.end();
    
    res.json({
      status: 'success',
      message: 'User deleted successfully'
    });
  } catch (error) {
    console.error('Delete user error:', error);
    res.status(500).json({ 
      status: 'error',
      message: 'Failed to delete user',
      error: error.message 
    });
  }
});

// DELETE user (permanent delete - optional)
app.delete('/api/users/:id/permanent', async (req, res) => {
  try {
    const connection = await getConnection();
    
    // Check if user exists
    const [existing] = await connection.execute(
      'SELECT id FROM users WHERE id = ?',
      [req.params.id]
    );
    
    if (existing.length === 0) {
      await connection.end();
      return res.status(404).json({ 
        status: 'error',
        message: 'User not found' 
      });
    }
    
    // Permanent delete
    await connection.execute(
      'DELETE FROM users WHERE id = ?',
      [req.params.id]
    );
    
    await connection.end();
    
    res.json({
      status: 'success',
      message: 'User permanently deleted successfully'
    });
  } catch (error) {
    console.error('Permanent delete user error:', error);
    res.status(500).json({ 
      status: 'error',
      message: 'Failed to permanently delete user',
      error: error.message 
    });
  }
});

// Default route
app.get('/', (req, res) => {
  res.json({ 
    message: 'Konekin API is running!',
    endpoints: {
      'GET /api/users': 'Get all users',
      'GET /api/users/:id': 'Get user by ID',
      'POST /api/users': 'Create new user',
      'DELETE /api/users/:id': 'Soft delete user',
      'DELETE /api/users/:id/permanent': 'Permanent delete user (use with caution)'
    }
  });
});

module.exports = app;