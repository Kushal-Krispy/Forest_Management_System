<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/ExportService.php';

class ReportEngine
{
    public static function listForests(?int $forestId = null): array
    {
        $pdo = getPDO();
        if ($forestId) {
            $stmt = $pdo->prepare(
                'SELECT forest_name, forest_code, location, latitude, longitude, area_sq_km, ecosystem_type
                 FROM forests WHERE forest_id = ? AND deleted_at IS NULL'
            );
            $stmt->execute([$forestId]);
            return $stmt->fetchAll();
        }
        return $pdo->query(
            'SELECT forest_name, forest_code, location, latitude, longitude, area_sq_km, ecosystem_type
             FROM forests WHERE deleted_at IS NULL ORDER BY forest_name'
        )->fetchAll();
    }

    public static function listAnimals(?int $forestId = null): array
    {
        $pdo = getPDO();
        $sql = 'SELECT a.animal_id, a.common_name, a.species_name, a.population_estimate, a.conservation_status
                FROM animals a WHERE a.deleted_at IS NULL';
        if ($forestId) {
            $sql .= ' AND a.forest_id = ' . (int)$forestId;
        }
        $sql .= ' ORDER BY a.common_name';
        return $pdo->query($sql)->fetchAll();
    }

    public static function animalPopulation(?int $forestId = null): array
    {
        $pdo = getPDO();
        $sql = 'SELECT a.animal_id, a.common_name, a.population_estimate AS current_population
                FROM animals a WHERE a.deleted_at IS NULL';
        if ($forestId) {
            $sql .= ' AND a.forest_id = ' . (int)$forestId;
        }
        $sql .= ' ORDER BY a.common_name';
        return $pdo->query($sql)->fetchAll();
    }

    public static function listIncidents(?int $forestId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $pdo = getPDO();
        $sql = "SELECT i.incident_id, i.category AS incident_type, i.incident_date, i.created_at AS reported_date,
                       u.full_name AS reported_by
                FROM incidents i
                JOIN users u ON i.reported_by = u.user_id
                WHERE i.status = 'approved' AND i.deleted_at IS NULL";
        $params = [];
        if ($forestId) {
            $sql .= ' AND i.forest_id = ?';
            $params[] = $forestId;
        }
        if ($dateFrom) {
            $sql .= ' AND i.incident_date >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $sql .= ' AND i.incident_date <= ?';
            $params[] = $dateTo;
        }
        $sql .= ' ORDER BY i.incident_date DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function singleIncident(int $incidentId, ?int $forestId = null): ?array
    {
        $pdo = getPDO();
        $params = [$incidentId];
        $stmt = $pdo->prepare(
            "SELECT i.incident_id, i.category AS incident_category, i.incident_date, i.image_path,
                    i.description, i.workflow_status AS current_status,
                    r.full_name AS reported_by, v.full_name AS verified_by
             FROM incidents i
             LEFT JOIN users r ON i.reported_by = r.user_id
             LEFT JOIN users v ON i.approved_by = v.user_id
             WHERE i.incident_id = ? AND i.status = 'approved' AND i.deleted_at IS NULL"
             . ($forestId ? ' AND i.forest_id = ?' : '')
        );
        if ($forestId) {
            $params[] = $forestId;
        }
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    public static function listUsers(): array
    {
        $pdo = getPDO();
        return $pdo->query(
            'SELECT user_id, username, full_name, email, role, is_active, created_at
             FROM users WHERE deleted_at IS NULL ORDER BY username'
        )->fetchAll();
    }

    public static function birthDeathReport(?int $forestId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $pdo = getPDO();
        $sql = 'SELECT f.forest_name, a.common_name AS animal_name, e.event_type, e.event_count,
                       e.cause_of_death, e.recorded_at
                FROM animal_birth_death_events e
                JOIN animals a ON e.animal_id = a.animal_id
                JOIN forests f ON e.forest_id = f.forest_id
                WHERE 1=1';
        $params = [];
        if ($forestId) {
            $sql .= ' AND e.forest_id = ?';
            $params[] = $forestId;
        }
        if ($dateFrom) {
            $sql .= ' AND DATE(e.recorded_at) >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $sql .= ' AND DATE(e.recorded_at) <= ?';
            $params[] = $dateTo;
        }
        $sql .= ' ORDER BY f.forest_name, e.recorded_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function generateReport(string $category, ?int $forestId, ?int $incidentId, ?string $dateFrom, ?string $dateTo): array
    {
        return match ($category) {
            'forests' => [
                'title' => 'List of Forests',
                'headers' => ['Name', 'Forest Code', 'Location', 'Latitude', 'Longitude', 'Area (sq km)', 'Type of Forest'],
                'rows' => array_map(fn($r) => [
                    $r['forest_name'], $r['forest_code'] ?? '', $r['location'],
                    $r['latitude'] ?? '', $r['longitude'] ?? '', $r['area_sq_km'], $r['ecosystem_type'],
                ], self::listForests($forestId)),
                'grouped' => false,
            ],
            'animals' => [
                'title' => 'List of Animals',
                'headers' => ['Animal ID', 'Common Name', 'Scientific Name', 'Population', 'Conservation Status'],
                'rows' => array_map(fn($r) => [
                    $r['animal_id'], $r['common_name'], $r['species_name'],
                    $r['population_estimate'], $r['conservation_status'],
                ], self::listAnimals($forestId)),
                'grouped' => false,
            ],
            'animal_population' => [
                'title' => 'Animal Population',
                'headers' => ['Animal ID', 'Common Name', 'Current Population'],
                'rows' => array_map(fn($r) => [
                    $r['animal_id'], $r['common_name'], $r['current_population'],
                ], self::animalPopulation($forestId)),
                'grouped' => false,
            ],
            'incidents' => [
                'title' => 'List of Incidents',
                'headers' => ['Incident ID', 'Incident Type', 'Incident Date', 'Reported Date', 'Reported By'],
                'rows' => array_map(fn($r) => [
                    $r['incident_id'], $r['incident_type'], $r['incident_date'],
                    $r['reported_date'], $r['reported_by'],
                ], self::listIncidents($forestId, $dateFrom, $dateTo)),
                'grouped' => false,
            ],
            'single_incident' => self::buildSingleIncidentReport($incidentId, $forestId),
            'users' => [
                'title' => 'Users List',
                'headers' => ['User ID', 'Username', 'Full Name', 'Email', 'Role', 'Active', 'Created At'],
                'rows' => array_map(fn($r) => [
                    $r['user_id'], $r['username'], $r['full_name'], $r['email'],
                    $r['role'], $r['is_active'] ? 'Yes' : 'No', $r['created_at'],
                ], self::listUsers()),
                'grouped' => false,
            ],
            'birth_death' => self::buildBirthDeathGroupedReport($forestId, $dateFrom, $dateTo),
            default => ['title' => 'Unknown Report', 'headers' => [], 'rows' => [], 'grouped' => false],
        };
    }

    private static function buildSingleIncidentReport(?int $incidentId, ?int $forestId): array
    {
        $incident = $incidentId ? self::singleIncident($incidentId, $forestId) : null;
        return [
            'title' => 'Single Incident Report',
            'incident' => $incident,
            'grouped' => false,
            'is_single_incident' => true,
        ];
    }

    private static function buildBirthDeathGroupedReport(?int $forestId, ?string $dateFrom, ?string $dateTo): array
    {
        $data = self::birthDeathReport($forestId, $dateFrom, $dateTo);
        $grouped = [];
        foreach ($data as $row) {
            $forest = $row['forest_name'];
            if (!isset($grouped[$forest])) {
                $grouped[$forest] = [];
            }
            $grouped[$forest][] = [
                $row['animal_name'],
                $row['event_type'] === 'birth' ? $row['event_count'] : 0,
                $row['event_type'] === 'death' ? $row['event_count'] : 0,
                $row['recorded_at'],
            ];
        }
        return [
            'title' => 'Birth & Death Report',
            'headers' => ['Animal Name', 'No. of Birth', 'No. of Death', 'Date and Time'],
            'grouped_data' => $grouped,
            'grouped' => true,
        ];
    }

    public static function exportReport(array $report, string $format, string $filename): void
    {
        if (!empty($report['is_single_incident'])) {
            if ($format === 'csv' && !empty($report['incident'])) {
                $i = $report['incident'];
                ExportService::exportCsv($filename, ['Field', 'Value'], [
                    ['Incident ID', $i['incident_id']],
                    ['Incident Category', $i['incident_category']],
                    ['Date of Incident', $i['incident_date']],
                    ['Description', $i['description']],
                    ['Reported By', $i['reported_by'] ?? 'N/A'],
                    ['Verified By', $i['verified_by'] ?? 'N/A'],
                    ['Current Status', $i['current_status']],
                ]);
            } else {
                ExportService::exportSingleIncidentPdf($report['incident'], $report['title']);
            }
            return;
        }

        if (!empty($report['grouped']) && !empty($report['grouped_data'])) {
            if ($format === 'csv') {
                ExportService::exportGroupedCsv($filename, $report['headers'], $report['grouped_data']);
            } else {
                ExportService::exportGroupedPdf($report['title'], $report['headers'], $report['grouped_data']);
            }
            return;
        }

        if ($format === 'csv') {
            ExportService::exportCsv($filename, $report['headers'], $report['rows'] ?? []);
        } else {
            ExportService::exportPdf($report['title'], $report['headers'], $report['rows'] ?? []);
        }
    }
}
