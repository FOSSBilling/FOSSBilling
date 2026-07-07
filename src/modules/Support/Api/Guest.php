<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Support\Api;

use Box\Mod\Support\Entity\KbArticle;
use Box\Mod\Support\Entity\KbArticleCategory;
use FOSSBilling\PaginationOptions;
use FOSSBilling\Validation\Api\RequiredParams;

class Guest extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Submit new ticket.
     *
     * @return string - ticket hash
     */
    #[RequiredParams([
        'name' => 'Please enter your name',
        'email' => 'Please enter your email address',
        'subject' => 'Please enter the subject',
    ])]
    public function ticket_create(array $data): string
    {
        // Deprecated 0.9.0 The 'message' parameter will be dropped. Update your themes to use 'content' instead.
        $content = $data['content'] ?? $data['message'] ?? null;
        if (!is_string($content) || strlen($content) < 4) {
            throw new \FOSSBilling\InformationException('Please enter your message');
        }

        $data['email'] = $this->getDi()['tools']->validateAndSanitizeEmail($data['email']);
        $this->getDi()['rate_limiter']->consumeOrThrow('guest_ticket_create', (string) $this->getIp());

        $data['content'] = \FOSSBilling\Tools::sanitizeMarkdownContent($content);

        return $this->getService()->ticketCreateForGuest($data);
    }

    /**
     * Get ticket.
     *
     * @return array - ticket details
     */
    #[RequiredParams(['hash' => 'Ticket hash required'])]
    public function ticket_get(array $data): array
    {
        $guestTicket = $this->getService()->findOneByHash($data['hash']);

        return $this->getService()->toApiArray($guestTicket);
    }

    /**
     * Close ticket.
     */
    #[RequiredParams(['hash' => 'Ticket hash required'])]
    public function ticket_close(array $data): bool
    {
        $guestTicket = $this->getService()->findOneByHash($data['hash']);

        return $this->getService()->closeTicket($guestTicket, $this->getIdentity());
    }

    /**
     * Reply to ticket.
     */
    #[RequiredParams(['hash' => 'Ticket hash required'])]
    public function ticket_reply(array $data): int
    {
        $guestTicket = $this->getService()->findOneByHash($data['hash']);
        // Deprecated 0.9.0 The 'message' parameter will be dropped. Update your themes to use 'content' instead.
        $message = $data['content'] ?? $data['message'] ?? null;

        if (!is_string($message)) {
            throw new \FOSSBilling\InformationException('Message cannot be empty');
        }

        $message = \FOSSBilling\Tools::sanitizeMarkdownContent($message);

        return $this->getService()->ticketReply($guestTicket, new \Model_Guest(), $message);
    }

    /**
     * Get whether guest tickets are enabled.
     */
    public function guest_tickets_enabled(): bool
    {
        return $this->getService()->guestTicketsEnabled();
    }

    /**
     * @deprecated use guest_tickets_enabled() instead
     */
    public function public_tickets_enabled(): bool
    {
        return $this->guest_tickets_enabled();
    }

    /**
     * Return pairs for support helpdesks. Can be used to populate the select box.
     */
    public function helpdesk_get_pairs(): array
    {
        return $this->getService()->getHelpdeskRepository()->getPairs();
    }

    /*
     * Support Knowledge Base Functions.
     */
    /**
     * Get whether the knowledge base is enabled, or not.
     */
    public function kb_enabled(): bool
    {
        return $this->getService()->kbEnabled();
    }

    /**
     * Get whether the knowledge base suggestions are enabled in the specified area.
     */
    public function kb_suggestions_enabled(array $data): bool
    {
        return $this->getService()->kbSuggestionsEnabled($data['area'] ?? '');
    }

    /**
     * Get whether public knowledge base article view counts are visible.
     */
    public function kb_article_views_enabled(): bool
    {
        return $this->getService()->kbArticleViewsEnabled();
    }

    /**
     * Get paginated list of knowledge base articles.
     * Returns only active articles.
     */
    public function kb_article_get_list(array $data): array
    {
        $this->assertKbEnabled();

        $search = $data['search'] ?? null;
        $cat = $data['kb_article_category_id'] ?? null;

        /** @var \Box\Mod\Support\Repository\KbArticleRepository $repo */
        $repo = $this->getService()->getKbArticleRepository();

        $qb = $repo->getSearchQueryBuilder(KbArticle::ACTIVE, $search, $cat);

        return $this->getDi()['pager']->paginateDoctrineQuery($qb, PaginationOptions::fromArray($data), $this->getIdentity(), false, $this->getService()->kbArticleViewsEnabled());
    }

    /**
     * Get active knowledge base article.
     */
    public function kb_article_get(array $data): array
    {
        $this->assertKbEnabled();

        if (!isset($data['id']) && !isset($data['slug'])) {
            throw new \FOSSBilling\InformationException('ID or slug is missing');
        }

        $id = $data['id'] ?? null;
        $slug = $data['slug'] ?? null;

        /** @var \Box\Mod\Support\Repository\KbArticleRepository $repo */
        $repo = $this->getService()->getKbArticleRepository();

        $article = $id
            ? $repo->findOneActiveById((int) $id)
            : $repo->findOneActiveBySlug($slug);

        if (!$article instanceof KbArticle) {
            throw new \FOSSBilling\InformationException('Article item not found');
        }

        $repo->incrementViews($article);

        return $article->toApiArray($this->getIdentity(), includeContent: true, includeViews: $this->getService()->kbArticleViewsEnabled());
    }

    /**
     * Get paginated list of knowledge base categories.
     */
    public function kb_category_get_list(array $data): array
    {
        $this->assertKbEnabled();

        $data['article_status'] = KbArticle::ACTIVE;
        $q = $data['q'] ?? null;

        /** @var \Box\Mod\Support\Repository\KbArticleCategoryRepository $repo */
        $repo = $this->getService()->getKbArticleCategoryRepository();

        $qb = $repo->getSearchQueryBuilder($data);

        return $this->getDi()['pager']->paginateDoctrineQuery($qb, PaginationOptions::fromArray($data), $this->getIdentity(), $q, $this->getService()->kbArticleViewsEnabled());
    }

    /**
     * Get knowledge base category by ID or SLUG.
     */
    public function kb_category_get(array $data): array
    {
        $this->assertKbEnabled();

        if (!isset($data['id']) && !isset($data['slug'])) {
            throw new \FOSSBilling\InformationException('Category ID or slug is missing');
        }

        $id = $data['id'] ?? null;
        $slug = $data['slug'] ?? null;

        /** @var \Box\Mod\Support\Repository\KbArticleCategoryRepository $repo */
        $repo = $this->getService()->getKbArticleCategoryRepository();

        $cat = $id
            ? $repo->find((int) $id)
            : $repo->findOneBySlug($slug);

        if (!$cat instanceof KbArticleCategory) {
            throw new \FOSSBilling\InformationException('Knowledge Base category not found');
        }

        return $cat->toApiArray($this->getIdentity(), includeArticleViews: $this->getService()->kbArticleViewsEnabled());
    }

    private function assertKbEnabled(): void
    {
        if (!$this->getService()->kbEnabled()) {
            throw new \FOSSBilling\InformationException('Knowledge Base is disabled');
        }
    }
}
