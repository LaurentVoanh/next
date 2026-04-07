<?php
// dashboard.php - Tableau de bord utilisateur pour sa startup
require_once 'config.php';
session_start();

$db = getMainDB();

// Récupérer la startup par son slug
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM startups WHERE slug = ?");
$stmt->execute([$slug]);
$startup = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$startup) {
    header('Location: index.php');
    exit;
}

// Mettre à jour les crédits quotidiens si nécessaire
$today = date('Y-m-d');
$lastReset = substr($startup['last_ai_run'] ?? '', 0, 10);
if ($lastReset !== $today) {
    $update = $db->prepare("UPDATE startups SET credits = 1000, last_ai_run = ? WHERE id = ?");
    $update->execute([$today . ' ' . date('H:i:s'), $startup['id']]);
    $startup['credits'] = 1000;
}

// Vérifier si l'utilisateur est connecté et propriétaire
$isOwner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $startup['user_id'];
if (!$isOwner && !isset($_SESSION['user_id'])) {
    // Visiteur non connecté - créditer le propriétaire
    $visitorIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $checkVisit = $db->prepare("SELECT id FROM visits WHERE startup_id = ? AND ip_address = ? AND DATE(visited_at) = ?");
    $checkVisit->execute([$startup['id'], $visitorIp, $today]);
    
    if (!$checkVisit->fetch()) {
        // Nouvelle visite unique aujourd'hui
        $db->prepare("INSERT INTO visits (startup_id, ip_address, credited) VALUES (?, ?, 1)")
           ->execute([$startup['id'], $visitorIp]);
        $db->prepare("UPDATE startups SET credits = credits + 1000 WHERE id = ?")
           ->execute([$startup['id']]);
        $startup['credits'] += 1000;
    }
}

// Récupérer les tâches en cours
$tasksStmt = $db->prepare("SELECT * FROM ai_tasks WHERE startup_id = ? ORDER BY created_at DESC LIMIT 20");
$tasksStmt->execute([$startup['id']]);
$tasks = $tasksStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les fichiers générés
$filesStmt = $db->prepare("SELECT * FROM generated_files WHERE startup_id = ? ORDER BY created_at DESC");
$filesStmt->execute([$startup['id']]);
$files = $filesStmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'run_ai_task':
                $taskType = $_POST['task_type'] ?? 'general';
                $prompt = $_POST['prompt'] ?? '';
                
                if ($startup['credits'] >= 10) {
                    // Déduire les crédits
                    $db->prepare("UPDATE startups SET credits = credits - 10 WHERE id = ?")
                       ->execute([$startup['id']]);
                    
                    // Créer la tâche
                    $insertTask = $db->prepare("INSERT INTO ai_tasks (startup_id, task_type, prompt, status) VALUES (?, ?, ?, 'pending')");
                    $insertTask->execute([$startup['id'], $taskType, $prompt]);
                    
                    // Exécuter la tâche IA immédiatement
                    require_once 'ai.php';
                    $aiResult = executeAITask($startup, $taskType, $prompt);
                    
                    // Mettre à jour la tâche
                    $taskId = $db->lastInsertId();
                    $updateTask = $db->prepare("UPDATE ai_tasks SET status = ?, result = ?, completed_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $updateTask->execute([
                        isset($aiResult['error']) ? 'failed' : 'completed',
                        json_encode($aiResult),
                        $taskId
                    ]);
                }
                break;
                
            case 'set_password':
                $password = $_POST['password'] ?? '';
                if (strlen($password) >= 6) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    // Créer ou mettre à jour l'utilisateur
                    if ($startup['user_id']) {
                        $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                           ->execute([$hash, $startup['user_id']]);
                    } else {
                        $db->prepare("INSERT INTO users (password_hash, startup_id, tokens, daily_credits) VALUES (?, ?, 1000, 1000)")
                           ->execute([$hash, $startup['id']]);
                        $startup['user_id'] = $db->lastInsertId();
                    }
                    $_SESSION['user_id'] = $startup['user_id'];
                }
                break;
        }
        
        header('Location: dashboard.php?slug=' . $slug);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($startup['name']) ?> - BOOM FORTUNE</title>
    <style>
        :root {
            --primary: #FF6B35;
            --secondary: #004E89;
            --accent: #FFA62B;
            --success: #4CAF50;
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
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
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
        .main-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        .panel {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .panel h2 {
            color: var(--secondary);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light);
        }
        .progress-bar {
            width: 100%;
            height: 30px;
            background: #E0E0E0;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            transition: width 0.5s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        .task-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .task-item {
            padding: 15px;
            background: var(--light);
            border-radius: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #ccc;
        }
        .task-item.completed { border-left-color: var(--success); }
        .task-item.pending { border-left-color: var(--accent); }
        .task-item.failed { border-left-color: #f44336; }
        .file-list {
            display: grid;
            gap: 10px;
        }
        .file-item {
            padding: 15px;
            background: var(--light);
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 107, 53, 0.4);
        }
        textarea {
            width: 100%;
            min-height: 100px;
            padding: 15px;
            border: 2px solid #E0E0E0;
            border-radius: 10px;
            font-size: 1rem;
            resize: vertical;
            font-family: inherit;
            margin-bottom: 15px;
        }
        textarea:focus {
            outline: none;
            border-color: var(--primary);
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
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #E0E0E0;
            border-radius: 10px;
            font-size: 1rem;
            margin-bottom: 15px;
        }
        @media (max-width: 900px) {
            .main-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🚀 <?= htmlspecialchars($startup['name']) ?></h1>
        <p><?= htmlspecialchars($startup['description']) ?></p>
    </div>
    
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $startup['credits'] ?></div>
                <div class="stat-label">Crédits IA</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($files) ?></div>
                <div class="stat-label">Fichiers générés</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($tasks) ?></div>
                <div class="stat-label">Tâches exécutées</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $startup['progress'] ?>%</div>
                <div class="stat-label">Progression</div>
            </div>
        </div>
        
        <div class="main-grid">
            <div class="panel">
                <h2>⚡ Lancer une tâche IA</h2>
                
                <?php if ($isOwner): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="run_ai_task">
                    
                    <div class="form-group">
                        <label>Type de tâche</label>
                        <select name="task_type">
                            <option value="code">Générer du code</option>
                            <option value="content">Rédiger du contenu</option>
                            <option value="design">Concevoir une interface</option>
                            <option value="database">Créer une base de données</option>
                            <option value="legal">Document juridique</option>
                            <option value="marketing">Stratégie marketing</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Votre demande</label>
                        <textarea 
                            name="prompt" 
                            placeholder="Décrivez ce que vous voulez que l'IA crée pour vous..."
                            required
                        ></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        🤖 Exécuter avec l'IA (-10 crédits)
                    </button>
                </form>
                <?php else: ?>
                <p style="color: #666; margin-bottom: 20px;">
                    Connectez-vous en tant que propriétaire pour lancer des tâches IA.
                </p>
                <form method="POST">
                    <input type="hidden" name="action" value="set_password">
                    <div class="form-group">
                        <label>Définir un mot de passe</label>
                        <input type="password" name="password" minlength="6" 
                               style="width:100%;padding:12px;border:2px solid #E0E0E0;border-radius:10px;"
                               placeholder="Mot de passe (min 6 caractères)">
                    </div>
                    <button type="submit" class="btn btn-primary">Devenir propriétaire</button>
                </form>
                <?php endif; ?>
                
                <div style="margin-top: 30px;">
                    <h3 style="margin-bottom: 15px; color: var(--secondary);">Progression globale</h3>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $startup['progress'] ?>%">
                            <?= $startup['progress'] ?>%
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="panel">
                <h2>📋 Tâches récentes</h2>
                <div class="task-list">
                    <?php if (empty($tasks)): ?>
                    <p style="color: #999; text-align: center; padding: 40px;">
                        Aucune tâche pour le moment
                    </p>
                    <?php else: ?>
                    <?php foreach ($tasks as $task): ?>
                    <div class="task-item <?= $task['status'] ?>">
                        <strong><?= htmlspecialchars($task['task_type']) ?></strong>
                        <p style="color: #666; margin: 5px 0; font-size: 0.9rem;">
                            <?= htmlspecialchars(substr($task['prompt'], 0, 100)) ?><?= strlen($task['prompt']) > 100 ? '...' : '' ?>
                        </p>
                        <small style="color: #999;"><?= $task['created_at'] ?></small>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="panel" style="margin-top: 30px;">
            <h2>📁 Fichiers générés</h2>
            <div class="file-list">
                <?php if (empty($files)): ?>
                <p style="color: #999; text-align: center; padding: 40px;">
                    Aucun fichier généré pour le moment
                </p>
                <?php else: ?>
                <?php foreach ($files as $file): ?>
                <div class="file-item">
                    <div>
                        <strong><?= htmlspecialchars($file['filename']) ?></strong>
                        <div style="color: #666; font-size: 0.9rem;"><?= $file['content_type'] ?></div>
                    </div>
                    <a href="view_file.php?id=<?= $file['id'] ?>" class="btn btn-primary" style="padding: 8px 15px; font-size: 0.9rem;">
                        Voir
                    </a>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh toutes les 30 secondes
        setInterval(() => location.reload(), 30000);
    </script>
</body>
</html>
