<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * The durable claim ledger for Nano payments: one send block credits one
 * invoice, once, and the record survives a restart.
 *
 * A confirmed Nano send stays valid forever and its hash is public, so replay
 * protection cannot live in memory: a process restart that forgot its claims
 * would let every old block be presented again. Each claim is appended and
 * fsynced to a file under the operator's data directory and reloaded on the
 * next construction, so the "block hash -> invoice" record outlives the PHP
 * worker. Pure: no dependency and no network, so it unit-tests on its own.
 *
 * File format: one `HASH\tORDER\n` line per claim; the hash is upper-case so a
 * re-read in another case is the same block.
 */
final class Payment_Nano_ClaimsFile
{
    /** @var array<string, string> block hash (upper) => order id */
    private array $byHash = [];

    /** @var array<string, string> order id => block hash (upper) */
    private array $byOrder = [];

    public function __construct(private readonly string $path)
    {
        $this->load();
    }

    /**
     * Record that $blockHash settles $orderId.
     *
     * @return bool true when this is the first time the block is credited (the
     *              caller should release the invoice now); false when the same
     *              block was already credited to the same order (idempotent -
     *              a retried callback must not credit twice)
     *
     * @throws Payment_Exception when the block was already spent on another
     *                           order, or the order was already paid by another
     *                           block - neither is ever credited
     */
    public function claim(string $blockHash, string $orderId): bool
    {
        $hash = strtoupper(trim($blockHash));
        if (preg_match('/^[0-9A-F]{64}$/', $hash) !== 1) {
            throw new Payment_Exception('A Nano block hash is 64 hex characters.');
        }

        $this->load();

        if (isset($this->byHash[$hash])) {
            if ($this->byHash[$hash] === $orderId) {
                return false;
            }

            throw new Payment_Exception('This Nano block was already used to pay another invoice.');
        }

        if (isset($this->byOrder[$orderId]) && $this->byOrder[$orderId] !== $hash) {
            throw new Payment_Exception('This invoice was already paid by another Nano block.');
        }

        $this->append($hash, $orderId);
        $this->byHash[$hash] = $orderId;
        $this->byOrder[$orderId] = $hash;

        return true;
    }

    public function isClaimed(string $blockHash): bool
    {
        return isset($this->byHash[strtoupper(trim($blockHash))]);
    }

    private function load(): void
    {
        $this->byHash = [];
        $this->byOrder = [];

        if (!is_file($this->path)) {
            return;
        }

        $handle = fopen($this->path, 'rb');
        if ($handle === false) {
            throw new Payment_Exception('The Nano claims file could not be read.');
        }

        try {
            flock($handle, LOCK_SH);
            while (($line = fgets($handle)) !== false) {
                $line = rtrim($line, "\r\n");
                if ($line === '') {
                    continue;
                }
                $parts = explode("\t", $line, 2);
                if (count($parts) !== 2) {
                    continue;
                }
                [$hash, $order] = $parts;
                $this->byHash[$hash] = $order;
                $this->byOrder[$order] = $hash;
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function append(string $hash, string $orderId): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0o700, true) && !is_dir($dir)) {
            throw new Payment_Exception('The Nano claims directory could not be created.');
        }

        $handle = fopen($this->path, 'ab');
        if ($handle === false) {
            throw new Payment_Exception('The Nano claims file could not be opened for writing.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new Payment_Exception('The Nano claims file is locked.');
            }
            fwrite($handle, $hash . "\t" . $orderId . "\n");
            fflush($handle);
            // fsync when the runtime offers it, so the claim is on disk before
            // the invoice is released and a crash cannot forget it.
            if (function_exists('fsync')) {
                @fsync($handle);
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
