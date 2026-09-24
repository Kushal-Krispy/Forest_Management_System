<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/services/PopulationAnalytics.php';
require_once __DIR__ . '/../includes/services/FireRiskEngine.php';
require_once __DIR__ . '/../includes/services/HealthScoreEngine.php';

requireApiLogin();
$action = $_GET['action'] ?? 'dashboard';
$pdo = getPDO();

switch ($action) {
    case 'population':
        $animalId = isset($_GET['animal_id']) ? (int)$_GET['animal_id'] : null;
        $period = $_GET['period'] ?? 'monthly';
        jsonResponse([
            'trends' => PopulationAnalytics::getTrends($animalId, $period),
            'forecast' => $animalId ? PopulationAnalytics::getForecast($animalId) : [],
            'growth' => $animalId ? PopulationAnalytics::getGrowthPercentage($animalId) : 0,
        ]);

    case 'forest_population':
        $forestId = (int)($_GET['forest_id'] ?? 0);
        if ($forestId < 1) {
            jsonResponse(['error' => 'forest_id required'], 400);
        }
        jsonResponse(PopulationAnalytics::getForestBirthDeathCounts($forestId));

    case 'fire_risk':
        $forestId = isset($_GET['forest_id']) ? (int)$_GET['forest_id'] : null;
        if ($forestId) {
            jsonResponse(FireRiskEngine::calculateForForest($forestId));
        }
        jsonResponse(['forests' => FireRiskEngine::calculateAll()]);

    case 'health':
        $forestId = isset($_GET['forest_id']) ? (int)$_GET['forest_id'] : null;
        if ($forestId) {
            jsonResponse(HealthScoreEngine::calculateForForest($forestId));
        }
        jsonResponse(['forests' => HealthScoreEngine::calculateAll()]);

    case 'dashboard':
        $stats = [
            'forests' => (int)$pdo->query('SELECT COUNT(*) FROM forests WHERE deleted_at IS NULL')->fetchColumn(),
            'animals' => (int)$pdo->query('SELECT COUNT(*) FROM animals WHERE deleted_at IS NULL')->fetchColumn(),
            'incidents' => (int)$pdo->query("SELECT COUNT(*) FROM incidents WHERE status='approved'")->fetchColumn(),
            'pending' => (int)$pdo->query("SELECT COUNT(*) FROM pending_incidents WHERE status='pending'")->fetchColumn(),
            'critical_incidents' => (int)$pdo->query("SELECT COUNT(*) FROM incidents WHERE severity='critical' AND workflow_status != 'closed'")->fetchColumn(),
        ];

        $incidentsByCategory = $pdo->query(
            "SELECT category, COUNT(*) AS count FROM incidents WHERE status='approved' GROUP BY category ORDER BY count DESC"
        )->fetchAll();

        $recentNotifications = $pdo->prepare(
            'SELECT title, message, type, created_at FROM notifications
             WHERE user_id = ? OR user_id IS NULL ORDER BY created_at DESC LIMIT 5'
        );
        $recentNotifications->execute([getSessionUserId()]);

        jsonResponse([
            'stats' => $stats,
            'incidents_by_category' => $incidentsByCategory,
            'notifications' => $recentNotifications->fetchAll(),
        ]);

    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}
