<?php

namespace App\Actions\Produk;

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\Satuan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportProdukAction
{
    /**
     * Import products from CSV content or array of rows.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{success: int, failed: int, errors: array<int, string>}
     */
    public function execute(array $rows): array
    {
        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // Accounting for 1-based index and header row

            // Normalize array keys to lowercase, trim spaces, and convert numeric cells to string
            $normalizedRow = [];
            foreach ($row as $key => $value) {
                $cleanKey = strtolower(trim((string) $key));
                if ($value === null || (is_string($value) && trim($value) === '')) {
                    $normalizedRow[$cleanKey] = null;
                } else {
                    $valStr = trim((string) $value);
                    if ($cleanKey === 'sku' && is_numeric($valStr) && (str_contains(strtolower($valStr), 'e') || str_contains($valStr, '.'))) {
                        $valStr = sprintf('%.0f', (float) $valStr);
                    }
                    $normalizedRow[$cleanKey] = $valStr;
                }
            }

            $validator = Validator::make($normalizedRow, [
                'nama_produk' => ['required', 'string', 'max:255'],
                'harga_jual' => ['required', 'numeric', 'min:0'],
                'kategori' => ['nullable', 'string', 'max:255'],
                'nama_varian' => ['nullable', 'string', 'max:255'],
                'satuan' => ['nullable', 'string', 'max:255'],
                'sku' => ['nullable', 'string', 'max:50'],
                'harga_modal' => ['nullable', 'numeric', 'min:0'],
                'stok' => ['nullable', 'integer', 'min:0'],
                'minimum_stok' => ['nullable', 'integer', 'min:0'],
            ]);

            if ($validator->fails()) {
                $failed++;
                $errors[] = "Baris {$rowNumber}: ".implode(', ', $validator->errors()->all());

                continue;
            }

            $data = $validator->validated();

            // Get or create Kategori
            $kategoriNama = ! empty($data['kategori']) ? $data['kategori'] : 'Umum';
            $kategori = Kategori::firstOrCreate(
                ['nama' => $kategoriNama]
            );

            // Get or create Satuan
            $satuanNama = ! empty($data['satuan']) ? $data['satuan'] : 'Pcs';
            $satuan = Satuan::firstOrCreate(
                ['nama' => $satuanNama]
            );

            // Parse values with NULL defaults if empty
            $sku = $this->formatSku($data['sku'] ?? null);
            $hargaModal = (isset($data['harga_modal']) && $data['harga_modal'] !== '') ? (float) $data['harga_modal'] : null;
            $stok = (isset($data['stok']) && $data['stok'] !== '') ? (int) $data['stok'] : null;
            $minimumStok = (isset($data['minimum_stok']) && $data['minimum_stok'] !== '') ? (int) $data['minimum_stok'] : null;
            $namaVarian = ! empty($data['nama_varian']) ? $data['nama_varian'] : 'Default';

            // Check SKU uniqueness if provided
            if ($sku !== null) {
                $existingVarian = ProdukVarian::with('produk')->where('sku', $sku)->first();
                if ($existingVarian) {
                    $existingProdukNama = $existingVarian->produk?->nama_produk;
                    if ($existingProdukNama !== $data['nama_produk'] || $existingVarian->nama_varian !== $namaVarian) {
                        $failed++;
                        $errors[] = "Baris {$rowNumber}: SKU '{$sku}' sudah digunakan oleh produk '{$existingProdukNama}' (Varian: {$existingVarian->nama_varian}).";

                        continue;
                    }
                }
            }

            try {
                DB::transaction(function () use ($data, $kategori, $satuan, $sku, $hargaModal, $stok, $minimumStok, $namaVarian) {
                    $produk = Produk::firstOrCreate(
                        ['nama_produk' => $data['nama_produk']],
                        ['kategori_id' => $kategori->id]
                    );

                    $produk->varians()->updateOrCreate(
                        ['nama_varian' => $namaVarian],
                        [
                            'satuan_id' => $satuan->id,
                            'harga_jual' => (float) $data['harga_jual'],
                            'sku' => $sku,
                            'harga_modal' => $hargaModal,
                            'stok' => $stok,
                            'minimum_stok' => $minimumStok,
                        ]
                    );
                });

                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "Baris {$rowNumber}: ".$e->getMessage();
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Format SKU to string and convert scientific notation back to integer digits.
     */
    private function formatSku(mixed $sku): ?string
    {
        if ($sku === null || trim((string) $sku) === '') {
            return null;
        }

        $skuStr = trim((string) $sku);

        if (is_numeric($skuStr) && (str_contains(strtolower($skuStr), 'e') || str_contains($skuStr, '.'))) {
            $skuStr = sprintf('%.0f', (float) $skuStr);
        }

        return $skuStr !== '' ? $skuStr : null;
    }

    /**
     * Parse Excel (.xlsx, .xls) or CSV file path into array of rows with header mapping.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseSpreadsheet(string $filePath): array
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            return [];
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($extension, ['xlsx', 'xls'])) {
            try {
                $spreadsheet = IOFactory::load($filePath);
                $worksheet = $spreadsheet->getActiveSheet();
                $sheetData = $worksheet->toArray(null, true, false, true);

                if (empty($sheetData)) {
                    return [];
                }

                $headers = [];
                $rows = [];

                $firstRowKey = array_key_first($sheetData);
                $firstRow = $sheetData[$firstRowKey];

                foreach ($firstRow as $colKey => $headerVal) {
                    $headers[$colKey] = strtolower(trim((string) $headerVal));
                }

                unset($sheetData[$firstRowKey]);

                foreach ($sheetData as $row) {
                    $hasContent = false;
                    foreach ($row as $val) {
                        if ($val !== null && trim((string) $val) !== '') {
                            $hasContent = true;
                            break;
                        }
                    }

                    if (! $hasContent) {
                        continue;
                    }

                    $rowData = [];
                    foreach ($headers as $colKey => $headerKey) {
                        if ($headerKey === '') {
                            continue;
                        }
                        $rowData[$headerKey] = $row[$colKey] ?? null;
                    }
                    $rows[] = $rowData;
                }

                return $rows;
            } catch (\Throwable $e) {
                return [];
            }
        }

        return $this->parseCsv($filePath);
    }

    /**
     * Parse CSV file or path into array of rows with header mapping.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseCsv(string $filePath): array
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            return [];
        }

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return [];
        }

        // Detect and strip UTF-8 BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = [];
        $rows = [];

        // Read header line
        if (($firstLine = fgetcsv($handle, 1000, ',')) !== false) {
            // Handle semicolon-delimited CSVs as well if commas didn't split
            if (count($firstLine) === 1 && str_contains($firstLine[0], ';')) {
                rewind($handle);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                } else {
                    fseek($handle, 3);
                }
                $firstLine = fgetcsv($handle, 1000, ';');
                $delimiter = ';';
            } else {
                $delimiter = ',';
            }

            foreach ($firstLine as $header) {
                $headers[] = strtolower(trim((string) $header));
            }

            while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
                if (count($row) === 1 && ($row[0] === null || trim($row[0]) === '')) {
                    continue; // Skip empty lines
                }

                $rowData = [];
                foreach ($headers as $i => $headerKey) {
                    $rowData[$headerKey] = $row[$i] ?? null;
                }
                $rows[] = $rowData;
            }
        }

        fclose($handle);

        return $rows;
    }
}
