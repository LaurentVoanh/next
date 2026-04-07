<?php
// view_file.php - Afficher un fichier généré
require_once 'config.php';

$fileId = $_GET['id'] ?? 0;
if (!$fileId) {
    header('Location: index.php');
    exit;
}

$db = getMainDB();
$stmt = $db->prepare("SELECT * FROM generated_files WHERE id = ?");
$stmt->execute([$fileId]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    header('Location: index.php');
    exit;
}

// Récupérer la startup associée
$startupStmt = $db->prepare("SELECT * FROM startups WHERE id = ?");
$startupStmt->execute([$file['startup_id']]);
$startup = $startupStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($file['filename']) ?> - BOOM FORTUNE</title>
    <style>
        :root {
            --primary: #FF6B35;
            --secondary: #004E89;
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
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        .nav-link {
            display: inline-block;
            padding: 10px 20px;
            background: white;
            color: var(--secondary);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .file-info {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .file-content {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        pre {
            margin: 0;
            padding: 30px;
            overflow-x: auto;
            background: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 14px;
            line-height: 1.6;
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #FF8C5A);
            color: white;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📄 <?= htmlspecialchars($file['filename']) ?></h1>
        <p style="opacity: 0.9; margin-top: 5px;">
            <?= htmlspecialchars($startup['name']) ?> • <?= htmlspecialchars($file['content_type']) ?>
        </p>
    </div>
    
    <div class="container">
        <a href="dashboard.php?slug=<?= htmlspecialchars($startup['slug']) ?>" class="nav-link">
            ← Retour au dashboard
        </a>
        
        <div class="file-info">
            <strong>Fichier:</strong> <?= htmlspecialchars($file['filename']) ?><br>
            <strong>Type:</strong> <?= htmlspecialchars($file['content_type']) ?><br>
            <strong>Créé le:</strong> <?= $file['created_at'] ?>
        </div>
        
        <div class="file-content">
            <pre><code><?= htmlspecialchars($file['content']) ?></code></pre>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="data/startups/<?= htmlspecialchars($startup['slug']) ?>/<?= htmlspecialchars($file['filename']) ?>" 
               class="btn btn-primary" download>
                ⬇️ Télécharger le fichier
            </a>
        </div>
    </div>
</body>
</html>
