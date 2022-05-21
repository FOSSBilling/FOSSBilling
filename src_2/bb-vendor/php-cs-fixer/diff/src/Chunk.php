<?php
/*
 * This file is part of sebastian/diff.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpCsFixer\Diff;

final class Chunk
{
    /**
     * @var int
     */
    private $start;

    /**
     * @var int
     */
    private $startRange;

    /**
     * @var int
     */
    private $end;

    /**
     * @var int
     */
    private $endRange;

    /**
     * @var Line[]
     */
    private $lines;

    public function __construct($start = 0, $startRange = 1, $end = 0, $endRange = 1, array $lines = [])
    {
        $this->start      = $start;
        $this->startRange = $startRange;
        $this->end        = $end;
        $this->endRange   = $endRange;
        $this->lines      = $lines;
    }

    public function getStart()
    {
        return $this->start;
    }

    public function getStartRange()
    {
        return $this->startRange;
    }

    public function getEnd()
    {
        return $this->end;
    }

    public function getEndRange()
    {
        return $this->endRange;
    }

    /**
     * @return Line[]
     */
    public function getLines()
    {
        return $this->lines;
    }

    /**
     * @param Line[] $lines
     */
    public function setLines(array $lines)
    {
        foreach ($lines as $line) {
            if (!$line instanceof Line) {
                throw new InvalidArgumentException;
            }
        }

        $this->lines = $lines;
    }
}
