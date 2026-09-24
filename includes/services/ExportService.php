<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../audit_tracker.php';

class ExportService
{
    public static function exportCsv(string $filename, array $headers, array $rows): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Smart Forest Monitoring Platform v2.0']);
        fputcsv($out, ['Generated: ' . date('Y-m-d H:i:s')]);
        fputcsv($out, []);
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fputcsv($out, []);
        fputcsv($out, ['--- End of Report ---']);
        fclose($out);
        logActivity('export', null, null, 'CSV: ' . $filename);
        exit;
    }

    public static function exportExcel(string $filename, array $headers, array $rows): void
    {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<?mso-application progid="Excel.Sheet"?>';
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
              xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        echo '<Worksheet ss:Name="Report"><Table>';

        echo '<Row><Cell><Data ss:Type="String">Smart Forest Monitoring Platform v2.0</Data></Cell></Row>';
        echo '<Row><Cell><Data ss:Type="String">Generated: ' . date('Y-m-d H:i:s') . '</Data></Cell></Row>';
        echo '<Row></Row>';

        echo '<Row>';
        foreach ($headers as $h) {
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars($h) . '</Data></Cell>';
        }
        echo '</Row>';

        foreach ($rows as $row) {
            echo '<Row>';
            foreach ($row as $cell) {
                $type = is_numeric($cell) ? 'Number' : 'String';
                echo '<Cell><Data ss:Type="' . $type . '">' . htmlspecialchars((string)$cell) . '</Data></Cell>';
            }
            echo '</Row>';
        }

        echo '</Table></Worksheet></Workbook>';
        logActivity('export', null, null, 'Excel: ' . $filename);
        exit;
    }

    public static function exportPdf(string $title, array $headers, array $rows, ?string $summary = null): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $title . '.pdf"');
        header('Cache-Control: no-cache');
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        @page { margin: 2cm; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
        .header { border-bottom: 3px solid #2d6a4f; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { color: #2d6a4f; margin: 0; font-size: 18px; }
        .header p { margin: 4px 0; color: #666; }
        .summary { background: #f0f7f4; padding: 12px; border-radius: 6px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #2d6a4f; color: white; padding: 8px; text-align: left; }
        td { padding: 6px 8px; border-bottom: 1px solid #ddd; }
        tr:nth-child(even) { background: #f9f9f9; }
        .footer { margin-top: 30px; border-top: 1px solid #ccc; padding-top: 10px; font-size: 9px; color: #999; }
        .watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%) rotate(-45deg);
                      font-size: 60px; color: rgba(45,106,79,0.08); z-index: -1; }
    </style>
</head>
<body>
    <div class="watermark">FMS 2.0</div>
    <div class="header">
        <h1>🌲 ' . htmlspecialchars($title) . '</h1>
        <p>Smart Forest Monitoring & Wildlife Management Platform</p>
        <p>Generated: ' . date('F j, Y H:i:s') . ' | Classification: Official Use</p>
    </div>';
        
        if ($summary) {
            $html .= '<div class="summary"><strong>Executive Summary:</strong><br>' . nl2br(htmlspecialchars($summary)) . '</div>';
        }
        
        $html .= '<table>
        <thead><tr>';
        foreach ($headers as $h) {
            $html .= '<th>' . htmlspecialchars($h) . '</th>';
        }
        $html .= '</tr></thead>
        <tbody>';
        
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . htmlspecialchars((string)$cell) . '</td>';
            }
            $html .= '</tr>';
        }
        
        $html .= '</tbody>
    </table>
    <div class="footer">
        Smart Forest Monitoring Platform v2.0 | Page 1 | ' . date('Y-m-d H:i:s') . '
        <br>This document is generated from live database records.
    </div>
</body>
</html>';
        
        // Use wkhtmltopdf or similar if available, otherwise fallback to HTML
        // For now, we'll use a simple approach with print dialog
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $title . '.html"');
        echo $html;
        logActivity('export', null, null, 'PDF: ' . $title);
        exit;
    }

    public static function exportGroupedCsv(string $filename, array $headers, array $groupedData): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Smart Forest Monitoring Platform v2.0']);
        fputcsv($out, ['Generated: ' . date('Y-m-d H:i:s')]);
        fputcsv($out, []);
        foreach ($groupedData as $forestName => $rows) {
            fputcsv($out, [$forestName]);
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fputcsv($out, []);
        }
        fclose($out);
        logActivity('export', null, null, 'CSV: ' . $filename);
        exit;
    }

    public static function exportGroupedPdf(string $title, array $headers, array $groupedData): void
    {
        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <style>
        @page { margin: 2cm; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
        .header { border-bottom: 3px solid #2d6a4f; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { color: #2d6a4f; margin: 0; font-size: 18px; }
        .forest-heading { background: #2d6a4f; color: white; padding: 10px; margin: 20px 0 0; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #40916c; color: white; padding: 8px; text-align: left; }
        td { padding: 6px 8px; border-bottom: 1px solid #ddd; }
        tr:nth-child(even) { background: #f9f9f9; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="header">
        <h1><?= htmlspecialchars($title) ?></h1>
        <p>Generated: <?= date('F j, Y H:i:s') ?></p>
    </div>
    <?php foreach ($groupedData as $forestName => $rows): ?>
    <div class="forest-heading"><?= htmlspecialchars($forestName) ?></div>
    <table>
        <thead><tr><?php foreach ($headers as $h): ?><th><?= htmlspecialchars($h) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
        <tr><?php foreach ($row as $cell): ?><td><?= htmlspecialchars((string)$cell) ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endforeach; ?>
    <p class="no-print"><button onclick="window.print()">Print / Save as PDF</button></p>
</body>
</html>
        <?php
        logActivity('export', null, null, 'PDF: ' . $title);
        exit;
    }

    public static function exportSingleIncidentPdf(?array $incident, string $title): void
    {
        header('Content-Type: text/html; charset=utf-8');
        if (!$incident) {
            echo '<p>Incident not found.</p>';
            exit;
        }
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?> #<?= (int)$incident['incident_id'] ?></title>
    <style>
        @page { margin: 2cm; }
        body { font-family: Georgia, serif; font-size: 12px; color: #222; max-width: 800px; margin: 0 auto; padding: 20px; }
        .report-header { border-bottom: 4px double #2d6a4f; padding-bottom: 16px; margin-bottom: 24px; text-align: center; }
        .report-header h1 { color: #2d6a4f; margin: 0 0 8px; font-size: 22px; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px; }
        .meta-item { background: #f8faf9; border-left: 4px solid #2d6a4f; padding: 10px 14px; }
        .meta-item strong { display: block; color: #666; font-size: 10px; text-transform: uppercase; }
        .description { background: #fff; border: 1px solid #ddd; padding: 16px; line-height: 1.6; margin-bottom: 20px; }
        .incident-image { max-width: 100%; border-radius: 8px; border: 1px solid #ccc; margin: 16px 0; }
        .status-badge { display: inline-block; padding: 4px 12px; background: #2d6a4f; color: white; border-radius: 4px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="report-header">
        <h1>Incident Investigation Report</h1>
        <p>Incident #<?= (int)$incident['incident_id'] ?></p>
        <p>Generated: <?= date('F j, Y H:i:s') ?></p>
    </div>
    <div class="meta-grid">
        <div class="meta-item"><strong>Incident Category</strong><?= htmlspecialchars($incident['incident_category']) ?></div>
        <div class="meta-item"><strong>Date of Incident</strong><?= htmlspecialchars($incident['incident_date']) ?></div>
        <div class="meta-item"><strong>Reported By</strong><?= htmlspecialchars($incident['reported_by'] ?? 'N/A') ?></div>
        <div class="meta-item"><strong>Verified By</strong><?= htmlspecialchars($incident['verified_by'] ?? 'N/A') ?></div>
        <div class="meta-item"><strong>Current Status</strong><span class="status-badge"><?= htmlspecialchars($incident['current_status']) ?></span></div>
    </div>
    <h3>Description</h3>
    <div class="description"><?= nl2br(htmlspecialchars($incident['description'])) ?></div>
    <?php if (!empty($incident['image_path'])): ?>
    <h3>Incident Image</h3>
    <img class="incident-image" src="<?= htmlspecialchars($incident['image_path']) ?>" alt="Incident photo">
    <?php endif; ?>
    <p class="no-print" style="margin-top:30px;"><button onclick="window.print()">Print / Save as PDF</button></p>
</body>
</html>
        <?php
        logActivity('export', null, null, 'PDF: Incident #' . $incident['incident_id']);
        exit;
    }

    public static function fetchTableData(string $table, array $columns, string $where = '1=1', array $params = []): array
    {
        $allowed = ['forests', 'animals', 'incidents', 'users', 'audit_log', 'activity_logs', 'notifications'];
        if (!in_array($table, $allowed, true)) {
            return [[], []];
        }

        $pdo = getPDO();
        $colList = implode(', ', $columns);
        $stmt = $pdo->prepare("SELECT {$colList} FROM {$table} WHERE {$where}");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        return [$columns, $rows];
    }
}
