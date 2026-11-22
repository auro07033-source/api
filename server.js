const express = require('express');
const cors = require('cors');
const sqlite3 = require('sqlite3').verbose();
const jwt = require('jsonwebtoken');
const bcrypt = require('bcryptjs');
const axios = require('axios');
const path = require('path');

const app = express();

app.use(cors({
    origin: ['https://nexuspanel.gt.tc', 'https://nexuspanel-t583.onrender.com', 'http://localhost:3000', 'http://127.0.0.1:5500']
}));
app.use(express.json());

const JWT_SECRET = 'nexus_super_secret_key_degistir_bunu_2026';
const REAL_API_BASE = 'https://api.ajaxsystems.fun/';
const REAL_API_KEY = 'fire';

const db = new sqlite3.Database('./nexus.db');

db.serialize(() => {
    db.run(`CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE, password TEXT, email TEXT UNIQUE, role TEXT DEFAULT 'user', balance REAL DEFAULT 0, active INTEGER DEFAULT 1, banned INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)`);
    db.run(`CREATE TABLE IF NOT EXISTS keys (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT UNIQUE, type TEXT, duration_days INTEGER, limit_count INTEGER, used_count INTEGER DEFAULT 0, assigned_to INTEGER, reseller_id INTEGER, active INTEGER DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)`);
    db.run(`CREATE TABLE IF NOT EXISTS logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, action TEXT, query TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)`);
    db.run(`CREATE TABLE IF NOT EXISTS vip (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT, level TEXT, end_at INTEGER, limits INTEGER DEFAULT 1000, used INTEGER DEFAULT 0, active INTEGER DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)`);
    db.run(`CREATE TABLE IF NOT EXISTS announcements (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, content TEXT, priority TEXT DEFAULT 'info', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)`);

    db.get(`SELECT * FROM users WHERE role = 'admin'`, (err, row) => {
        if(!row) {
            const hash = bcrypt.hashSync('nexus2026', 10);
            db.run(`INSERT INTO users (username, password, role) VALUES ('admin', ?, 'admin')`, [hash]);
            console.log('Varsayılan admin oluşturuldu: admin / nexus2026');
        }
    });
});

function auth(roles = []) {
    return (req, res, next) => {
        const token = req.headers.authorization?.split(' ')[1];
        if (!token) return res.status(401).json({ error: 'Yetkisiz erişim' });

        jwt.verify(token, JWT_SECRET, (err, decoded) => {
            if (err) return res.status(403).json({ error: 'Geçersiz token' });
            if (roles.length && !roles.includes(decoded.role)) {
                return res.status(403).json({ error: 'Bu işlem için yetkiniz yok' });
            }
            req.user = decoded;
            next();
        });
    };
}

app.get('/api/health', (req, res) => res.json({ status: 'ok', time: new Date() }));

app.post('/api/login', (req, res) => {
    const { username, password } = req.body;
    db.get(`SELECT * FROM users WHERE username = ?`, [username], (err, user) => {
        if (!user) return res.status(401).json({ error: 'Kullanıcı bulunamadı' });
        if (user.banned) return res.status(403).json({ error: 'Hesabınız banlanmış' });
        if (!user.active) return res.status(403).json({ error: 'Hesap devre dışı' });
        
        if (bcrypt.compareSync(password, user.password)) {
            const token = jwt.sign({ id: user.id, username: user.username, role: user.role }, JWT_SECRET, { expiresIn: '24h' });
            res.json({ token, role: user.role, username: user.username });
        } else {
            res.status(401).json({ error: 'Şifre yanlış' });
        }
    });
});

app.post('/api/register', (req, res) => {
    const { username, password, email } = req.body;
    if (!username || !password) return res.status(400).json({ error: 'Eksik bilgi' });
    
    const hash = bcrypt.hashSync(password, 10);
    db.run(`INSERT INTO users (username, password, email) VALUES (?, ?, ?)`, [username, hash, email || null], function(err) {
        if (err) return res.status(400).json({ error: 'Kullanıcı adı veya e-posta zaten var' });
        res.json({ success: true, message: 'Kayıt başarılı' });
    });
});

app.get('/api/me', auth(['user', 'admin', 'reseller']), (req, res) => {
    db.get(`SELECT id, username, email, role, balance, created_at FROM users WHERE id = ?`, [req.user.id], (err, user) => {
        if(err || !user) return res.status(404).json({ error: 'Kullanıcı bulunamadı' });
        res.json(user);
    });
});

app.post('/api/key/activate', auth(['user', 'admin']), (req, res) => {
    const { code } = req.body;
    const userId = req.user.id;
    
    db.get(`SELECT * FROM keys WHERE code = ? AND active = 1`, [code], (err, key) => {
        if (!key) return res.status(404).json({ error: 'Geçersiz key' });
        if (key.assigned_to && key.assigned_to !== userId) return res.status(400).json({ error: 'Bu key başkasına ait' });
        
        db.run(`UPDATE keys SET assigned_to = ? WHERE id = ?`, [userId, key.id], (err) => {
            if(err) return res.status(500).json({ error: 'Key atanamadı' });
            res.json({ success: true, message: 'Key aktifleştirildi' });
        });
    });
});

app.get('/api/query/:type', auth(['user', 'admin']), async (req, res) => {
    const { type } = req.params;
    const queryParams = req.query;
    const userId = req.user.id;

    db.get(`SELECT * FROM keys WHERE assigned_to = ? AND active = 1`, [userId], async (err, key) => {
        if (!key) return res.status(403).json({ error: 'Aktif anahtarınız yok' });
        
        const now = new Date();
        const created = new Date(key.created_at);
        const diffDays = Math.ceil((now - created) / (1000 * 60 * 60 * 24));
        if (key.duration_days > 0 && diffDays > key.duration_days) {
            return res.status(403).json({ error: 'Anahtar süresi dolmuş' });
        }

        if (key.limit_count > 0 && key.used_count >= key.limit_count) {
            return res.status(403).json({ error: 'Anahtar limiti aşıldı' });
        }

        try {
            let targetUrl = `${REAL_API_BASE}${type}.php?`;
            Object.keys(queryParams).forEach(k => {
                targetUrl += `${k}=${encodeURIComponent(queryParams[k])}&`;
            });
            if (type === 'gsmtc') targetUrl += `auth=${REAL_API_KEY}`;

            const response = await axios.get(targetUrl);
            
            db.run(`UPDATE keys SET used_count = used_count + 1 WHERE id = ?`, [key.id]);
            db.run(`INSERT INTO logs (user_id, action, query) VALUES (?, ?, ?)`, [userId, type, JSON.stringify(queryParams)]);

            res.json(response.data);
        } catch (error) {
            res.status(500).json({ error: 'API sunucusuna ulaşılamadı', detail: error.message });
        }
    });
});

app.get('/api/admin/users', auth(['admin']), (req, res) => {
    db.all(`SELECT id, username, email, role, balance, active, banned, created_at FROM users`, [], (err, rows) => {
        res.json(rows || []);
    });
});

app.post('/api/admin/users/:id/ban', auth(['admin']), (req, res) => {
    const { id } = req.params;
    const { banned } = req.body;
    db.run(`UPDATE users SET banned = ?, active = ? WHERE id = ?`, [banned ? 1 : 0, banned ? 0 : 1, id], (err) => {
        if(err) return res.status(500).json({ error: 'Güncellenemedi' });
        res.json({ success: true });
    });
});

app.post('/api/admin/users/:id/role', auth(['admin']), (req, res) => {
    const { id } = req.params;
    const { role } = req.body;
    db.run(`UPDATE users SET role = ? WHERE id = ?`, [role, id], (err) => {
        if(err) return res.status(500).json({ error: 'Güncellenemedi' });
        res.json({ success: true });
    });
});

app.delete('/api/admin/users/:id', auth(['admin']), (req, res) => {
    const { id } = req.params;
    db.run(`DELETE FROM users WHERE id = ?`, [id], (err) => {
        if(err) return res.status(500).json({ error: 'Silinemedi' });
        res.json({ success: true });
    });
});

app.get('/api/admin/keys', auth(['admin']), (req, res) => {
    db.all(`SELECT k.*, u.username as assigned_username, r.username as reseller_name FROM keys k LEFT JOIN users u ON k.assigned_to = u.id LEFT JOIN users r ON k.reseller_id = r.id`, [], (err, rows) => {
        res.json(rows || []);
    });
});

app.post('/api/admin/key_create', auth(['admin']), (req, res) => {
    const { code, type, duration_days, limit_count } = req.body;
    db.run(`INSERT INTO keys (code, type, duration_days, limit_count) VALUES (?, ?, ?, ?)`, 
    [code, type, duration_days, limit_count], function(err) {
        if(err) return res.status(400).json({error: 'Anahtar oluşturulamadı'});
        res.json({ success: true, id: this.lastID });
    });
});

app.post('/api/admin/keys/:id/toggle', auth(['admin']), (req, res) => {
    const { id } = req.params;
    db.get(`SELECT active FROM keys WHERE id = ?`, [id], (err, row) => {
        if(err || !row) return res.status(404).json({error:'Key yok'});
        const newActive = row.active ? 0 : 1;
        db.run(`UPDATE keys SET active = ? WHERE id = ?`, [newActive, id], (err) => {
            res.json({ success: true, active: newActive });
        });
    });
});

app.delete('/api/admin/keys/:id', auth(['admin']), (req, res) => {
    const { id } = req.params;
    db.run(`DELETE FROM keys WHERE id = ?`, [id], (err) => {
        if(err) return res.status(500).json({ error: 'Silinemedi' });
        res.json({ success: true });
    });
});

app.get('/api/admin/vip', auth(['admin']), (req, res) => {
    db.all(`SELECT * FROM vip`, [], (err, rows) => {
        res.json(rows || []);
    });
});

app.post('/api/admin/vip/create', auth(['admin']), (req, res) => {
    const { username, level, end_at, limits } = req.body;
    db.run(`INSERT INTO vip (username, level, end_at, limits) VALUES (?, ?, ?, ?)`, 
    [username, level, end_at, limits], function(err) {
        if(err) return res.status(400).json({error: 'VIP oluşturulamadı'});
        res.json({ success: true });
    });
});

app.delete('/api/admin/vip/:id', auth(['admin']), (req, res) => {
    const { id } = req.params;
    db.run(`DELETE FROM vip WHERE id = ?`, [id], (err) => {
        res.json({ success: true });
    });
});

app.get('/api/admin/announcements', auth(['admin']), (req, res) => {
    db.all(`SELECT * FROM announcements ORDER BY created_at DESC`, [], (err, rows) => {
        res.json(rows || []);
    });
});

app.post('/api/admin/announcements', auth(['admin']), (req, res) => {
    const { title, content, priority } = req.body;
    db.run(`INSERT INTO announcements (title, content, priority) VALUES (?, ?, ?)`, 
    [title, content, priority], function(err) {
        if(err) return res.status(400).json({error: 'Duyuru oluşturulamadı'});
        res.json({ success: true });
    });
});

app.delete('/api/admin/announcements/:id', auth(['admin']), (req, res) => {
    const { id } = req.params;
    db.run(`DELETE FROM announcements WHERE id = ?`, [id], (err) => {
        res.json({ success: true });
    });
});

app.get('/api/admin/logs', auth(['admin']), (req, res) => {
    db.all(`SELECT l.*, u.username FROM logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT 100`, [], (err, rows) => {
        res.json(rows || []);
    });
});

app.post('/api/reseller/create', auth(['admin']), (req, res) => {
    const { username, password, email, balance } = req.body;
    const hash = bcrypt.hashSync(password, 10);
    db.run(`INSERT INTO users (username, password, email, role, balance) VALUES (?, ?, ?, 'reseller', ?)`, 
    [username, hash, email || null, balance || 0], function(err) {
        if(err) return res.status(400).json({error: 'Bayi oluşturulamadı'});
        res.json({ success: true });
    });
});

app.get('/api/reseller/keys', auth(['reseller', 'admin']), (req, res) => {
    db.all(`SELECT k.*, u.username as assigned_username FROM keys k LEFT JOIN users u ON k.assigned_to = u.id WHERE k.reseller_id = ?`, [req.user.id], (err, rows) => {
        res.json(rows || []);
    });
});

app.post('/api/reseller/key_create', auth(['reseller', 'admin']), (req, res) => {
    const { code, type, duration_days, limit_count, price } = req.body;
    
    db.get(`SELECT balance FROM users WHERE id = ?`, [req.user.id], (err, user) => {
        if(err || !user) return res.status(404).json({error: 'Kullanıcı bulunamadı'});
        if(user.balance < price) return res.status(400).json({error: 'Yetersiz bakiye'});
        
        db.run(`INSERT INTO keys (code, type, duration_days, limit_count, reseller_id) VALUES (?, ?, ?, ?, ?)`, 
        [code, type, duration_days, limit_count, req.user.id], function(err) {
            if(err) return res.status(400).json({error: 'Anahtar oluşturulamadı'});
            db.run(`UPDATE users SET balance = balance - ? WHERE id = ?`, [price, req.user.id]);
            res.json({ success: true });
        });
    });
});

app.get('/api/announcements/latest', (req, res) => {
    db.get(`SELECT * FROM announcements ORDER BY created_at DESC LIMIT 1`, [], (err, row) => {
        res.json(row || null);
    });
});

app.use(express.static(path.join(__dirname, 'public')));

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`[NEXUS BACKEND] Sunucu http://localhost:${PORT} adresinde çalışıyor.`);
});