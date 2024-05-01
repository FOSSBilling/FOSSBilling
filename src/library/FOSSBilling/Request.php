<?php

declare(strict_types=1);
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

class Request
{
    /**
     * Gets most possible client IPv4 Address. This method search in $_SERVER[‘REMOTE_ADDR’] and optionally in $_SERVER[‘HTTP_X_FORWARDED_FOR’].
     *
     * @param bool $trustForwardedHeader - No by default because this can be changed to anything extremely easy, making it unreliable for tracking and adding a potential source for external data to be executed.
     *                                   Please see: https://stackoverflow.com/questions/3003145/how-to-get-the-client-ip-address-in-php
     */
    public function getClientAddress(bool $trustForwardedHeader = false): string|array|null
    {
        $address = null;
        if ($trustForwardedHeader && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $address = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $address = $_SERVER['REMOTE_ADDR'] ?? null;
        }

        if (is_string($address)) {
            if (str_contains($address, ',')) {
                [$address] = explode(',', $address);
            }
        }

        return $address;
    }

    /**
     * Checks whether request includes attached files.
     *
     * @return int - number of files
     */
    public function hasFiles($onlySuccessful = true)
    {
        $number_of_files = 0;
        $number_of_successful_files = 0;
        foreach ($_FILES as $file) {
            ++$number_of_files;
            if (isset($file['error']) && $file['error'] == 0) {
                ++$number_of_successful_files;
            }
        }

        return ($onlySuccessful) ? $number_of_successful_files : $number_of_files;
    }

    /**
     * Gets attached files as SplFileInfo collection.
     */
    public function getUploadedFiles($onlySuccessful = true): array
    {
        $files = [];
        foreach ($_FILES as $file) {
            $f = new RequestFile($file);
            if ($onlySuccessful) {
                if ($file['error'] == 0) {
                    $files[] = $f;
                }
            } else {
                $files[] = $f;
            }
        }

        return $files;
    }
}
