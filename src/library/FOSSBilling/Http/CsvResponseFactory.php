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

use Doctrine\DBAL\Connection;
use League\Csv\EscapeFormula;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $headersRequested = $headers !== [];

        if ($headers) {
            $headers = array_values(array_diff($headers, self::SENSITIVE_COLUMNS));
        }

        $platform = $this->connection->getDatabasePlatform();
        $sql = 'SELECT * FROM ' . $platform->quoteSingleIdentifier($table);
        if ($limit > 0) {
            $sql = $platform->modifyLimitQuery($sql, $limit);
        }

        $response = new StreamedResponse(function () use ($sql, $headers, $headersRequested): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('Unable to open the CSV output stream.');
            }

            $csv = Writer::from($output);
            $csv->addFormatter((new EscapeFormula())->escapeRecord(...));

            // If every explicitly requested column was sensitive, produce an empty export
            // without querying or exposing any fallback columns.
            if ($headersRequested && $headers === []) {
                fclose($output);

                return;
            }

            $rows = $this->connection->iterateAssociative($sql);
            $headerMap = $headers === [] ? null : array_flip($headers);
            $wroteHeaders = false;

            foreach ($rows as $row) {
                if ($headerMap === null) {
                    $headers = array_values(array_diff(array_keys($row), self::SENSITIVE_COLUMNS));
                    $headerMap = array_flip($headers);
                }

                if (!$wroteHeaders) {
                    $csv->insertOne($headers);
                    $wroteHeaders = true;
                }
                $csv->insertOne(array_intersect_key($row, $headerMap));
            }

            // Preserve an explicitly requested header row for an empty result set.
            if (!$wroteHeaders && $headers !== []) {
                $csv->insertOne($headers);
            }

            fclose($output);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $outputName));
        $response->headers->set('Cache-Control', 'no-cache, must-revalidate');
        $response->headers->set('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');

        return $response;
    }
}
