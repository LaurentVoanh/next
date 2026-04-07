<?php
// index.php - Mistral Key Manager
// Design épuré, couleurs Pantone, validation AJAX

define('DB_FILE', __DIR__ . '/apikeymistral.sqlite');

// Initialisation SQLite
function initDB() {
    try {
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE IF NOT EXISTS APIKEYMISTRAL (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pseudo TEXT NOT NULL,
            api_key TEXT NOT NULL UNIQUE,
            is_valid INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        return $pdo;
    } catch (PDOException $e) {
        error_log("DB Error: " . $e->getMessage());
        return null;
    }
}

// Masquage clé : 5qaRTjWUgfgfdgfd5ZbH8Rake → 5qaRTj••••••••••••••••Rake
function maskApiKey($key) {
    $len = strlen($key);
    if ($len < 12) return str_repeat('•', $len);
    return substr($key, 0, 6) . str_repeat('•', $len - 10) . substr($key, -4);
}

// Validation API Mistral
function validateMistralKey($apiKey) {
    $url = 'https://api.mistral.ai/v1/chat/completions';
    $data = [
        'model' => 'pixtral-12b-2409',
        'messages' => [['role' => 'user', 'content' => 'ping']]
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200;
}

// Traitement AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $pdo = initDB();
    
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit;
    }
    
    // Ajout clé API
    if ($_POST['action'] === 'add_key') {
        $pseudo = trim($_POST['pseudo'] ?? '');
        $apiKey = trim($_POST['api_key'] ?? '');
        
        if (empty($pseudo) || empty($apiKey)) {
            echo json_encode(['success' => false, 'error' => 'Champs requis']);
            exit;
        }
        
        // Format clé : 32 caractères alphanumériques
        if (!preg_match('/^[A-Za-z0-9]{32}$/', $apiKey)) {
            echo json_encode(['success' => false, 'error' => 'Format clé invalide (32 caractères alphanumériques)']);
            exit;
        }
        
        // Vérifier doublon
        $stmt = $pdo->prepare("SELECT id FROM APIKEYMISTRAL WHERE api_key = ?");
        $stmt->execute([$apiKey]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Clé déjà enregistrée']);
            exit;
        }
        
        // Test API
        $isValid = validateMistralKey($apiKey) ? 1 : 0;
        
        // Insertion
        $stmt = $pdo->prepare("INSERT INTO APIKEYMISTRAL (pseudo, api_key, is_valid) VALUES (?, ?, ?)");
        $success = $stmt->execute([$pseudo, $apiKey, $isValid]);
        
        echo json_encode([
            'success' => $success,
            'valid' => $isValid,
            'message' => $isValid ? 'Clé valide ✓' : 'Clé invalide ✗'
        ]);
        exit;
    }
    
    // Stats
    if ($_POST['action'] === 'get_stats') {
        $total = $pdo->query("SELECT COUNT(*) FROM APIKEYMISTRAL")->fetchColumn();
        $validKeys = $pdo->query("SELECT pseudo, api_key FROM APIKEYMISTRAL WHERE is_valid = 1 ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
        
        $formatted = array_map(fn($k) => [
            'pseudo' => htmlspecialchars($k['pseudo']),
            'key' => maskApiKey($k['api_key'])
        ], $validKeys);
        
        echo json_encode(['total' => (int)$total, 'keys' => $formatted]);
        exit;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mistral Key Manager</title>
    <style>
        :root {
            --pantone-blue: #00A8E1;
            --pantone-coral: #FF6B6B;
            --pantone-cream: #FFF9F0;
            --pantone-charcoal: #2D3142;
            --pantone-mint: #4ECDC4;
            --shadow: 0 8px 30px rgba(0,0,0,0.08);
            --radius: 16px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, var(--pantone-cream) 0%, #F7F9FC 100%);
            color: var(--pantone-charcoal);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            width: 100%;
            max-width: 520px;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 40px 35px;
        }
        .header {
            text-align: center;
            margin-bottom: 35px;
        }
        .header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--pantone-charcoal);
            margin-bottom: 8px;
        }
        .header p {
            color: #6B7280;
            font-size: 0.95rem;
        }
        .form-group {
            margin-bottom: 22px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--pantone-charcoal);
        }
        input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: #FAFAFA;
            font-family: inherit;
        }
        input:focus {
            outline: none;
            border-color: var(--pantone-blue);
            box-shadow: 0 0 0 4px rgba(0, 168, 225, 0.1);
            background: white;
        }
        input::placeholder { color: #9CA3AF; }
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--pantone-blue), #0088C7);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.1s, box-shadow 0.2s;
            margin-top: 8px;
        }
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 25px rgba(0, 168, 225, 0.3);
        }
        .btn:active { transform: translateY(0); }
        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        .message {
            margin-top: 15px;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.9rem;
            display: none;
        }
        .message.success {
            background: rgba(78, 205, 196, 0.15);
            color: #065F46;
            border: 1px solid var(--pantone-mint);
        }
        .message.error {
            background: rgba(255, 107, 107, 0.15);
            color: #991B1B;
            border: 1px solid var(--pantone-coral);
        }
        .stats {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #E5E7EB;
        }
        .stats-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }
        .stats-total {
            font-size: 2rem;
            font-weight: 700;
            color: var(--pantone-blue);
        }
        .stats-label {
            color: #6B7280;
            font-size: 0.9rem;
        }
        .keys-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 280px;
            overflow-y: auto;
            padding-right: 5px;
        }
        .keys-list::-webkit-scrollbar { width: 6px; }
        .keys-list::-webkit-scrollbar-thumb {
            background: #D1D5DB;
            border-radius: 3px;
        }
        .key-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: #FAFAFA;
            border-radius: 10px;
            font-size: 0.9rem;
            border-left: 3px solid var(--pantone-mint);
        }
        .key-pseudo {
            font-weight: 500;
            color: var(--pantone-charcoal);
        }
        .key-masked {
            font-family: 'SF Mono', 'Fira Code', monospace;
            color: #6B7280;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        .empty-state {
            text-align: center;
            color: #9CA3AF;
            padding: 20px;
            font-size: 0.9rem;
        }
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .hint {
            font-size: 0.8rem;
            color: #9CA3AF;
            margin-top: 6px;
        }
        @media (max-width: 600px) {
            .container { padding: 30px 25px; }
            .header h1 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔑 Mistral Key Manager</h1>
            <p>Validez et gérez vos clés API Mistral AI</p>
        </div>
        
        <form id="keyForm">
            <div class="form-group">
                <label for="pseudo">Votre pseudo</label>
                <input type="text" id="pseudo" name="pseudo" required 
                       placeholder="ex: dev_algeria" maxlength="50" autocomplete="username">
            </div>
            
            <div class="form-group">
                <label for="api_key">Clé API Mistral</label>
                <input type="password" id="api_key" name="api_key" required 
                       placeholder="5qaRgfgfgfgfdEP5ZbH8Rake" 
                       pattern="[A-Za-z0-9]{32}" maxlength="32" 
                       title="32 caractères alphanumériques" autocomplete="off">
                <div class="hint">Format : 32 caractères alphanumériques (ex: 5qaRTjggfdP5ZbH8Rake)</div>
            </div>
            
            <button type="submit" class="btn" id="submitBtn">
                Valider la clé
            </button>
            
            <div id="message" class="message"></div>
        </form>
        
        <div class="stats">
            <div class="stats-header">
                <div>
                    <div class="stats-total" id="totalCount">0</div>
                    <div class="stats-label">clés enregistrées</div>
                </div>
            </div>
            
            <h3 style="margin-bottom: 15px; font-size: 1rem; color: var(--pantone-charcoal);">
                Clés validées ✓
            </h3>
            <div id="keysList" class="keys-list">
                <div class="empty-state">Aucune clé validée pour le moment</div>
            </div>
        </div>
    </div>

    <script>
        let isLoading = false;
        document.addEventListener('DOMContentLoaded', loadStats);
        
        document.getElementById('keyForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            if (isLoading) return;
            
            const pseudo = document.getElementById('pseudo').value.trim();
            const apiKey = document.getElementById('api_key').value.trim();
            const btn = document.getElementById('submitBtn');
            const msg = document.getElementById('message');
            
            isLoading = true;
            btn.disabled = true;
            btn.innerHTML = '<span class="loading"></span>Validation...';
            msg.style.display = 'none';
            
            try {
                const formData = new FormData();
                formData.append('action', 'add_key');
                formData.append('pseudo', pseudo);
                formData.append('api_key', apiKey);
                
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();
                
                msg.textContent = data.message || (data.success ? 'Enregistré !' : 'Erreur');
                msg.className = 'message ' + (data.success ? 'success' : 'error');
                msg.style.display = 'block';
                
                if (data.success) {
                    document.getElementById('api_key').value = '';
                    loadStats();
                }
            } catch (err) {
                msg.textContent = 'Erreur de connexion';
                msg.className = 'message error';
                msg.style.display = 'block';
            } finally {
                isLoading = false;
                btn.disabled = false;
                btn.textContent = 'Valider la clé';
            }
        });
        
        async function loadStats() {
            try {
                const formData = new FormData();
                formData.append('action', 'get_stats');
                
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();
                
                document.getElementById('totalCount').textContent = data.total;
                
                const listEl = document.getElementById('keysList');
                if (data.keys && data.keys.length > 0) {
                    listEl.innerHTML = data.keys.map(k => 
                        `<div class="key-item">
                            <span class="key-pseudo">@${escapeHtml(k.pseudo)}</span>
                            <span class="key-masked">${escapeHtml(k.key)}</span>
                        </div>`
                    ).join('');
                } else {
                    listEl.innerHTML = '<div class="empty-state">Aucune clé validée pour le moment</div>';
                }
            } catch (err) {
                console.error('Stats load error:', err);
            }
        }
        
        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
        
        setInterval(loadStats, 30000);
    </script>
</body>
</html>