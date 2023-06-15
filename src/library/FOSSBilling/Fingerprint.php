<?php
declare(strict_types=1);
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

class Fingerprint
{
    private array $fingerprintProperties;

    public function __construct()
    {
        $agentDetails = $this->extractAgentInfo();

        /**
         * Sets up the fingerprint info for the existing request.
         * 'weight' is used to weigh specific parameters.
         *      Example: The agent string has a weight of 2, one failure from it equal as 2 failures of other properties.
         *      By doing this, we can prevent minor changes such as a browser update from requiring the user to re-authenticate.
         *      But it does mean that if the that property and one-or-two other ones fail, the user will need to re-authenticate.
         */
        $this->fingerprintProperties = [
            'agentString' => [
                'source' => $agentDetails['userAgent'],
                'weight' => 2,
            ],
            'browser' => [
                'source' => $agentDetails['browser'],
                'weight' => 100, // Always fail if this doesn't match.
            ],
            'browserVersion' => [
                'source' => $agentDetails['browserVersion'],
                'weight' => 1,
            ],
            'os' => [
                'source' => $agentDetails['os'],
                'weight' => 100, // Always fail if this doesn't match.
            ],
            'ip' => [
                'source' => $_SERVER['REMOTE_ADDR'] ?? '',
                'weight' => 3,
            ],
            'referrer' => [
                'source' => $_SERVER['HTTP_REFERER'] ?? '',
                'weight' => 1,
            ],
            'forwardedFor' => [
                'source' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
                'weight' => 3,
            ],
            'language' => [
                'source' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
                'weight' => 2,
            ],
            'encoding' => [
                'source' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
                'weight' => 1,
            ],
            'upgradeRequests' => [
                'source' => $_SERVER['HTTP_UPGRADE_INSECURE_REQUESTS'] ?? '',
                'weight' => 1,
            ],
        ];
    }

    /**
     * Generates a fingerprint for the device that made the request to the server
     */
    public function fingerprint(): array
    {
        $fingerprint = [];

        foreach ($this->fingerprintProperties as $name => $properties) {
            if (!empty($properties['source'])) {
                $fingerprint[$name] = hash('md5', $properties['source']);
            }
        }

        return $fingerprint;
    }

    /**
     * Compares a provided fingerprint against one generated for the device that made the request to the server.
     * This function creates a baseline "score" with the total of properties in the fingerprint. The final score must be at least half of the baseline.
     *      - Each property can define a weight. For example, if the IP address doesn't match and the weight is set to 3, 3 will be selected from the total.
     *          - This means with a total of 9 properties, the IP address being wrong would effectively be weighted as 3 properties and only two more differing properties will make it fail the check.
     *      - If any property is found in one of the fingerprints and not the other, the baseline is incremented and the final score is decreased by it's weight.
     */
    public function checkFingerprint(array $fingerprint): bool
    {
        $itemCount = 0;
        $scoreSubtract = 0;

        foreach ($this->fingerprintProperties as $name => $properties) {
            $exitsInFingerprint = array_key_exists($name, $fingerprint);
            $exitsInCurrentFingerprint = !empty($properties['source']);

            if ((!$exitsInFingerprint && $exitsInCurrentFingerprint) || ($exitsInFingerprint && !$exitsInCurrentFingerprint)) {
                //The property exists in one fingerprint and not the other, so we increment the total count and deduct from the score.
                $itemCount++;
                $scoreSubtract += $properties['weight'];
            } elseif (!$exitsInFingerprint && !$exitsInCurrentFingerprint) {
                // Do nothing in this case, as the property isn't in either fingerprint.
            } else {
                $itemCount++;
                $hashedData = hash('md5', $properties['source']);

                if ($fingerprint[$name] !== $hashedData) {
                    $scoreSubtract += $properties['weight'];
                }
            }
        }

        // Remove the total score from the total number of items. The final score must be at least half the number of properties in order for the fingerprint to be considered valid.
        $finalScore = $itemCount - $scoreSubtract;
        return $finalScore >= ($itemCount / 2);
    }

    private function extractAgentInfo(): array
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Extract the browser name
        if (preg_match('/(?:Chrome|CriOS)\/([0-9\.]+)/', $userAgent, $matches)) {
            $browser = 'Chrome';
            $version = $matches[1];
        } elseif (preg_match('/Firefox\/([0-9\.]+)/', $userAgent, $matches)) {
            $browser = 'Firefox';
            $version = $matches[1];
        } elseif (preg_match('/Safari\/([0-9\.]+)/', $userAgent, $matches)) {
            $browser = 'Safari';
            $version = $matches[1];
        } else {
            $browser = 'Unknown';
            $version = 'Unknown';
        }

        // Extract the operating system
        if (preg_match('/Windows NT ([0-9\.]+)/', $userAgent, $matches)) {
            $os = 'Windows NT ' . $matches[1];
        } elseif (preg_match('/Mac OS X ([0-9_]+)/', $userAgent, $matches)) {
            $os = 'Mac OS X';
        } elseif (preg_match('/Linux/', $userAgent)) {
            $os = 'Linux';
        } else {
            $os = 'Unknown';
        }

        return [
            'browser' => $browser,
            'browserVersion' => $version,
            'os' => $os,
            'userAgent' => $userAgent,
        ];
    }
}
