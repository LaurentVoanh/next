<?php
// index.php - Portail public BOOM FORTUNE
require_once 'config.php';

$db = getMainDB();

// Traitement du formulaire de création de startup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['startup_idea'])) {
    $idea = trim($_POST['startup_idea']);
    if (!empty($idea)) {
        // Démarrer le processus de création avec l'IA (5 étapes)
        session_start();
        $_SESSION['startup_idea'] = $idea;
        $_SESSION['step'] = 1;
        
        // Générer un nom temporaire
        $tempName = "Startup-" . substr(uniqid(), -8);
        $slug = generateSlug($tempName);
        
        // Créer la startup en base
        $stmt = $db->prepare("INSERT INTO startups (name, slug, description, status, progress) VALUES (?, ?, ?, 'pending', 10)");
        $stmt->execute([$tempName, $slug, $idea]);
        
        $_SESSION['startup_id'] = $db->lastInsertId();
        $_SESSION['startup_slug'] = $slug;
        
        header('Location: wizard.php?step=1');
        exit;
    }
}

// Récupérer les startups existantes
$startups = [];
try {
    $stmt = $db->query("SELECT * FROM startups WHERE status = 'active' ORDER BY created_at DESC LIMIT 50");
    $startups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Pas de startups encore
}

// Couleurs aléatoires pour le fond
$colors = [
    ['r' => 240, 'g' => 248, 'b' => 255], // AliceBlue
    ['r' => 250, 'g' => 250, 'b' => 210], // LightGoldenRod
    ['r' => 230, 'g' => 250, 'b' => 230], // HoneyDew
    ['r' => 245, 'g' => 245, 'b' => 245], // WhiteSmoke
    ['r' => 255, 'g' => 250, 'b' => 240], // FloralWhite
    ['r' => 240, 'g' => 255, 'b' => 240], // MintCream
];
$color = $colors[array_rand($colors)];
$bgColor = "rgb({$color['r']}, {$color['g']}, {$color['b']})";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BOOM FORTUNE - Start-up gratuite avec l'IA</title>
    <style>
        :root {
            --primary: #FF6B35;
            --secondary: #004E89;
            --accent: #FFA62B;
            --dark: #1A1A2E;
            --light: <?= $bgColor ?>;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--light);
            color: var(--dark);
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        header {
            text-align: center;
            padding: 60px 20px 40px;
        }
        h1 {
            font-size: 4rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
            letter-spacing: -2px;
        }
        .slogan {
            font-size: 1.5rem;
            color: var(--secondary);
            font-weight: 500;
        }
        nav {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 30px 0;
            padding: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        nav a {
            color: var(--dark);
            text-decoration: none;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        nav a:hover {
            background: var(--primary);
            color: white;
        }
        .main-form {
            max-width: 700px;
            margin: 40px auto;
            padding: 40px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .main-form h2 {
            text-align: center;
            margin-bottom: 30px;
            color: var(--secondary);
        }
        textarea {
            width: 100%;
            min-height: 150px;
            padding: 20px;
            border: 3px solid #E0E0E0;
            border-radius: 12px;
            font-size: 1.1rem;
            resize: vertical;
            transition: border-color 0.3s;
            font-family: inherit;
        }
        textarea:focus {
            outline: none;
            border-color: var(--primary);
        }
        .btn-create {
            width: 100%;
            padding: 18px;
            margin-top: 20px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.3rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-create:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(255, 107, 53, 0.4);
        }
        .features {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--secondary);
        }
        .features span {
            display: inline-block;
            margin: 0 15px;
            padding: 8px 16px;
            background: rgba(0, 78, 137, 0.1);
            border-radius: 20px;
        }
        .startups-list {
            margin-top: 60px;
            padding: 40px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        }
        .startups-list h3 {
            font-size: 2rem;
            margin-bottom: 30px;
            color: var(--secondary);
            text-align: center;
        }
        .category-filter {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .category-btn {
            padding: 10px 20px;
            border: 2px solid var(--secondary);
            background: white;
            color: var(--secondary);
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        .category-btn:hover, .category-btn.active {
            background: var(--secondary);
            color: white;
        }
        .startup-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .startup-card {
            padding: 25px;
            background: var(--light);
            border-radius: 15px;
            border-left: 5px solid var(--primary);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .startup-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .startup-card h4 {
            color: var(--secondary);
            margin-bottom: 10px;
            font-size: 1.3rem;
        }
        .startup-card p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        .startup-card .category {
            display: inline-block;
            padding: 5px 12px;
            background: var(--primary);
            color: white;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
            font-size: 1.2rem;
        }
        @media (max-width: 768px) {
            h1 { font-size: 2.5rem; }
            .slogan { font-size: 1.2rem; }
            nav { flex-direction: column; gap: 10px; }
            .main-form { padding: 25px; }
            .features span { display: block; margin: 10px 0; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🚀 BOOM FORTUNE</h1>
            <p class="slogan">Start-up gratuite avec l'IA</p>
        </header>
        
        <nav>
            <a href="index.php">Accueil</a>
            <a href="#create">Créer ma start-up</a>
            <a href="#startups">Voir les start-ups</a>
            <a href="admin.php">Administration</a>
        </nav>
        
        <section id="create" class="main-form">
            <h2>💡 Quelle est l'idée de votre start-up ?</h2>
            <form method="POST" action="">
                <textarea 
                    name="startup_idea" 
                    placeholder="Décrivez votre idée de start-up en quelques mots... L'IA se chargera de tout le reste !"
                    required
                ></textarea>
                <button type="submit" class="btn-create">✨ Créer avec l'IA</button>
            </form>
            
            <div class="features">
                <span>✓ C'est gratuit</span>
                <span>⚡ Rapide</span>
                <span>🎯 Immédiat</span>
                <span>😊 Simple</span>
                <span>∞ Illimité</span>
            </div>
        </section>
        
        <section id="startups" class="startups-list">
            <h3>🌟 Start-ups déjà créées</h3>
            
            <?php if (!empty($startups)): ?>
            <div class="category-filter">
                <button class="category-btn active" data-category="all">Toutes</button>
                <button class="category-btn" data-category="tech">Tech</button>
                <button class="category-btn" data-category="ecommerce">E-commerce</button>
                <button class="category-btn" data-category="saas">SaaS</button>
                <button class="category-btn" data-category="other">Autre</button>
            </div>
            
            <div class="startup-grid">
                <?php foreach ($startups as $startup): ?>
                <div class="startup-card" data-category="<?= htmlspecialchars($startup['category'] ?: 'other') ?>">
                    <h4><?= htmlspecialchars($startup['name']) ?></h4>
                    <p><?= htmlspecialchars(substr($startup['description'], 0, 150)) ?><?= strlen($startup['description']) > 150 ? '...' : '' ?></p>
                    <span class="category"><?= htmlspecialchars($startup['category'] ?: 'Autre') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <p>Aucune start-up créée pour le moment.<br>Soyez le premier à créer la vôtre ! 🚀</p>
            </div>
            <?php endif; ?>
        </section>
    </div>
    
    <script>
        // Filtrage par catégorie
        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const category = this.dataset.category;
                document.querySelectorAll('.startup-card').forEach(card => {
                    if (category === 'all' || card.dataset.category === category) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>
