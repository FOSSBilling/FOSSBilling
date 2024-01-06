<?php
/**
 * Class to paginate a list of items in a old digg style.
 *
 * @see https://github.com/gigo6000/Symfony2-Pagination-Class
 *
 * @author Darko Goleš
 * @author Carlos Mafla <gigo6000@hotmail.com>
 *
 * @www.inchoo.net
 */
class Box_Paginator extends Paginator
{
}

class Paginator
{
    /**
     * @var int total number of pages
     */
    protected $numPages;

    /**
     * @var int offset
     */
    protected $offset;

    /**
     * @var array range
     */
    protected $range;

    /**
     * @var string currentUrl
     */
    protected $currentUrl;

    /**
     * @param int $itemsCount
     * @param int $currentPage
     * @param int $limit
     * @param int $midRange
     */
    public function __construct(protected $itemsCount = 0, protected $currentPage = 1, protected $limit = 20, protected $midRange = 7)
    {
        // Set defaults
        $this->setDefaults();

        // Calculate number of pages total
        $this->getInternalNumPages();

        // Calculate first shown item on current page
        $this->calculateOffset();
        $this->calculateRange();
    }

    private function calculateRange()
    {
        $startRange = $this->currentPage - floor($this->midRange / 2);
        $endRange = $this->currentPage + floor($this->midRange / 2);

        if ($startRange <= 0) {
            $endRange += abs($startRange) + 1;
            $startRange = 1;
        }

        if ($endRange > $this->numPages) {
            $endRange = $this->numPages;
            $startRange = $endRange - $this->numPages + 1;
        }

        $this->range = range($startRange, $endRange);
    }

    private function setDefaults()
    {
        // If currentPage is set to null or is set to 0 or less
        // set it to default (1)
        if ($this->currentPage == null || $this->currentPage < 1) {
            $this->currentPage = 1;
        }
        // if limit is set to null set it to default (20)
        if ($this->limit == null) {
            $this->limit = 20;
        // if limit is any number less than 1 then set it to 0 for displaying
        // items without limit
        } elseif ($this->limit < 1) {
            $this->limit = 0;
        }
    }

    private function getInternalNumPages()
    {
        // If limit is set to 0 or set to number bigger then total items count
        // display all in one page
        if ($this->limit < 1 || $this->limit > $this->itemsCount) {
            $this->numPages = 1;
        } else {
            // Calculate rest numbers from dividing operation so we can add one
            // more page for this items
            $restItemsNum = $this->itemsCount % $this->limit;
            // if rest items > 0 then add one more page else just divide items
            // by limit
            $this->numPages = $restItemsNum > 0 ? intval($this->itemsCount / $this->limit) + 1 : intval($this->itemsCount / $this->limit);
        }
    }

    private function calculateOffset()
    {
        // Calculet offset for items based on current page number
        $this->offset = ($this->currentPage - 1) * $this->limit;
    }

    /**
     * @return int number of pages
     */
    public function getNumPages()
    {
        return $this->numPages;
    }

    /**
     * @return int current page
     */
    public function getCurrentPage()
    {
        return $this->currentPage;
    }

    /**
     * @return string current url
     */
    public function getCurrentUrl()
    {
        return $this->currentUrl;
    }

    /**
     * @return int limit items per page
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @return int offset
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @return array range
     */
    public function getRange()
    {
        return $this->range;
    }

    /**
     * @return int mid range
     */
    public function getMidRange()
    {
        return $this->midRange;
    }

    public function getStartingPoint()
    {
        $arr = $this->getRange();

        return reset($arr);
    }

    public function getEndingPoint()
    {
        $arr = $this->getRange();

        return end($arr);
    }

    public function toArray()
    {
        return [
            'currentpage' => $this->getCurrentPage(),
            'numpages' => $this->getNumPages(),
            'midrange' => $this->getMidRange(),
            'range' => $this->getRange(),
            'start' => $this->getStartingPoint(),
            'end' => $this->getEndingPoint(),
        ];
    }
}
