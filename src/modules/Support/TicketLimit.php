<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Support;

use Box\Mod\Client\Entity\Client;

/**
 * A ticket limit per client.
 *
 * Support settings: "Tickets a client may open" (ticket_limit_max) "in this many
 * days" (ticket_limit_days), 0 or empty for no limit, and the client groups
 * without a limit (ticket_limit_exempt_groups). Every ticket of the client
 * created in the window counts, whoever opened it, but only the client's own new
 * ticket (client API ticket_create, so the client area) is refused: staff, email
 * and guest tickets and all replies never are.
 */
final class TicketLimit
{
    /**
     * The client's limit for the client area's banners: max, days, limit (both
     * written out, "2 tickets in 7 days"), left and date (when a ticket can be
     * opened again once none is left); null when the client has no limit.
     *
     * @return array{max: int, days: int, limit: string, left: int, date: string}|null
     */
    public static function status(\Pimple\Container $di, Client $client): ?array
    {
        $config = $di['mod_config']('support');
        $max = (int) ($config['ticket_limit_max'] ?? 0);
        $days = (int) ($config['ticket_limit_days'] ?? 0);
        $exempt = array_map(strval(...), (array) ($config['ticket_limit_exempt_groups'] ?? []));
        $memberOf = [];
        foreach ($client->getClientGroups() as $group) {
            $memberOf[] = (string) $group->getId();
        }
        if ($max < 1 || $days < 1 || ($memberOf !== [] && array_intersect($memberOf, $exempt) !== [])) {
            return null;
        }
        $times = $di['em']->getConnection()->fetchFirstColumn(
            'SELECT created_at FROM support_ticket WHERE client_id = ? AND created_at > ? ORDER BY created_at',
            [(int) $client->getId(), date('Y-m-d H:i:s', time() - $days * 86400)],
        );
        // The ticket whose leaving the window frees a place once the last one is used (none counted: one opened now).
        $from = $times[max(0, count($times) - $max)] ?? date('Y-m-d H:i:s');

        // Each count in its own plural form (Twig has none), so the banner reads "1 ticket in 1 day".
        $limit = __trans(':tickets in :days', [
            ':tickets' => __pluralTrans(':count ticket', ':count tickets', $max, [':count' => $max]),
            ':days' => __pluralTrans(':count day', ':count days', $days, [':count' => $days]),
        ]);

        return ['max' => $max, 'days' => $days, 'limit' => $limit, 'left' => max(0, $max - count($times)), 'date' => date('Y-m-d H:i:s', strtotime((string) $from) + $days * 86400)];
    }

    /** Refuses the client's new ticket when none is left. */
    public static function check(\Pimple\Container $di, Client $client): void
    {
        $status = self::status($di, $client);
        if ($status === null || $status['left'] > 0) {
            return;
        }
        // The date as the client area's format_date writes it (TwigFactory).
        $format = new \IntlDateFormatter(
            \FOSSBilling\Core\I18n\I18n::getActiveLocale($di['request']),
            constant('\IntlDateFormatter::' . strtoupper((string) \FOSSBilling\Core\System\Config::getProperty('i18n.date_format', 'MEDIUM'))),
            \IntlDateFormatter::NONE,
            \FOSSBilling\Core\I18n\I18n::getActiveTimezone($di['request'], $client->getTimezone()),
        );

        throw new \FOSSBilling\Core\Exception\InformationException('You have reached your limit of :limit. You can open a new ticket on :date.', [':limit' => $status['limit'], ':date' => $format->format(new \DateTimeImmutable($status['date']))]);
    }
}
