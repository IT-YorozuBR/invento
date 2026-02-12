<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Contagem;
use App\Models\Inventario;

class ExportService
{
    private Inventario $inventarioModel;
    private Contagem   $contagemModel;

    public function __construct()
    {
        $this->inventarioModel = new Inventario();
        $this->contagemModel   = new Contagem();
    }

    public function export(int $inventarioId, string $format = 'xlsx'): void
    {
        $inventario = $this->inventarioModel->findById($inventarioId);
        if (!$inventario) {
            http_response_code(404);
            die('Inventário não encontrado.');
        }

        $rows     = $this->contagemModel->exportarDados($inventarioId);
        $headers  = [
            'Depósito', 'Part Number', 'Descrição', 'Unidade', 'Lote', 'Validade',
            'Qtd. 1ª Contagem', 'Qtd. 2ª Contagem', 'Qtd. 3ª Contagem', 'Qtd. Final',
            'Status', 'Contador 1º', 'Contador 2º', 'Contador 3º',
            'Data 1ª Contagem', 'Data 2ª Contagem', 'Data 3ª Contagem', 'Observações',
        ];

        $meta = [
            ['INVENTÁRIO',    $inventario['codigo']],
            ['DESCRIÇÃO',     $inventario['descricao']],
            ['DATA INÍCIO',   $this->fmtDate($inventario['data_inicio'])],
            ['DATA FIM',      $this->fmtDate($inventario['data_fim'])],
            ['ADMINISTRADOR', $inventario['admin_nome']],
            ['STATUS',        $inventario['status']],
            [],
        ];

        $data = $this->formatRows($rows);

        match (strtolower($format)) {
            'csv'         => $this->exportCsv($meta, $headers, $data, $inventario['codigo']),
            'txt'         => $this->exportTxt($meta, $headers, $data, $inventario['codigo']),
            'consolidado' => $this->exportConsolidado($inventarioId, 'xlsx'),
            default       => $this->exportXlsx($meta, $headers, $data, $inventario['codigo']),
        };
    }

    /**
     * Exporta relatório consolidado agrupando todos os registros de partnumber
     * independente do depósito, somando as quantidades.
     */
    public function exportConsolidado(int $inventarioId, string $format = 'xlsx'): void
    {
        $inventario = $this->inventarioModel->findById($inventarioId);
        if (!$inventario) {
            http_response_code(404);
            die('Inventário não encontrado.');
        }

        $rows = $this->contagemModel->exportarDadosConsolidados($inventarioId);

        $headers = [
            'Part Number', 'Descrição', 'Unidade',
            'Qtd. Total', 'Nº Depósitos', 'Detalhes por Depósito',
        ];

        $meta = [
            ['INVENTÁRIO',    $inventario['codigo']],
            ['DESCRIÇÃO',     $inventario['descricao']],
            ['RELATÓRIO',     'CONSOLIDADO POR PARTNUMBER'],
            ['DATA GERAÇÃO',  date('d/m/Y H:i:s')],
            [],
        ];

        $data = array_map(fn($r) => [
            $r['partnumber']          ?? '',
            $r['descricao_item']      ?? '',
            $r['unidade_medida']      ?? '',
            $this->fmtNum($r['quantidade_total'] ?? null),
            (string) ($r['num_depositos'] ?? 0),
            $r['detalhes_depositos']  ?? '',
        ], $rows);

        $code = $inventario['codigo'] . '_consolidado';

        match (strtolower($format)) {
            'csv'   => $this->exportCsv($meta, $headers, $data, $code),
            'txt'   => $this->exportTxt($meta, $headers, $data, $code),
            default => $this->exportXlsx($meta, $headers, $data, $code),
        };
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function formatRows(array $rows): array
    {
        return array_map(fn($r) => [
            $r['deposito']             ?? '',
            $r['partnumber']           ?? '',
            $r['descricao_item']       ?? '',
            $r['unidade_medida']       ?? '',
            $r['lote']                 ?? '',
            $this->fmtDate($r['validade'] ?? null),
            $this->fmtNum($r['quantidade_primaria']   ?? null),
            $this->fmtNum($r['quantidade_secundaria'] ?? null),
            $this->fmtNum($r['quantidade_terceira']   ?? null),
            $this->fmtNum($r['quantidade_final']      ?? null),
            $r['status']               ?? '',
            $r['contador_1']           ?? '',
            $r['contador_2']           ?? '',
            $r['contador_3']           ?? '',
            $this->fmtDateTime($r['data_contagem_primaria']   ?? null),
            $this->fmtDateTime($r['data_contagem_secundaria'] ?? null),
            $this->fmtDateTime($r['data_contagem_terceira']   ?? null),
            $r['observacoes']          ?? '',
        ], $rows);
    }

    private function fmtDate(?string $d): string
    {
        return $d ? date('d/m/Y', strtotime($d)) : '';
    }

    private function fmtDateTime(?string $d): string
    {
        return $d ? date('d/m/Y H:i', strtotime($d)) : '';
    }

    private function fmtNum($v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        $f = (float) $v;
        return abs($f - floor($f)) < 0.00001
            ? number_format($f, 0, ',', '.')
            : number_format($f, 4, ',', '.');
    }

    // -----------------------------------------------------------------------
    // CSV
    // -----------------------------------------------------------------------

    private function exportCsv(array $meta, array $headers, array $data, string $code): void
    {
        $filename = "inventario_{$code}_" . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

        foreach ($meta as $row) {
            fputcsv($out, $row, ';');
        }
        fputcsv($out, $headers, ';');
        foreach ($data as $row) {
            fputcsv($out, $row, ';');
        }
        fclose($out);
        exit;
    }

    // -----------------------------------------------------------------------
    // TXT
    // -----------------------------------------------------------------------

    private function exportTxt(array $meta, array $headers, array $data, string $code): void
    {
        $filename = "inventario_{$code}_" . date('Y-m-d_H-i-s') . '.txt';
        header('Content-Type: text/plain; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');

        $out = '';
        foreach ($meta as $row) {
            $out .= implode("\t", $row) . "\n";
        }
        $out .= implode("\t", $headers) . "\n";
        foreach ($data as $row) {
            $out .= implode("\t", $row) . "\n";
        }
        echo $out;
        exit;
    }

    // -----------------------------------------------------------------------
    // XLSX (via ZipArchive + SpreadsheetML)
    // -----------------------------------------------------------------------

    private function exportXlsx(array $meta, array $headers, array $data, string $code): void
    {
        $filename = "inventario_{$code}_" . date('Y-m-d_H-i-s');

        // Montar array único com meta + cabeçalhos + dados
        $rows = [];
        foreach ($meta as $row) {
            $rows[] = empty($row) ? [] : array_combine(array_keys($row + ['', '']), $row);
        }

        // Cabeçalho como primeira linha indexada por label
        $headerRow = array_combine($headers, $headers);
        $rows[]    = $headerRow;

        foreach ($data as $row) {
            $rows[] = array_combine($headers, $row);
        }

        $this->generateXlsxFile($rows, $filename);
    }

    private function generateXlsxFile(array $data, string $filename): void
    {
        $xml = $this->buildSpreadsheetXml($data);

        $tmpDir = sys_get_temp_dir() . '/xlsx_' . uniqid();
        $dirs   = [$tmpDir, "$tmpDir/_rels", "$tmpDir/xl", "$tmpDir/xl/_rels", "$tmpDir/xl/worksheets"];
        foreach ($dirs as $d) {
            mkdir($d, 0755, true);
        }

        file_put_contents("$tmpDir/xl/worksheets/sheet1.xml", $xml);
        file_put_contents("$tmpDir/[Content_Types].xml",      $this->contentTypesXml());
        file_put_contents("$tmpDir/_rels/.rels",              $this->rootRelsXml());
        file_put_contents("$tmpDir/xl/workbook.xml",          $this->workbookXml());
        file_put_contents("$tmpDir/xl/_rels/workbook.xml.rels",$this->workbookRelsXml());

        $zipFile = sys_get_temp_dir() . "/{$filename}.xlsx";
        $zip     = new \ZipArchive();
        $zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $entries = [
            '[Content_Types].xml'       => "$tmpDir/[Content_Types].xml",
            '_rels/.rels'               => "$tmpDir/_rels/.rels",
            'xl/workbook.xml'           => "$tmpDir/xl/workbook.xml",
            'xl/_rels/workbook.xml.rels'=> "$tmpDir/xl/_rels/workbook.xml.rels",
            'xl/worksheets/sheet1.xml'  => "$tmpDir/xl/worksheets/sheet1.xml",
        ];
        foreach ($entries as $local => $path) {
            $zip->addFile($path, $local);
        }
        $zip->close();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$filename}.xlsx\"");
        header('Content-Length: ' . filesize($zipFile));
        header('Cache-Control: max-age=0');

        if (ob_get_level()) {
            ob_end_clean();
        }
        readfile($zipFile);
        unlink($zipFile);
        $this->rmDir($tmpDir);
        exit;
    }

    private function buildSpreadsheetXml(array $data): string
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
              . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'
              . ' xmlns:x="urn:schemas-microsoft-com:office:excel">' . "\n";
        $xml .= " <Styles>\n";
        $xml .= '  <Style ss:ID="H"><Font ss:Bold="1" ss:Color="#FFFFFF"/>'
              . '<Interior ss:Color="#2C3E50" ss:Pattern="Solid"/>'
              . '<Alignment ss:Horizontal="Center"/></Style>' . "\n";
        $xml .= '  <Style ss:ID="N"><NumberFormat ss:Format="#,##0"/></Style>' . "\n";
        $xml .= " </Styles>\n";
        $xml .= " <Worksheet ss:Name=\"Inventário\">\n  <Table>\n";

        $isFirst = true;
        foreach ($data as $row) {
            if ($isFirst) {
                $xml    .= '   <Row ss:StyleID="H">';
                $isFirst = false;
            } else {
                $xml .= '   <Row>';
            }
            foreach ($row as $cell) {
                $type  = is_numeric($cell) && $cell !== '' ? 'Number' : 'String';
                $style = $type === 'Number' ? ' ss:StyleID="N"' : '';
                $xml  .= "<Cell{$style}><Data ss:Type=\"{$type}\">"
                       . htmlspecialchars((string) ($cell ?? ''), ENT_XML1, 'UTF-8')
                       . '</Data></Cell>';
            }
            $xml .= "</Row>\n";
        }

        $xml .= "  </Table>\n </Worksheet>\n</Workbook>";
        return $xml;
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Inventário" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';
    }

    private function rmDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->rmDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
