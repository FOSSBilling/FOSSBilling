<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\News;

use Box\Mod\News\Entity\Post;
use Box\Mod\News\Repository\PostRepository;

class Service
{
    protected ?\Pimple\Container $di = null;
    protected PostRepository $postRepository;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        $this->postRepository = $this->di['em']->getRepository(Post::class);
    }

    public function getPostRepository(): PostRepository
    {
        return $this->postRepository;
    }

    /**
     * Generate a placeholder meta description from given string.
     *
     * @param string $content - string to generate description from
     *
     * @return string Placeholder meta description
     */
    public function generateDescriptionFromContent(string $content): string
    {
        $desc = mb_convert_encoding($content, 'UTF-8');
        $desc = strip_tags($desc);
        $desc = str_replace(["\n", "\r", "\t"], ' ', $desc);

        return substr($desc, 0, 125);
    }
}
