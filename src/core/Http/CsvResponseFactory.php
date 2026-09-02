<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Http;

use Doctrine\DBAL\Connection;
use FOSSBilling\Core\Exception\InformationException;
use League\Csv\EscapeFormula;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

final readonly class CsvResponseFactory
{
    /**
     * Credential columns that must never appear in any CSV export.
     */
    private const array SENSITIVE_COLUMNS = ['pass', 'salt', 'api_token', 'hash', 'config'];

    public function __construct(private Connection $connection)
    {
    }

    public function create(string $table, string $outputName = 'export.csv', array $headers = [], int $limit = 0): Response
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new InformationException('Invalid table name for CSV export');
        }

        $headersRequested = $headers !== [];

        if ($headers) {
            $headers = array_values(array_diff($headers, self::SENSITIVE_COLUMNS));
        }

        $sql = 'SELECT * FROM `' . $table . '`';
        $params = [];
        if ($limit > 0) {
            $sql .= ' LIMIT :limit';
            $params['limit'] = $limit;
        }
        $rows = $this->connection->fetchAllAssociative($sql, $params);

        if ($headers) {
            $rows = array_map(static fn (array $row): array => array_intersect_key($row, array_flip($headers)), $rows);
        } elseif (!$headersRequested && $rows !== []) {
            $headers = array_values(array_diff(array_keys(reset($rows)), self::SENSITIVE_COLUMNS));
            $rows = array_map(static fn (array $row): array => array_intersect_key($row, array_flip($headers)), $rows);
        } elseif ($headersRequested) {
            // All requested headers were stripped as sensitive — export nothing.
            $rows = [];
        }

        $csvFile = new \SplTempFileObject();
        $csv = Writer::from($csvFile);
        $escapeFormula = new EscapeFormula();
        $csv->addFormatter($escapeFormula->escapeRecord(...));
        $csv->insertOne($headers);
        $csv->insertAll($rows);

        $csvFile->rewind();
        $content = '';
        while (!$csvFile->eof()) {
            $content .= $csvFile->fgets();
        }

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $outputName));
        $response->headers->set('Cache-Control', 'no-cache, must-revalidate');
        $response->headers->set('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');

        return $response;
    }
}
