<?php

declare(strict_types=1);

namespace App\Documents;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class SpreadsheetReader implements DocumentReaderInterface
{
    protected array $extensions = ['xlsx', 'xls'];

    public function supports(string $extension): bool
    {
        return in_array(strtolower($extension), $this->extensions, true);
    }

    public function read(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new RuntimeException('Planilha não encontrada.');
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Throwable $e) {
            throw new RuntimeException('Não foi possível abrir a planilha: ' . $e->getMessage());
        }

        $sheets = [];
        $summaryParts = [];

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $sheetName = $sheet->getTitle();
            $rows = $sheet->toArray(null, true, true, false);

            if (empty($rows)) {
                $sheets[] = [
                    'name' => $sheetName,
                    'headers' => [],
                    'rows' => [],
                ];

                $summaryParts[] = "Aba: {$sheetName}\nSem dados.";
                continue;
            }

            $headers = array_map(
                fn($value) => trim((string) $value),
                $rows[0] ?? []
            );

            $dataRows = array_slice($rows, 1, 5);

            $normalizedRows = [];
            foreach ($dataRows as $row) {
                $normalizedRows[] = array_map(
                    fn($value) => trim((string) $value),
                    $row
                );
            }

            $sheets[] = [
                'name' => $sheetName,
                'headers' => $headers,
                'rows' => $normalizedRows,
            ];

            $sheetSummary = [];
            $sheetSummary[] = "Aba: {$sheetName}";
            $sheetSummary[] = 'Colunas: ' . implode(' | ', array_filter($headers, fn($h) => $h !== ''));

            if (!empty($normalizedRows)) {
                $sheetSummary[] = 'Linhas iniciais:';
                foreach ($normalizedRows as $row) {
                    $sheetSummary[] = '- ' . implode(' | ', $row);
                }
            } else {
                $sheetSummary[] = 'Sem linhas de dados.';
            }

            $summaryParts[] = implode("\n", $sheetSummary);
        }

        return [
            'type' => 'spreadsheet',
            'summary' => implode("\n\n", $summaryParts),
            'full_text' => implode("\n\n", $summaryParts),
            'structured' => [
                'sheets' => $sheets,
            ],
        ];
    }
}