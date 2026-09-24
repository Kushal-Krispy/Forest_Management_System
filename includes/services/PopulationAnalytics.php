<?php

require_once __DIR__ . '/../../config/database.php';

class PopulationAnalytics
{
    public static function recordSnapshot(int $animalId, int $userId): void
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT forest_id, population_estimate FROM animals WHERE animal_id = ?');
        $stmt->execute([$animalId]);
        $animal = $stmt->fetch();
        if (!$animal) {
            return;
        }

        $prev = $pdo->prepare(
            'SELECT population FROM animal_population_history WHERE animal_id = ? ORDER BY recorded_date DESC LIMIT 1'
        );
        $prev->execute([$animalId]);
        $prevPop = (int)($prev->fetchColumn() ?: $animal['population_estimate']);
        $current = (int)$animal['population_estimate'];
        $diff = $current - $prevPop;

        $birthRate = $diff > 0 ? round(($diff / max($prevPop, 1)) * 100, 2) : 0;
        $deathRate = $diff < 0 ? round((abs($diff) / max($prevPop, 1)) * 100, 2) : 0;

        $stmt = $pdo->prepare(
            'INSERT INTO animal_population_history (animal_id, forest_id, population, birth_rate, death_rate, recorded_date, recorded_by)
             VALUES (?, ?, ?, ?, ?, CURDATE(), ?)'
        );
        $stmt->execute([
            $animalId, $animal['forest_id'], $current, $birthRate, $deathRate, $userId,
        ]);
    }

    public static function getTrends(?int $animalId = null, string $period = 'monthly'): array
    {
        $pdo = getPDO();
        $groupFormat = match ($period) {
            'quarterly' => '%Y-Q%q',
            'annual' => '%Y',
            default => '%Y-%m',
        };

        $sql = "SELECT aph.animal_id, a.common_name, a.species_name,
                DATE_FORMAT(aph.recorded_date, ?) AS period,
                AVG(aph.population) AS avg_population,
                AVG(aph.birth_rate) AS avg_birth_rate,
                AVG(aph.death_rate) AS avg_death_rate,
                SUM(aph.migration_count) AS total_migration,
                SUM(aph.relocation_count) AS total_relocation
                FROM animal_population_history aph
                JOIN animals a ON aph.animal_id = a.animal_id
                WHERE 1=1";
        $params = [$groupFormat === '%Y-Q%q' ? '%Y-%m' : $groupFormat];

        if ($animalId) {
            $sql .= ' AND aph.animal_id = ?';
            $params[] = $animalId;
        }
        $sql .= ' GROUP BY aph.animal_id, a.common_name, a.species_name, period ORDER BY period';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function getForecast(int $animalId, int $months = 6): array
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            'SELECT recorded_date, population FROM animal_population_history
             WHERE animal_id = ? ORDER BY recorded_date ASC'
        );
        $stmt->execute([$animalId]);
        $history = $stmt->fetchAll();

        if (count($history) < 2) {
            return [];
        }

        $n = count($history);
        $sumX = $sumY = $sumXY = $sumX2 = 0;
        foreach ($history as $i => $row) {
            $x = $i;
            $y = (int)$row['population'];
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / max(1, ($n * $sumX2 - $sumX * $sumX));
        $intercept = ($sumY - $slope * $sumX) / $n;

        $forecast = [];
        for ($i = 0; $i < $months; $i++) {
            $x = $n + $i;
            $forecast[] = [
                'month' => $i + 1,
                'predicted_population' => max(0, round($intercept + $slope * $x)),
            ];
        }
        return $forecast;
    }

    public static function getGrowthPercentage(int $animalId): float
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            'SELECT population FROM animal_population_history WHERE animal_id = ?
             ORDER BY recorded_date ASC LIMIT 1'
        );
        $stmt->execute([$animalId]);
        $first = (int)($stmt->fetchColumn() ?: 0);

        $stmt = $pdo->prepare(
            'SELECT population FROM animal_population_history WHERE animal_id = ?
             ORDER BY recorded_date DESC LIMIT 1'
        );
        $stmt->execute([$animalId]);
        $last = (int)($stmt->fetchColumn() ?: 0);

        if ($first === 0) {
            return 0;
        }
        return round((($last - $first) / $first) * 100, 2);
    }

    public static function getForestBirthDeathCounts(int $forestId): array
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN event_type = 'birth' THEN event_count ELSE 0 END), 0) AS total_births,
                COALESCE(SUM(CASE WHEN event_type = 'death' THEN event_count ELSE 0 END), 0) AS total_deaths
             FROM animal_birth_death_events WHERE forest_id = ?"
        );
        $stmt->execute([$forestId]);
        $row = $stmt->fetch() ?: ['total_births' => 0, 'total_deaths' => 0];

        $popStmt = $pdo->prepare(
            'SELECT COALESCE(SUM(population_estimate), 0) FROM animals WHERE forest_id = ? AND deleted_at IS NULL'
        );
        $popStmt->execute([$forestId]);
        $currentPopulation = (int)$popStmt->fetchColumn();

        return [
            'forest_id' => $forestId,
            'current_population' => $currentPopulation,
            'total_births' => (int)$row['total_births'],
            'total_deaths' => (int)$row['total_deaths'],
        ];
    }
}
