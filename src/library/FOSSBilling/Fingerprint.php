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

class Fingerprint
{
    private readonly array $fingerprintProperties;

    public function __construct()
    {
        $agentDetails = $this->extractAgentInfo();

        /*
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
                'weight' => 2,
            ],
            'referrer' => [
                'source' => $_SERVER['HTTP_REFERER'] ?? '',
                'weight' => 1,
            ],
            'forwardedFor' => [
                'source' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
                'weight' => 1,
            ],
            'language' => [
                'source' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
                'weight' => 3,
            ],
            'encoding' => [
                'source' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
                'weight' => 1,
            ],
            'upgradeRequests' => [
                'source' => $_SERVER['HTTP_UPGRADE_INSECURE_REQUESTS'] ?? '',
                'weight' => 1,
            ],
            'platform' => [
                'source' => $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? '',
                'weight' => 100, // Should more-or-less match the 'OS'. This also should never randomly change
            ],
            'mobile' => [
                'source' => $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ?? '',
                'weight' => 2,
            ],
            'cloudFlareCountry' => [
                'source' => $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '', // "IP Geolocation" must be enabled under Cloudflare's "network" settings
                'weight' => 4,
            ],
        ];
    }

    /**
     * Generates a fingerprint for the device that made the request to the server.
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
     *      - If any property is found in one of the fingerprints and not the other, the baseline is incremented and the final score is decreased by its weight.
     *
     * @return bool `true` if the fingerprint passes, `false` if it's considered invalid
     */
    public function checkFingerprint(array $fingerprint): bool
    {
        $itemCount = 0;
        $scoreSubtract = 0;
        $differing = [];

        foreach ($this->fingerprintProperties as $name => $properties) {
            $exitsInFingerprint = array_key_exists($name, $fingerprint);
            $exitsInCurrentFingerprint = !empty($properties['source']);

            if ((!$exitsInFingerprint && $exitsInCurrentFingerprint) || ($exitsInFingerprint && !$exitsInCurrentFingerprint)) {
                // The property exists in one fingerprint and not the other, so we increment the total count and deduct from the score.
                ++$itemCount;
                $scoreSubtract += $properties['weight'];
                $differing[] = $name;
            } elseif (!$exitsInFingerprint && !$exitsInCurrentFingerprint) {
                // Do nothing in this case, as the property isn't in either fingerprint.
            } else {
                ++$itemCount;
                $hashedData = hash('md5', $properties['source']);

                if ($fingerprint[$name] !== $hashedData) {
                    $scoreSubtract += $properties['weight'];
                    $differing[] = $name;
                }
            }
        }

        /**
         * Here we calculate how confident we are in our ability to fingerprint a device without causing issues for legitimate sessions.
         * The less confident we are, the more wrong the current fingerprint needs to be when compared against the session's before it is invalidated.
         *
         * In the event that less that 70% of the possible values are in the fingerprint, we use the percentage off we are to calculate a higher percentage before failure.
         * For example:
         *  If we have 13 possible fingerprint properties and only 9 are available, that's only 69% of the possible properties and the failure threshold will be calculated as follows.
         *  Negative weight: (1 - 0.69) / 1.25 = `0.24`
         *  Failure threshold: 0.5 + 0.24 = `0.74`
         *
         *  So in this example, the percentage wrong needs to be at or above 74% (nearly 75%) before the session is declared invalid.
         *  If there's only 6 of 13 available that moves to 93% and at 5 of 13 it's 99%.
         *  By doing this, we can effectively give additional headroom in situations where we are less-confident than we'd like to be and effectively completely disable the check in a worst-case situation.
         *
         *  Keep in mind, this method does not prevent changes such as the OS or browser from invalidating a session as those are weighted so heavily to always be considered more than 100% wrong.
         */
        $percentOfOverallItems = $itemCount / count($this->fingerprintProperties);
        if ($percentOfOverallItems >= 0.70) {
            $failureThreshold = 0.50;
        } else {
            $negativeWeight = (1 - $percentOfOverallItems) / 1.25;
            $failureThreshold = 0.5 + $negativeWeight;
        }

        // Here we calculate the "percentage wrong" (weighted, not a true percent) and return true (indicating no issues) if it's less then the failure threshold.
        $percentageWrong = $scoreSubtract / $itemCount;
        $valid = $percentageWrong < $failureThreshold;

        // If fingerprint debugging is enabled and it failed, print some debug info to the log
        if (!$valid && Config::getProperty('security.debug_fingerprint', false)) {
            $ID = $_COOKIE['PHPSESSID'] ?? null;
            if (!$ID) {
                return $valid;
            }

            $percentageWrong = round($percentageWrong * 100, 3);
            $failureThreshold = round($failureThreshold * 100, 3);

            error_log("The session with the ID '$ID' failed its fingerprint check with a (weighted) difference of $percentageWrong% compared to the allowed $failureThreshold%. $itemCount properties were used in the check.");
            $output = PHP_EOL;
            foreach ($differing as $name) {
                $output .= '    ' . $name . PHP_EOL;
            }
            error_log('The following properties differed:' . $output);
        }

        return $valid;
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
