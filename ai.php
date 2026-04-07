<?php
// ai.php - Moteur IA autonome pour exécuter les tâches
require_once 'config.php';

/**
 * Exécute une tâche IA pour une startup
 */
function executeAITask($startup, $taskType, $prompt) {
    $systemPrompt = getSystemPrompt($taskType);
    
    $fullPrompt = buildFullPrompt($startup, $taskType, $prompt);
    
    $result = callMistralAI($fullPrompt, $systemPrompt);
    
    if (isset($result['error'])) {
        return ['error' => $result['error']];
    }
    
    $content = $result['choices'][0]['message']['content'] ?? '';
    
    // Traiter le résultat selon le type de tâche
    return processTaskResult($taskType, $content, $startup);
}

/**
 * Obtient le prompt système selon le type de tâche
 */
function getSystemPrompt($taskType) {
    $prompts = [
        'code' => "Tu es un développeur expert full-stack PHP/HTML/CSS/JavaScript. Tu génères du code propre, fonctionnel et bien structuré. Pour chaque demande, fournis le code complet prêt à l'emploi. Utilise toujours les meilleures pratiques.",
        
        'content' => "Tu es un rédacteur professionnel spécialisé dans le contenu web et marketing. Tu rédiges des textes engageants, optimisés SEO et adaptés au ton de la startup. Structure toujours avec des titres clairs.",
        
        'design' => "Tu es un designer UI/UX expert. Tu conçois des interfaces modernes, intuitives et esthétiques. Décris précisément les layouts, couleurs, typographies et composants. Fournis du code HTML/CSS quand c'est pertinent.",
        
        'database' => "Tu es un architecte de bases de données expert SQLite/MySQL. Tu conçois des schémas optimisés, normalisés et performants. Fournis toujours le SQL complet avec les CREATE TABLE, indexes et relations.",
        
        'legal' => "Tu es un juriste spécialisé en droit des startups et nouvelles technologies. Tu rédiges des documents juridiques professionnels : CGU, politique de confidentialité, contrats, mentions légales. Adapte au contexte français/européen.",
        
        'marketing' => "Tu es un expert en marketing digital et growth hacking. Tu élabores des stratégies concrètes, actionnables et mesurables. Inclue canaux, budgets estimés, KPIs et calendrier d'exécution.",
        
        'general' => "Tu es un assistant IA polyvalent expert en création de startups. Tu aides sur tous les aspects : technique, business, design, marketing. Tes réponses sont structurées, précises et actionnables."
    ];
    
    return $prompts[$taskType] ?? $prompts['general'];
}

/**
 * Construit le prompt complet avec le contexte de la startup
 */
function buildFullPrompt($startup, $taskType, $userPrompt) {
    $context = "
Contexte de la startup:
- Nom: {$startup['name']}
- Description: {$startup['description']}
- Catégorie: {$startup['category'] ?? 'Non définie'}
- Progression: {$startup['progress']}%

Tâche demandée: {$taskType}

Demande spécifique de l'utilisateur:
{$userPrompt}

Fournis une réponse complète, détaillée et directement utilisable.";

    return $context;
}

/**
 * Traite le résultat de l'IA selon le type de tâche
 */
function processTaskResult($taskType, $content, $startup) {
    $db = getMainDB();
    $result = ['success' => true, 'content' => $content];
    
    // Sauvegarder les fichiers générés si applicable
    if (in_array($taskType, ['code', 'design', 'database'])) {
        $files = extractFilesFromContent($content, $taskType);
        
        foreach ($files as $file) {
            $filepath = __DIR__ . '/startups/' . $startup['slug'] . '/' . $file['filename'];
            
            // Créer le dossier s'il n'existe pas
            $dir = dirname($filepath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            // Sauvegarder le fichier
            file_put_contents($filepath, $file['content']);
            
            // Enregistrer en base
            $stmt = $db->prepare("INSERT INTO generated_files (startup_id, filename, filepath, content_type, content) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $startup['id'],
                $file['filename'],
                $filepath,
                $file['type'],
                $file['content']
            ]);
            
            $result['files'][] = $file['filename'];
        }
    }
    
    // Mettre à jour la progression
    $progressIncrease = rand(5, 15);
    $newProgress = min(100, $startup['progress'] + $progressIncrease);
    $db->prepare("UPDATE startups SET progress = ? WHERE id = ?")
       ->execute([$newProgress, $startup['id']]);
    $result['new_progress'] = $newProgress;
    
    return $result;
}

/**
 * Extrait les fichiers du contenu généré par l'IA
 */
function extractFilesFromContent($content, $taskType) {
    $files = [];
    
    // Pattern pour détecter les blocs de code avec nom de fichier
    $pattern = '/```(\w+):([^\n]+)\n(.*?)```/s';
    preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $lang = $match[1];
        $filename = trim($match[2]);
        $fileContent = $match[3];
        
        $files[] = [
            'filename' => $filename,
            'content' => $fileContent,
            'type' => getFileType($lang, $filename)
        ];
    }
    
    // Si aucun fichier explicite, créer un fichier par défaut
    if (empty($files) && !empty(trim($content))) {
        $defaultFilename = getDefaultFilename($taskType);
        $files[] = [
            'filename' => $defaultFilename,
            'content' => $content,
            'type' => $taskType
        ];
    }
    
    return $files;
}

/**
 * Détermine le type de fichier selon le langage et le nom
 */
function getFileType($lang, $filename) {
    $extensions = [
        'php' => 'PHP',
        'html' => 'HTML',
        'css' => 'CSS',
        'js' => 'JavaScript',
        'sql' => 'SQL',
        'json' => 'JSON',
        'md' => 'Markdown',
        'txt' => 'Text'
    ];
    
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    return $extensions[$ext] ?? $extensions[$lang] ?? 'Unknown';
}

/**
 * Retourne un nom de fichier par défaut selon le type de tâche
 */
function getDefaultFilename($taskType) {
    $defaults = [
        'code' => 'index.php',
        'design' => 'interface.html',
        'database' => 'schema.sql',
        'content' => 'content.md',
        'legal' => 'document.md',
        'marketing' => 'strategy.md'
    ];
    
    return $defaults[$taskType] ?? 'output.txt';
}

/**
 * Boucle principale d'exécution automatique des tâches
 * À appeler via cron ou en background
 */
function runAutoTasks() {
    $db = getMainDB();
    
    // Récupérer toutes les startups actives
    $startups = $db->query("SELECT * FROM startups WHERE status = 'active' AND credits > 50")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($startups as $startup) {
        // Vérifier si des tâches sont en attente
        $pendingTasks = $db->prepare("SELECT * FROM ai_tasks WHERE startup_id = ? AND status = 'pending' LIMIT 3");
        $pendingTasks->execute([$startup['id']]);
        $tasks = $pendingTasks->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tasks as $task) {
            // Marquer comme en cours
            $db->prepare("UPDATE ai_tasks SET status = 'running' WHERE id = ?")
               ->execute([$task['id']]);
            
            // Exécuter la tâche
            $result = executeAITask($startup, $task['task_type'], $task['prompt']);
            
            // Mettre à jour le statut
            $status = isset($result['error']) ? 'failed' : 'completed';
            $db->prepare("UPDATE ai_tasks SET status = ?, result = ?, completed_at = CURRENT_TIMESTAMP WHERE id = ?")
               ->execute([
                   $status,
                   json_encode($result),
                   $task['id']
               ]);
        }
    }
}

// Si appelé directement en CLI, exécuter les tâches auto
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === '--auto') {
    runAutoTasks();
}
?>
