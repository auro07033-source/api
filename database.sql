CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE,
    password TEXT,
    email TEXT UNIQUE,
    role TEXT DEFAULT 'user', -- 'user', 'admin', 'reseller'
    balance REAL DEFAULT 0,
    active INTEGER DEFAULT 1,
    banned INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE,
    type TEXT, -- 'standart', 'vip', 'premium'
    duration_days INTEGER,
    limit_count INTEGER,
    used_count INTEGER DEFAULT 0,
    assigned_to INTEGER,
    reseller_id INTEGER,
    active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT,
    query TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);