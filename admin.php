<?php
// admin.php - Page d'administration pour gérer les clés API Mistral
require_once 'config.php';
session_start();

$db = getApiKeyDB();
$mainDb = getMainDB();

// Traitement des actions admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_key':
                $pseudo = trim($_POST['pseudo']);
                $apiKey = trim($_POST['api_key']);
                
                if (!empty($pseudo) && !empty($apiKey)) {
                    // Tester la clé
                    $isValid = testApiKey($apiKey) ? 1 : 0;
                    
                    $stmt = $db->prepare("INSERT INTO api_keys (pseudo, api_key, is_valid) VALUES (?, ?, ?)");
                    $stmt->execute([$pseudo, $apiKey, $isValid]);
                }
                break;
                
            case 'delete_key':
                $keyId = (int)$_POST['key_id'];
                $db->prepare("DELETE FROM api_keys WHERE id = ?")->execute([$keyId]);
                break;
                
            case 'test_all':
                $keys = $db->query("SELECT * FROM api_keys")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($keys as $key) {
                    $isValid = testApiKey($key['api_key']) ? 1 : 0;
                    $db->prepare("UPDATE api_keys SET is_valid = ? WHERE id = ?")
                       ->execute([$isValid, $key['id']]);
                }
                break;
        }
        
        header('Location: admin.php');
        exit;
    }
}

// Récupérer toutes les clés
$keys = $db->query("SELECT * FROM api_keys ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$totalKeys = count($keys);
$validKeys = count(array_filter($keys, fn($k) => $k['is_valid']));
$totalUsage = array_sum(array_column($keys, 'usage_count'));

// Fonction pour tester une clé API
function testApiKey($apiKey) {
    $url = 'https://api.mistral.ai/v1/chat/completions';
    $data = [
        'model' => 'mistral-small-latest',
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
        CURLOPT_TIMEOUT => 15
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200;
}

// Récupérer la config serveur
$serverConfig = [];
try {
    $pdo = new PDO('sqlite:' . __DIR__ . '/data/server.sqlite');
    $serverConfig = $pdo->query("SELECT * FROM server_config")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - BOOM FORTUNE</title>
    <style>
        :root {
            --primary: #FF6B35;
            --secondary: #004E89;
            --success: #4CAF50;
            --danger: #f44336;
            --dark: #1A1A2E;
            --light: #F8FAFC;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--light);
            color: var(--dark);
            min-height: 100vh;
        }
        .header {
            background: linear-gradient(135deg, var(--secondary), #002855);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        nav {
            text-align: center;
            margin-bottom: 30px;
        }
        nav a {
            display: inline-block;
            padding: 10px 20px;
            margin: 0 10px;
            background: white;
            color: var(--secondary);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        nav a:hover {
            background: var(--primary);
            color: white;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        .panel {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .panel h2 {
            color: var(--secondary);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--secondary);
        }
        input, select {
            width: 100%;
            padding: 12px;
            border: 2px solid #E0E0E0;
            border-radius: 10px;
            font-size: 1rem;
        }
        input:focus {
            outline: none;
            border-color: var(--primary);
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #FF8C5A);
            color: white;
        }
        .btn-success {
            background: var(--success);
            color: white;
        }
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #E0E0E0;
        }
        th {
            background: var(--light);
            color: var(--secondary);
            font-weight: 600;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-valid {
            background: rgba(76, 175, 80, 0.2);
            color: var(--success);
        }
        .status-invalid {
            background: rgba(244, 67, 54, 0.2);
            color: var(--danger);
        }
        .config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }
        .config-item {
            padding: 15px;
            background: var(--light);
            border-radius: 10px;
        }
        .config-key {
            font-weight: 600;
            color: var(--secondary);
            margin-bottom: 5px;
        }
        .config-value {
            color: #666;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚙️ Administration BOOM FORTUNE</h1>
        <p>Gestion des clés API et configuration</p>
    </div>
    
    <div class="container">
        <nav>
            <a href="index.php">🏠 Accueil</a>
            <a href="admin.php">⚙️ Admin</a>
            <a href="apikeymistral.php">🔑 Gestion Clés</a>
        </nav>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $totalKeys ?></div>
                <div class="stat-label">Clés totales</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--success);"><?= $validKeys ?></div>
                <div class="stat-label">Clés valides</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--secondary);"><?= $totalUsage ?></div>
                <div class="stat-label">Utilisations totales</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $totalKeys > 0 ? round($validKeys/$totalKeys*100) : 0 ?>%</div>
                <div class="stat-label">Taux de validité</div>
            </div>
        </div>
        
        <div class="panel">
            <h2>➕ Ajouter une clé API Mistral</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_key">
                <div style="display: grid; grid-template-columns: 1fr 2fr auto; gap: 15px;">
                    <div class="form-group" style="margin:0;">
                        <label for="pseudo">Pseudo</label>
                        <input type="text" name="pseudo" required placeholder="Votre pseudo">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label for="api_key">Clé API</label>
                        <input type="text" name="api_key" required placeholder="Clé API Mistral (32 caractères)">
                    </div>
                    <div class="form-group" style="margin:0; display: flex; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Ajouter</button>
                    </div>
                </div>
            </form>
            
            <form method="POST" style="margin-top: 20px;">
                <input type="hidden" name="action" value="test_all">
                <button type="submit" class="btn btn-success">🔄 Tester toutes les clés</button>
            </form>
        </div>
        
        <div class="panel">
            <h2>🔑 Clés API enregistrées</h2>
            <?php if (empty($keys)): ?>
            <p style="color: #999; text-align: center; padding: 40px;">
                Aucune clé API enregistrée
            </p>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Pseudo</th>
                        <th>Clé (masquée)</th>
                        <th>Statut</th>
                        <th>Utilisations</th>
                        <th>Taux succès</th>
                        <th>Dernière utilisation</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($keys as $key): ?>
                    <tr>
                        <td><?= $key['id'] ?></td>
                        <td><?= htmlspecialchars($key['pseudo']) ?></td>
                        <td><code><?= substr($key['api_key'], 0, 8) ?>...<?= substr($key['api_key'], -4) ?></code></td>
                        <td>
                            <span class="status-badge <?= $key['is_valid'] ? 'status-valid' : 'status-invalid' ?>">
                                <?= $key['is_valid'] ? '✓ Valide' : '✗ Invalide' ?>
                            </span>
                        </td>
                        <td><?= $key['usage_count'] ?></td>
                        <td><?= number_format($key['success_rate'], 1) ?>%</td>
                        <td><?= $key['last_used'] ?? 'Jamais' ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete_key">
                                <input type="hidden" name="key_id" value="<?= $key['id'] ?>">
                                <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 0.85rem;" 
                                        onclick="return confirm('Supprimer cette clé ?')">
                                    🗑️
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($serverConfig)): ?>
        <div class="panel">
            <h2>🖥️ Configuration du serveur</h2>
            <div class="config-grid">
                <?php foreach ($serverConfig as $config): ?>
                <div class="config-item">
                    <div class="config-key"><?= htmlspecialchars($config['config_key']) ?></div>
                    <div class="config-value"><?= htmlspecialchars($config['config_value']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
