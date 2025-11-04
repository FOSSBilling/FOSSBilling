<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Tests\GoldenMaster;

use PHPUnit\Framework\TestCase;

/**
 * Base class for golden-master contract tests.
 *
 * Golden-master tests prove that API response shapes remain identical
 * after migrating from RedBeanPHP to Doctrine. They capture a "golden"
 * baseline and assert future responses match exactly.
 */
abstract class GoldenMasterTestCase extends TestCase
{
    /**
     * Scrub nondeterministic fields from JSON response.
     *
     * Replaces values that change between test runs (IDs, timestamps, tokens)
     * with placeholders so snapshots can be compared reliably.
     *
     * @param array $data Response data to scrub
     *
     * @return array Scrubbed data with placeholders
     */
    protected function scrubJson(array $data): array
    {
        array_walk_recursive($data, function (&$value, $key) {
            // Scrub IDs (replace with placeholder)
            if (in_array($key, ['id', 'client_id', 'admin_id', 'aid', 'group_id'])) {
                $value = '<ID>';
            }

            // Scrub timestamps
            if (in_array($key, ['created_at', 'updated_at', 'published_at', 'expires_at'])) {
                $value = '<TIMESTAMP>';
            }

            // Scrub tokens and hashes
            if (in_array($key, ['api_token', 'token', 'hash', 'pass', 'salt'])) {
                $value = '<TOKEN>';
            }

            // Scrub UUIDs
            if (is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-/i', $value)) {
                $value = '<UUID>';
            }
        });

        return $data;
    }

    /**
     * Assert JSON matches snapshot.
     *
     * Compares scrubbed actual response with saved snapshot. If snapshot
     * doesn't exist, creates it and marks test as skipped.
     *
     * @param array  $actual       Actual API response data
     * @param string $snapshotPath Path to snapshot file (relative to tests/)
     *
     * @throws \PHPUnit\Framework\AssertionFailedError If response doesn't match snapshot
     */
    protected function assertJsonMatchesSnapshot(array $actual, string $snapshotPath): void
    {
        $actualScrubbed = $this->scrubJson($actual);

        $fullPath = __DIR__ . '/' . $snapshotPath;
        $dir = dirname($fullPath);

        if (!file_exists($fullPath)) {
            // Create directory if needed
            if (!is_dir($dir)) {
                mkdir($dir, 0o755, true);
            }

            // Generate snapshot on first run
            file_put_contents(
                $fullPath,
                json_encode($actualScrubbed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
            $this->markTestSkipped('Snapshot created at: ' . $snapshotPath . '. Run tests again to verify.');
        }

        $expected = json_decode(file_get_contents($fullPath), true);

        if ($expected === null) {
            $this->fail('Invalid JSON in snapshot file: ' . $snapshotPath);
        }

        $this->assertEquals(
            $expected,
            $actualScrubbed,
            'API contract changed! Response does not match snapshot. ' .
            'If this change is intentional, delete the snapshot and regenerate it.'
        );
    }

    /**
     * Assert response has expected structure.
     *
     * Validates that required keys exist in the response.
     *
     * @param array $response     API response
     * @param array $requiredKeys Keys that must be present
     */
    protected function assertResponseStructure(array $response, array $requiredKeys): void
    {
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey(
                $key,
                $response,
                sprintf('Required key "%s" missing from response', $key)
            );
        }
    }

    /**
     * Assert pagination response structure.
     *
     * Validates the standard pagination structure returned by FOSSBilling APIs.
     *
     * @param array $response API response
     */
    protected function assertPaginationStructure(array $response): void
    {
        $this->assertResponseStructure($response, [
            'pages',
            'page',
            'per_page',
            'total',
            'list',
        ]);

        $this->assertIsInt($response['pages'], 'pages must be an integer');
        $this->assertIsInt($response['page'], 'page must be an integer');
        $this->assertIsInt($response['per_page'], 'per_page must be an integer');
        $this->assertIsInt($response['total'], 'total must be an integer');
        $this->assertIsArray($response['list'], 'list must be an array');
    }
}
