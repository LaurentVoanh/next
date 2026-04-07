<?php
// config.php - Configuration et initialisation des bases de données SQLite
define('DB_DIR', __DIR__ . '/data');
define('MAIN_DB', DB_DIR . '/main.sqlite');
define('APIKEY_DB', DB_DIR . '/apikeymistral.sqlite');
define('SERVER_DB', DB_DIR . '/server.sqlite');

// Créer le dossier data s'il n'existe pas
if (!is_dir(DB_DIR)) {
    mkdir(DB_DIR, 0755, true);
}

// Fonction pour obtenir la connexion à la base principale
function getMainDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO('sqlite:' . MAIN_DB);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            initMainTables($pdo);
        } catch (PDOException $e) {
            error_log("Main DB Error: " . $e->getMessage());
            return null;
        }
    }
    return $pdo;
}

// Fonction pour obtenir la connexion à la base des clés API
function getApiKeyDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO('sqlite:' . APIKEY_DB);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            initApiKeyTables($pdo);
        } catch (PDOException $e) {
            error_log("API Key DB Error: " . $e->getMessage());
            return null;
        }
    }
    return $pdo;
}

// Initialiser les tables principales
function initMainTables($pdo) {
    // Table des startups
    $pdo->exec("CREATE TABLE IF NOT EXISTS startups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        slug TEXT UNIQUE NOT NULL,
        description TEXT,
        category TEXT,
        user_id INTEGER,
        status TEXT DEFAULT 'active',
        progress INTEGER DEFAULT 0,
        credits INTEGER DEFAULT 1000,
        last_ai_run DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Table des utilisateurs
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        email TEXT,
        password_hash TEXT,
        startup_id INTEGER,
        tokens INTEGER DEFAULT 1000,
        daily_credits INTEGER DEFAULT 1000,
        last_credit_reset DATE,
        ip_address TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(startup_id) REFERENCES startups(id)
    )");
    
    // Table des tâches IA
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        startup_id INTEGER,
        task_type TEXT,
        status TEXT DEFAULT 'pending',
        prompt TEXT,
        result TEXT,
        error TEXT,
        retries INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME,
        FOREIGN KEY(startup_id) REFERENCES startups(id)
    )");
    
    // Table des fichiers générés
    $pdo->exec("CREATE TABLE IF NOT EXISTS generated_files (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        startup_id INTEGER,
        filename TEXT,
        filepath TEXT,
        content_type TEXT,
        content TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(startup_id) REFERENCES startups(id)
    )");
    
    // Table des visites (pour les crédits bonus)
    $pdo->exec("CREATE TABLE IF NOT EXISTS visits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        startup_id INTEGER,
        ip_address TEXT,
        visited_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        credited INTEGER DEFAULT 0,
        FOREIGN KEY(startup_id) REFERENCES startups(id)
    )");
}

// Initialiser la table des clés API
function initApiKeyTables($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS api_keys (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        pseudo TEXT NOT NULL,
        api_key TEXT NOT NULL UNIQUE,
        is_valid INTEGER DEFAULT 0,
        usage_count INTEGER DEFAULT 0,
        last_used DATETIME,
        success_rate REAL DEFAULT 100.0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
}

// Vérifier et enregistrer la configuration du serveur (une seule fois)
function checkServerConfig() {
    $dbFile = SERVER_DB;
    $shouldInit = !file_exists($dbFile);
    
    if (!$shouldInit) {
        try {
            $pdo = new PDO('sqlite:' . $dbFile);
            $result = $pdo->query("SELECT COUNT(*) FROM server_config")->fetchColumn();
            $shouldInit = ($result == 0);
        } catch (Exception $e) {
            $shouldInit = true;
        }
    }
    
    if ($shouldInit) {
        try {
            $pdo = new PDO('sqlite:' . $dbFile);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $pdo->exec("CREATE TABLE IF NOT EXISTS server_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                config_key TEXT UNIQUE,
                config_value TEXT,
                checked_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            
            // Récupérer toutes les infos du serveur
            $config = [
                'php_version' => phpversion(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'curl_enabled' => extension_loaded('curl') ? '1' : '0',
                'sqlite_enabled' => extension_loaded('sqlite3') ? '1' : '0',
                'openssl_enabled' => extension_loaded('openssl') ? '1' : '0',
                'json_enabled' => extension_loaded('json') ? '1' : '0',
                'mbstring_enabled' => extension_loaded('mbstring') ? '1' : '0',
                'allow_url_fopen' => ini_get('allow_url_fopen'),
                'display_errors' => ini_get('display_errors'),
                'error_reporting' => error_reporting(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI',
                'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? __DIR__,
                'script_path' => __FILE__,
                'os_type' => PHP_OS,
                'timezone' => date_default_timezone_get(),
            ];
            
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO server_config (config_key, config_value) VALUES (?, ?)");
            foreach ($config as $key => $value) {
                $stmt->execute([$key, is_array($value) ? json_encode($value) : $value]);
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Server Config Error: " . $e->getMessage());
            return false;
        }
    }
    return true;
}

// Exécuter la vérification du serveur
checkServerConfig();

// Fonction utilitaire pour générer un slug
function generateSlug($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9-]/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-') . '-' . substr(uniqid(), -6);
}

// Fonction pour obtenir une clé API Mistral (système de rotation intelligente)
function getMistralApiKey() {
    $db = getApiKeyDB();
    if (!$db) return null;
    
    // Sélectionner les clés valides triées par taux de succès et nombre d'utilisations
    $stmt = $db->query("
        SELECT id, api_key, usage_count, success_rate 
        FROM api_keys 
        WHERE is_valid = 1 
        ORDER BY success_rate DESC, usage_count ASC 
        LIMIT 10
    ");
    $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($keys)) return null;
    
    // Choisir une clé aléatoirement parmi les meilleures
    $selectedKey = $keys[array_rand($keys)];
    
    // Incrémenter le compteur d'utilisation
    $update = $db->prepare("UPDATE api_keys SET usage_count = usage_count + 1, last_used = CURRENT_TIMESTAMP WHERE id = ?");
    $update->execute([$selectedKey['id']]);
    
    return $selectedKey['api_key'];
}

// Fonction pour appeler l'API Mistral
function callMistralAI($prompt, $systemPrompt = '', $model = 'mistral-large-latest') {
    $apiKey = getMistralApiKey();
    if (!$apiKey) {
        return ['error' => 'No valid API key available'];
    }
    
    $url = 'https://api.mistral.ai/v1/chat/completions';
    $messages = [];
    
    if ($systemPrompt) {
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];
    }
    $messages[] = ['role' => 'user', 'content' => $prompt];
    
    $data = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 4096
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
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        // Essayer avec une autre clé en cas d'échec
        if ($httpCode === 401 || $httpCode === 429) {
            $apiKey = getMistralApiKey();
            if ($apiKey) {
                return callMistralAI($prompt, $systemPrompt, $model);
            }
        }
        return ['error' => "HTTP $httpCode: $error", 'response' => $response];
    }
    
    return json_decode($response, true);
}
?>
