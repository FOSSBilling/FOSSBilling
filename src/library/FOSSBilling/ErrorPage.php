<?php declare(strict_types=1);
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

class ErrorPage
{
    public function __construct()
    {
        /* If the __trans function is undefined, this probably means we experienced an unrecoverable error during the initialization of FOSSBilling.
         * As a workaround, we define a "polyfill" for it here which just returns the original (english) string, handling placeholders in the process.
         * This allows us to have translation functionality in our error pages, while also handling cases where they aren't setup.
         */
        if (!function_exists('__trans')) {
            function __trans(string $msgid, array $values = null)
            {
                if (is_array($values)) {
                    $msgid = strtr($msgid, $values);
                }

                return $msgid;
            }
        }
    }

    /**
     * Returns the list of error codes and their specialized messages. All Error code parameters are optional.
     *
     * @return array
     */
    private function getCodes(): array
    {
        return [
            '1' => [
                'title' => __trans('Unable to find Composer Packages'),
                'message' => 'The composer packages appear to be missing. This shouldn\'t happen if you are using a release version of FOSSBilling. If you are developer, you will need to install dependencies using :code.', [':code' => '<code>composer install</code>'],
                'link' => [
                    'label' => __trans('View more info on the composer website'),
                    'href' => 'https://getcomposer.org/doc/01-basic-usage.md#installing-dependencies',
                ],
            ],
            '2' => [
                'message' => __trans('For security reasons, you must delete the installation directory before you can use FOSSBilling. :code', [':code' => '(<code>/install</code>)']),
                'link' => [
                    'label' => __trans('View more info on the Getting Started guide'),
                    'href' => 'https://fossbilling.org/docs/getting-started/shared#remove-the-installer',
                ]
            ],
            '3' => [
                'title' => __trans('Your Configuration is Empty'),
                'message' => __trans('Your FOSSBilling configuration seems to either be empty or non-existent. You may need to re-install FOSSBilling, or re-create the :code file based on the example config.', [':code' => '<code>config.php</code>']),
                'link' => [
                    'label' => __trans('See the example config.'),
                    'href' => 'https://github.com/FOSSBilling/FOSSBilling/blob/main/src/config-sample.php',
                ]
            ],
            '4' => [
                'title' => __trans('Migration is required'),
                'message' => __trans('Legacy BoxBilling or FOSSBilling preview files have been found. The file structure within FOSSBilling, along with the configuration format, has since changed. :lineBreak See the migration guide for assistance in migrating to the latest version of FOSSBilling.', [':lineBreak' => '<br>']),
                'link' => [
                    'label' => __trans('Check the migration guide.'),
                    'href' => 'https://fossbilling.org/docs/getting-started/migrate-from-boxbilling',
                ]
            ],
            '5' => [
                'title' => __trans("Missing .htaccess file"),
                'message' => __trans("You appear to be running an Apache or LiteSpeed based webserver without a valid :code file. Please create one using the default FOSSBilling .htaccess file.", [':code', '<b><em>.htaccess</em></b>']),
                'link' => [
                    'label' => __trans("Check the default .htaccess"),
                    'href' => 'https://github.com/FOSSBilling/FOSSBilling/blob/main/src/.htaccess',
                ]
            ],
        ];
    }

    /* List of code categories. The "start" and "end" values are considered valid for a category.
     * (Example: an error code of 50 will match the "FOSSBilling Loader" category)
     */
    private array $codeCategories = [
        'FOSSBilling Loader' => [
            'start' => 1,
            'end' => 50,
        ],
        'HTTP Error Codes' => [
            'start' => 400,
            'end' => 599,
        ],
    ];

    /**
     * Gets info for a specified error code, using placeholders for anything undefined.
     *
     * @param int $code The error code
     * @return array
     */
    private function getCodeInfo(int $code): array
    {
        $errorDetails = [
            'title' => __trans('An error has occurred.'),
            'link' => [
                'label' => __trans('View the FOSSBilling documentation'),
                'href' => 'https://fossbilling.org/docs',
            ],
            'category' => __trans('None')
        ];

        $codes = $this->getCodes();

        if (key_exists($code, $codes)) {
            $codeInfo = $codes[$code];
            $errorDetails = array_merge($errorDetails, $codeInfo);
        }

        $errorDetails['category'] = 'Generic';
        foreach ($this->codeCategories as $categoryName => $categoryRange) {
            if ($code >= $categoryRange['start'] && $code <= $categoryRange['end']) {
                $errorDetails['category'] = $categoryName;
                break;
            }
        }

        return $errorDetails;
    }

    /**
     * @param int $code Error code
     * @param string $message The original exception message
     * @return never
     */
    public function generatePage(int $code, string $message)
    {
        $error = $this->getCodeInfo($code);
        $error['message'] ??= __trans('You\'ve received a generic error message: :errorMessage', [':errorMessage' => '<code>' . $message . '</code>']);

        $page = '
        <!DOCTYPE html>
        <html>
            <head>
            <title>FOSSBilling Error | ' . $error['title'] . '</title>
            <style>
            body {
                background-color: #222;
                color: #fff;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
                font-size: 16px;
                line-height: 1.5;
                margin: 0;
                padding: 0;
                text-align: left;
            }

            .container {
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
            }

            .error-container {
                width: 75%;
                background-color: #313131;
                border-radius: 25px;
                padding: 1%;
            }

            .error-title {
                font-size: 3.75rem;
                font-weight: 600;
                margin-bottom: 0px;
            }

            .error-code {
                font-size: 1rem;
                font-weight: 200;
                margin: 0px;
            }

            .error-category {
                font-size: 1rem;
                font-weight: 200;
                margin: 0px;
            }

            .error-message {
                font-size: 1.25rem;
                margin-bottom: 30px;
                line-height: 1.8;
            }

            code {
                background-color: #f2f2f2;
                color: #333;
                border-radius: 3px;
            }

            .footer {
                color: #fff;
                padding: 5px;
                text-align: center;
                font-size: 14px;
            }

            .footer a {
                color: #fff;
                text-decoration: none;
                margin: 0 10px;
            }

            .footer a:hover {
                text-decoration: underline;
            }

            a {
                color: #3291ff;
            }

            a:visited {
                color: inherit;
                text-decoration: none;
            }

            a:hover {
                text-decoration: underline;
            }

            .button {
                background-color: #3291ff;
                border: none;
                color: #fff;
                padding: 10px 20px;
                border-radius: 5px;
                font-size: 15px;
                cursor: pointer;
                transition: all 0.3s ease;
                text-decoration: none;
            }

            .button:hover {
                background-color: #3d9dff;
                text-decoration: none;
            }

            </style>
            </head>
            <body>
                <div class="container">
                <div class="error-container">
                    <p class="error-title">' . $error['title'] . '</p>
                    <p class="error-code">' . __trans('Error Code: #:number', [':number' => $code]) . '</p>
                    <p class="error-category">' . __trans('Component: :category', [':category' => $error['category']]) . '</p>
                    <p class="error-message" id="specialized">' . $error['message'] . '</p>
                    <p class="error-message" id="original" style="display: none;">' . $message . '</p>

                    <div class="link-container">
                        <button id="toggle" class="button" onclick="toggle()">' . __trans("Show original message") . '</button>
                        <a class="button" target="_blank" href="' . $error['link']['href'] . '">' . $error['link']['label'] . '</a>
                    </div>

                    <div class="footer" style="clear:both">
                        <hr>
                        <p>Powered By FOSSBilling</p>
                        <p>
                            <a href="https://github.com/fossbilling/fossbilling">Source code</a> |
                            <a href="https://fossbilling.org/discord">Discord</a> |
                            <a href="https://fossbilling.org/docs">Documentation</a> |
                            <a href="https://forum.fossbilling.com/">Forum</a> |
                            <a href="https://opencollective.com/FOSSBilling">Donate</a>
                        </p>
                    </div>
                </div>
                </div>
                <script>
                    function toggle() {
                        var og = document.getElementById("original");
                        var specialized = document.getElementById("specialized");

                        if (og.style.display === "none") {
                            og.style.display = "block";
                            specialized.style.display = "none";
                            document.querySelector("#toggle").innerHTML = "' . __trans("Show specialized message") . '";
                        } else {
                            og.style.display = "none";
                            specialized.style.display = "block";
                            document.querySelector("#toggle").innerHTML = "' . __trans("Show original message") . '";
                        }
                    }
                </script>
            </body>
        </html>';
        echo $page;
        die();
    }
}
