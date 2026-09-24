<?php

require_once __DIR__ . '/../../config/database.php';

class FireRiskEngine
{
    public static function calculateForForest(int $forestId): array
    {
        $pdo = getPDO();

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM incidents WHERE forest_id = ? AND status = 'approved'
             AND (LOWER(category) LIKE '%fire%' OR LOWER(category) LIKE '%wildfire%')
             AND incident_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)"
        );
        $stmt->execute([$forestId]);
        $fireCount = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare(
            'SELECT temperature, humidity, rainfall FROM weather_data
             WHERE forest_id = ? ORDER BY recorded_date DESC LIMIT 1'
        );
        $stmt->execute([$forestId]);
        $weather = $stmt->fetch() ?: ['temperature' => 25, 'humidity' => 50, 'rainfall' => 10];

        $stmt = $pdo->prepare('SELECT vegetation_density FROM forests WHERE forest_id = ?');
        $stmt->execute([$forestId]);
        $vegetation = (float)($stmt->fetchColumn() ?: 50);

        $fireFactor = min(100, $fireCount * 15);
        $tempFactor = min(100, max(0, ((float)$weather['temperature'] - 15) * 4));
        $humidityFactor = min(100, max(0, 100 - (float)$weather['humidity']));
        $rainfallFactor = min(100, max(0, 50 - (float)$weather['rainfall'] * 2));
        $vegFactor = min(100, $vegetation * 0.8);

        $riskScore = round(
            ($fireFactor * 0.30) + ($tempFactor * 0.20) + ($humidityFactor * 0.20) +
            ($rainfallFactor * 0.15) + ($vegFactor * 0.15),
            2
        );

        $level = match (true) {
            $riskScore >= 75 => 'critical',
            $riskScore >= 50 => 'high',
            $riskScore >= 25 => 'moderate',
            default => 'low',
        };

        $recommendations = self::getRecommendations($level);

        $stmt = $pdo->prepare(
            'INSERT INTO fire_risk_scores (forest_id, risk_score, risk_level, fire_incidents_factor,
             temperature_factor, humidity_factor, rainfall_factor, vegetation_factor, recommendations)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $forestId, $riskScore, $level, $fireFactor, $tempFactor,
            $humidityFactor, $rainfallFactor, $vegFactor, $recommendations,
        ]);

        $pdo->prepare('UPDATE forests SET fire_risk_score = ? WHERE forest_id = ?')
            ->execute([$riskScore, $forestId]);

        if ($riskScore >= 50) {
            require_once __DIR__ . '/NotificationService.php';
            NotificationService::create(
                'High Fire Risk Alert',
                "Forest ID {$forestId} has {$level} fire risk ({$riskScore}%).",
                'fire_risk',
                null,
                'forests',
                $forestId
            );
        }

        return [
            'forest_id' => $forestId,
            'risk_score' => $riskScore,
            'risk_level' => $level,
            'factors' => compact('fireFactor', 'tempFactor', 'humidityFactor', 'rainfallFactor', 'vegFactor'),
            'recommendations' => $recommendations,
        ];
    }

    public static function calculateAll(): array
    {
        $pdo = getPDO();
        $forests = $pdo->query('SELECT forest_id FROM forests WHERE deleted_at IS NULL')->fetchAll();
        $results = [];
        foreach ($forests as $f) {
            $results[] = self::calculateForForest((int)$f['forest_id']);
        }
        usort($results, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);
        return $results;
    }

    private static function getRecommendations(string $level): string
    {
        return match ($level) {
            'critical' => 'Deploy emergency fire crews. Issue public evacuation advisory. Increase patrol frequency to 24/7.',
            'high' => 'Activate fire-watch stations. Restrict open flames. Pre-position firefighting equipment.',
            'moderate' => 'Monitor fire risk indicators daily. Conduct controlled burn assessments.',
            default => 'Maintain standard patrol schedule. Continue routine fire prevention education.',
        };
    }
}
