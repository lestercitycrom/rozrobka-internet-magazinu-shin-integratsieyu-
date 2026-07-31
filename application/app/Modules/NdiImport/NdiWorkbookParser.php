<?php

namespace App\Modules\NdiImport;

use App\Modules\NdiImport\Data\WorkbookRow;
use App\Modules\NdiImport\Data\WorkbookSchema;
use App\Modules\NdiImport\Exceptions\UnknownWorkbookSchema;
use Generator;
use OpenSpout\Reader\XLSX\Reader;

final class NdiWorkbookParser
{
    public function inspect(string $path): WorkbookSchema
    {
        $reader = new Reader;
        $reader->open($path);

        try {
            $sheets = iterator_to_array($reader->getSheetIterator(), false);

            if (count($sheets) !== 1 || $sheets[0]->getName() !== 'Article') {
                throw new UnknownWorkbookSchema('The workbook must contain exactly one sheet named Article.');
            }

            foreach ($sheets[0]->getRowIterator() as $row) {
                $headers = $row->toArray();

                if (array_filter($headers, fn (mixed $value): bool => ! is_string($value)) !== []) {
                    throw new UnknownWorkbookSchema('Every workbook header must be a text cell.');
                }

                /** @var list<string> $headers */
                $role = NdiWorkbookSchemas::detect($headers);

                if ($role === null) {
                    throw new UnknownWorkbookSchema('The workbook header does not match a supported NDI dataset.');
                }

                return new WorkbookSchema($role, $headers, $sheets[0]->getName());
            }

            throw new UnknownWorkbookSchema('The workbook does not contain a header row.');
        } finally {
            $reader->close();
        }
    }

    /** @return Generator<int, WorkbookRow> */
    public function rows(string $path, WorkbookSchema $schema): Generator
    {
        $reader = new Reader;
        $reader->open($path);
        $excelRow = 0;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $excelRow++;

                    if ($excelRow === 1) {
                        continue;
                    }

                    $values = $row->toArray();

                    if (count($values) > count($schema->headers)) {
                        throw new UnknownWorkbookSchema("Excel row {$excelRow} has more cells than the detected schema.");
                    }

                    $values = array_pad($values, count($schema->headers), null);
                    /** @var array<string, mixed> $mapped */
                    $mapped = array_combine($schema->headers, $values);

                    yield new WorkbookRow($excelRow, $mapped);
                }
            }
        } finally {
            $reader->close();
        }
    }
}
