<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Http;

use Box_Database;
use League\Csv\EscapeFormula;
use League\Csv\Writer;
use SplTempFileObject;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

final class CsvResponseFactory
{
    public function __construct(private readonly Box_Database $database)
    {
    }

    public function create(string $table, string $outputName = 'export.csv', array $headers = [], int $limit = 0): Response
    {
        if ($limit > 0) {
            $beans = $this->database->findAll($table, 'LIMIT :limit', [':limit' => $limit]);
        } else {
            $beans = $this->database->findAll($table);
        }

        $rows = array_map(static fn ($bean) => $bean->export(), $beans);

        if ($headers) {
            $rows = array_map(static fn (array $row): array => array_intersect_key($row, array_flip($headers)), $rows);
        } elseif ($rows !== []) {
            $headers = array_keys(reset($rows));
        }

        $csvFile = new SplTempFileObject();
        $csv = Writer::from($csvFile);
        $escapeFormula = new EscapeFormula();
        $csv->addFormatter($escapeFormula->escapeRecord(...));
        $csv->insertOne($headers);
        $csv->insertAll($rows);

        $csvFile->rewind();
        $content = '';
        while (!$csvFile->eof()) {
            $content .= (string) $csvFile->fgets();
        }

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $outputName));
        $response->headers->set('Cache-Control', 'no-cache, must-revalidate');
        $response->headers->set('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');

        return $response;
    }
}
