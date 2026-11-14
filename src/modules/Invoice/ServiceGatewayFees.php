<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice;

use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Repository\ClientGroupMembershipRepository;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Entity\PayGateway;
use FOSSBilling\Core\Container\InjectionAwareInterface;

class ServiceGatewayFees implements InjectionAwareInterface
{
    final public const string TASK = 'gateway_fee';

    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function settings(PayGateway $gateway): array
    {
        $config = $gateway->getConfig() ?? [];
        $fee = $config['fee'] ?? [];

        return self::clean(is_array($fee) ? $fee : []);
    }

    public static function clean(array $fee): array
    {
        $number = static fn (mixed $value): float => is_numeric($value) ? round(max(0.0, (float) $value), 4) : 0.0;

        return [
            'percent' => min(100.0, $number($fee['percent'] ?? 0)),
            'fixed' => round($number($fee['fixed'] ?? 0), 2),
            'taxable' => !empty($fee['taxable']),
            'exempt_groups' => array_values(array_unique(array_map(intval(...), array_filter((array) ($fee['exempt_groups'] ?? []), is_numeric(...))))),
        ];
    }

    public function fee(PayGateway $gateway, float $base, string $currency, ?int $clientId): float
    {
        $fee = $this->settings($gateway);
        if ($base <= 0 || ($fee['percent'] <= 0 && $fee['fixed'] <= 0)) {
            return 0.0;
        }

        if ($clientId !== null && $fee['exempt_groups'] !== [] && array_intersect($this->getClientGroupIds($clientId), $fee['exempt_groups']) !== []) {
            return 0.0;
        }

        $fixed = $fee['fixed'] > 0 ? $fee['fixed'] / $this->di['mod_service']('Currency')->getBaseCurrencyRate($currency) : 0.0;

        return round($base * $fee['percent'] / 100 + $fixed, 2);
    }

    /**
     * @return int[]
     */
    private function getClientGroupIds(int $clientId): array
    {
        $client = $this->di['em']->getRepository(Client::class)->find($clientId);
        if ($client instanceof Client) {
            return $client->getGroupIds();
        }

        return $this->di['em']->getRepository(ClientGroupMembershipRepository::class)->getGroupIdsForClient($clientId);
    }

    public function line(Invoice $invoice): ?InvoiceItem
    {
        $line = $this->di['em']->getRepository(InvoiceItem::class)->findOneBy([
            'invoice' => $invoice,
            'type' => InvoiceItem::TYPE_CUSTOM,
            'task' => self::TASK,
        ]);

        return $line instanceof InvoiceItem ? $line : null;
    }

    public function base(Invoice $invoice): float
    {
        $line = $this->line($invoice);
        $invoiceService = $this->di['mod_service']('Invoice');
        $total = $invoiceService->getTotalWithTax($invoice);

        return max(0.0, round($total - ($line === null ? 0.0 : $this->lineTotal($invoice, $line)) - $invoiceService->getAmountPaid($invoice), 2));
    }

    public function lineTotal(Invoice $invoice, InvoiceItem $line): float
    {
        $price = (float) ($line->getPrice() ?? 0) * ($line->getQuantity() ?? 1);

        return round($price + ($line->getTaxed() ? $this->taxForFee($invoice, $price) : 0.0), 2);
    }

    /**
     * The tax a taxed $fee adds to the invoice, rounded once over all its
     * taxed lines as Service::getTax() does.
     */
    public function taxForFee(Invoice $invoice, float $fee): float
    {
        $rate = (float) ($invoice->getTaxrate() ?? 0);
        if ($rate <= 0 || $fee <= 0) {
            return 0.0;
        }

        $taxed = 0.0;
        foreach ($this->di['em']->getRepository(InvoiceItem::class)->findByInvoiceId((int) $invoice->getId()) as $item) {
            if ($item->getTaxed() && $item->getTask() !== self::TASK) {
                $taxed += (($item->getPrice() ?? 0) * ($item->getQuantity() ?? 1));
            }
        }

        return round(round(($taxed + $fee) * $rate / 100, 2) - round($taxed * $rate / 100, 2), 2);
    }

    public function charge(Invoice $invoice, PayGateway $gateway, float $base): float
    {
        $fee = $this->fee($gateway, $base, (string) $invoice->getCurrency(), $invoice->getClientId());
        if ($fee <= 0) {
            return 0.0;
        }

        return round($fee + ($this->settings($gateway)['taxable'] ? $this->taxForFee($invoice, $fee) : 0.0), 2);
    }

    /**
     * Sets the fee line of an unpaid invoice for the gateway that is to take
     * its payment (null removes it). Add Funds invoices carry no fee.
     * Gateway assignment itself stays with the caller.
     *
     * Fee lines are payment adjustments, not history rewrites: like status
     * changes they may touch issued unpaid invoices, under the invoice lock,
     * while paid, refunded, and canceled invoices stay immutable.
     */
    public function apply(Invoice $invoice, ?PayGateway $gateway): void
    {
        $invoiceService = $this->di['mod_service']('Invoice');

        $this->di['em']->wrapInTransaction(function () use ($invoice, $gateway, $invoiceService): void {
            $invoiceService->lockInvoiceState($invoice);
            $this->di['em']->refresh($invoice);

            if ($invoice->getStatus() !== Invoice::STATUS_UNPAID
                || $invoiceService->isInvoiceTypeDeposit($invoice)) {
                return;
            }

            $items = $this->di['mod_service']('Invoice', 'InvoiceItem');
            $line = $this->line($invoice);
            if (!$gateway instanceof PayGateway) {
                if ($line instanceof InvoiceItem) {
                    $items->remove($line, true);
                }

                return;
            }

            $settings = $this->settings($gateway);
            $amount = ($settings['percent'] > 0 || $settings['fixed'] > 0)
                ? $this->fee($gateway, $this->base($invoice), (string) $invoice->getCurrency(), $invoice->getClientId())
                : 0.0;
            if ($amount <= 0) {
                if ($line instanceof InvoiceItem) {
                    $items->remove($line, true);
                }

                return;
            }

            $taxed = (bool) $settings['taxable'];
            $title = __trans(':gateway fee', [':gateway' => (string) $gateway->getName()]);
            if ($line instanceof InvoiceItem
                && $line->getTitle() === $title
                && round((float) $line->getPrice(), 2) === $amount
                && (bool) $line->getTaxed() === $taxed) {
                return;
            }

            if ($line instanceof InvoiceItem) {
                $items->remove($line, true);
            }
            $items->addNew($invoice, ['title' => $title, 'price' => $amount, 'quantity' => 1, 'taxed' => $taxed, 'task' => self::TASK, 'type' => InvoiceItem::TYPE_CUSTOM], true);
        });
    }

    public function remove(Invoice $invoice): float
    {
        $invoiceService = $this->di['mod_service']('Invoice');

        return $this->di['em']->wrapInTransaction(function () use ($invoice, $invoiceService): float {
            $invoiceService->lockInvoiceState($invoice);
            $this->di['em']->refresh($invoice);

            $line = $this->line($invoice);
            if (!$line instanceof InvoiceItem || $invoice->getStatus() !== Invoice::STATUS_UNPAID) {
                return 0.0;
            }

            $total = $this->lineTotal($invoice, $line);
            $this->di['mod_service']('Invoice', 'InvoiceItem')->remove($line, true);

            return $total;
        });
    }
}
