<?php

require_once __DIR__ . '/../../config/database.php';

class HealthScoreEngine
{
    public static function calculateForForest(int $forestId): array
    {
        $pdo = getPDO();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM animals WHERE forest_id = ? AND deleted_at IS NULL');
        $stmt->execute([$forestId]);
        $animalCount = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare(
            'SELECT AVG(population) FROM animal_population_history WHERE forest_id = ? AND recorded_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)'
        );
        $stmt->execute([$forestId]);
        $avgPop = (float)($stmt->fetchColumn() ?: 0);

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM incidents WHERE forest_id = ? AND status = 'approved'
             AND incident_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)"
        );
        $stmt->execute([$forestId]);
        $incidentCount = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM incidents WHERE forest_id = ? AND status = 'approved'
             AND (LOWER(category) LIKE '%fire%') AND incident_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)"
        );
        $stmt->execute([$forestId]);
        $fireCount = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT AVG(quality_index) FROM water_quality WHERE forest_id = ?');
        $stmt->execute([$forestId]);
        $waterQuality = (float)($stmt->fetchColumn() ?: 70);

        $stmt = $pdo->prepare('SELECT AVG(aqi) FROM air_quality WHERE forest_id = ?');
        $stmt->execute([$forestId]);
        $aqi = (float)($stmt->fetchColumn() ?: 50);

        $biodiversity = min(100, $animalCount * 20);
        $population = min(100, $avgPop > 0 ? 60 + ($avgPop / 50) : 40);
        $incident = max(0, 100 - ($incidentCount * 8));
        $fire = max(0, 100 - ($fireCount * 15));
        $environmental = min(100, max(0, ($waterQuality * 0.5) + ((150 - $aqi) * 0.5)));

        $healthScore = round(
            ($biodiversity * 0.25) + ($population * 0.20) + ($incident * 0.20) +
            ($fire * 0.15) + ($environmental * 0.20),
            2
        );

        $rating = match (true) {
            $healthScore >= 81 => 'excellent',
            $healthScore >= 61 => 'good',
            $healthScore >= 41 => 'fair',
            default => 'poor',
        };

        $stmt = $pdo->prepare(
            'INSERT INTO forest_health_scores (forest_id, health_score, biodiversity_score, population_score,
             incident_score, fire_score, environmental_score, rating)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $forestId, $healthScore, $biodiversity, $population,
            $incident, $fire, $environmental, $rating,
        ]);

        $pdo->prepare('UPDATE forests SET health_score = ? WHERE forest_id = ?')
            ->execute([$healthScore, $forestId]);

        return [
            'forest_id' => $forestId,
            'health_score' => $healthScore,
            'rating' => $rating,
            'components' => [
                'biodiversity' => $biodiversity,
                'population' => $population,
                'incidents' => $incident,
                'fire' => $fire,
                'environmental' => $environmental,
            ],
        ];
    }

    public static function calculateAll(): array
    {
        $pdo = getPDO();
        $forests = $pdo->query('SELECT forest_id, forest_name FROM forests WHERE deleted_at IS NULL')->fetchAll();
        $results = [];
        foreach ($forests as $f) {
            $score = self::calculateForForest((int)$f['forest_id']);
            $score['forest_name'] = $f['forest_name'];
            $results[] = $score;
        }
        usort($results, fn($a, $b) => $b['health_score'] <=> $a['health_score']);
        return $results;
    }

    public static function getRatingLabel(string $rating): string
    {
        return ucfirst($rating);
    }
}
