<?php
// wizard.php - Guide de création en 5 étapes avec l'IA
require_once 'config.php';
session_start();

if (!isset($_SESSION['startup_id']) || !isset($_SESSION['startup_idea'])) {
    header('Location: index.php');
    exit;
}

$db = getMainDB();
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$step = max(1, min(5, $step));
$_SESSION['step'] = $step;

$startupId = $_SESSION['startup_id'];
$idea = $_SESSION['startup_idea'];

// Récupérer les données de la startup
$stmt = $db->prepare("SELECT * FROM startups WHERE id = ?");
$stmt->execute([$startupId]);
$startup = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$startup) {
    header('Location: index.php');
    exit;
}

// Traitement des soumissions de chaque étape
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step_response'])) {
        $response = trim($_POST['step_response']);
        $_SESSION["step_{$step}_response"] = $response;
        
        // Passer à l'étape suivante
        if ($step < 5) {
            header('Location: wizard.php?step=' . ($step + 1));
        } else {
            // Fin du wizard, création finale
            header('Location: dashboard.php?slug=' . $startup['slug']);
        }
        exit;
    }
    
    if (isset($_POST['ai_suggestion'])) {
        // Utiliser l'IA pour générer des suggestions
        $suggestions = generateAISuggestions($step, $idea, $_SESSION);
        echo json_encode(['success' => true, 'suggestions' => $suggestions]);
        exit;
    }
}

// Titres et descriptions des étapes
$steps = [
    1 => [
        'title' => '🎯 Définir votre objectif principal',
        'description' => 'Quel est le problème principal que votre start-up va résoudre ?',
        'ai_prompt' => "Basé sur cette idée de startup: '{$idea}', propose 3 objectifs principaux clairs et précis que cette startup pourrait viser. Réponds sous forme de liste JSON."
    ],
    2 => [
        'title' => '👥 Identifier votre cible',
        'description' => 'Qui sont vos clients idéaux ? Décrivez-les précisément.',
        'ai_prompt' => "Pour une startup avec cette idée: '{$idea}', identifie 3 personas clients types avec leurs caractéristiques démographiques et besoins. Réponds sous forme de liste JSON."
    ],
    3 => [
        'title' => '💰 Modèle économique',
        'description' => 'Comment allez-vous gagner de l\'argent ?',
        'ai_prompt' => "Propose 3 modèles économiques pertinents pour une startup avec cette idée: '{$idea}'. Explique brièvement chaque modèle. Réponds sous forme de liste JSON."
    ],
    4 => [
        'title' => '🚀 Fonctionnalités principales',
        'description' => 'Quelles sont les fonctionnalités essentielles de votre produit ?',
        'ai_prompt' => "Liste 5 fonctionnalités principales essentielles pour un MVP d'une startup avec cette idée: '{$idea}'. Classe-les par ordre de priorité. Réponds sous forme de liste JSON."
    ],
    5 => [
        'title' => '📈 Stratégie de lancement',
        'description' => 'Comment allez-vous lancer et promouvoir votre startup ?',
        'ai_prompt' => "Propose 3 stratégies de lancement concrètes pour une startup avec cette idée: '{$idea}'. Inclue canaux de marketing et actions spécifiques. Réponds sous forme de liste JSON."
    ]
];

$currentStep = $steps[$step];

// Fonction pour générer des suggestions IA
function generateAISuggestions($stepNum, $idea, $session) {
    $prompt = $GLOBALS['steps'][$stepNum]['ai_prompt'];
    
    $result = callMistralAI($prompt, "Tu es un expert en création de startups. Réponds toujours avec un format JSON valide.");
    
    if (isset($result['choices'][0]['message']['content'])) {
        $content = $result['choices'][0]['message']['content'];
        // Extraire le JSON de la réponse
        preg_match('/\{.*\}|\[.*\]/s', $content, $matches);
        if (!empty($matches[0])) {
            return json_decode($matches[0], true) ?? [];
        }
    }
    return [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Étape <?= $step ?>/5 - BOOM FORTUNE</title>
    <style>
        :root {
            --primary: #FF6B35;
            --secondary: #004E89;
            --accent: #FFA62B;
            --dark: #1A1A2E;
            --light: #F8FAFC;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .wizard-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 800px;
            width: 100%;
            padding: 50px;
        }
        .progress-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }
        .progress-bar::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 4px;
            background: #E0E0E0;
            transform: translateY(-50%);
            z-index: 0;
        }
        .step-indicator {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #E0E0E0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #999;
            z-index: 1;
            transition: all 0.3s;
        }
        .step-indicator.active {
            background: var(--primary);
            color: white;
            transform: scale(1.2);
        }
        .step-indicator.completed {
            background: #4CAF50;
            color: white;
        }
        h1 {
            color: var(--dark);
            margin-bottom: 15px;
            font-size: 2rem;
        }
        .description {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .suggestions {
            display: grid;
            gap: 15px;
            margin-bottom: 30px;
        }
        .suggestion-card {
            padding: 20px;
            background: var(--light);
            border: 2px solid transparent;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .suggestion-card:hover {
            border-color: var(--primary);
            background: rgba(255, 107, 53, 0.05);
        }
        .suggestion-card.selected {
            border-color: var(--primary);
            background: rgba(255, 107, 53, 0.1);
        }
        textarea {
            width: 100%;
            min-height: 120px;
            padding: 15px;
            border: 2px solid #E0E0E0;
            border-radius: 12px;
            font-size: 1rem;
            resize: vertical;
            font-family: inherit;
            margin-bottom: 20px;
        }
        textarea:focus {
            outline: none;
            border-color: var(--primary);
        }
        .btn-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
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
        .btn-secondary {
            background: #f0f0f0;
            color: var(--dark);
        }
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        .btn-ai {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="wizard-container">
        <div class="progress-bar">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <div class="step-indicator <?= $i === $step ? 'active' : '' ?> <?= $i < $step ? 'completed' : '' ?>">
                <?= $i < $step ? '✓' : $i ?>
            </div>
            <?php endfor; ?>
        </div>
        
        <h1><?= htmlspecialchars($currentStep['title']) ?></h1>
        <p class="description"><?= htmlspecialchars($currentStep['description']) ?></p>
        
        <form method="POST" id="wizardForm">
            <div id="suggestionsContainer" class="suggestions hidden">
                <!-- Les suggestions IA seront injectées ici -->
            </div>
            
            <textarea 
                name="step_response" 
                placeholder="Décrivez votre réponse ou sélectionnez une suggestion ci-dessus..."
                required
                id="responseText"
            ></textarea>
            
            <div class="btn-group">
                <button type="button" class="btn btn-ai" onclick="loadAISuggestions()">
                    ✨ Obtenir des suggestions IA
                </button>
                <button type="submit" class="btn btn-primary">
                    <?= $step < 5 ? 'Étape suivante →' : '🚀 Créer ma start-up !' ?>
                </button>
            </div>
        </form>
    </div>
    
    <script>
        let isLoading = false;
        
        async function loadAISuggestions() {
            if (isLoading) return;
            isLoading = true;
            
            const btn = document.querySelector('.btn-ai');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="loading"></span>Chargement...';
            btn.disabled = true;
            
            try {
                const formData = new FormData();
                formData.append('ai_suggestion', '1');
                
                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success && data.suggestions.length > 0) {
                    const container = document.getElementById('suggestionsContainer');
                    container.classList.remove('hidden');
                    
                    container.innerHTML = data.suggestions.map((s, i) => `
                        <div class="suggestion-card" onclick="selectSuggestion(this)">
                            ${typeof s === 'string' ? s : JSON.stringify(s)}
                        </div>
                    `).join('');
                }
            } catch (err) {
                console.error('Error loading suggestions:', err);
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
                isLoading = false;
            }
        }
        
        function selectSuggestion(card) {
            document.querySelectorAll('.suggestion-card').forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            document.getElementById('responseText').value = card.textContent.trim();
        }
    </script>
</body>
</html>
