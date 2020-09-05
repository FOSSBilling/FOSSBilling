<?php
/**
 * Placement.php
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 *
 * This file is part of tc-lib-barcode software library.
 */

namespace Com\Tecnick\Barcode\Type\Square\Datamatrix;

use \Com\Tecnick\Barcode\Exception as BarcodeException;

/**
 * Com\Tecnick\Barcode\Type\Square\Datamatrix\Placement
 *
 * Placement methods for Datamatrix Barcode type class
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
abstract class Placement
{
    /**
     * Places "chr+bit" with appropriate wrapping within array[].
     * (Annex F - ECC 200 symbol character placement)
     *
     * @param array $marr  Array of symbols.
     * @param int   $nrow  Number of rows.
     * @param int   $ncol  Number of columns.
     * @param int   $row   Row number.
     * @param int   $col   Column number.
     * @param int   $chr   Char byte.
     * @param int   $bit   Bit.
     *
     * @return array
     */
    protected function placeModule($marr, $nrow, $ncol, $row, $col, $chr, $bit)
    {
        if ($row < 0) {
            $row += $nrow;
            $col += (4 - (($nrow + 4) % 8));
        }
        if ($col < 0) {
            $col += $ncol;
            $row += (4 - (($ncol + 4) % 8));
        }
        $marr[(($row * $ncol) + $col)] = ((10 * $chr) + $bit);
        return $marr;
    }

    /**
     * Places the 8 bits of a utah-shaped symbol character.
     * (Annex F - ECC 200 symbol character placement)
     *
     * @param array $marr  Array of symbols.
     * @param int   $nrow  Number of rows.
     * @param int   $ncol  Number of columns.
     * @param int   $row   Row number.
     * @param int   $col   Column number.
     * @param int   $chr   Char byte.
     *
     * @return array
     */
    protected function placeUtah($marr, $nrow, $ncol, $row, $col, $chr)
    {
        $marr = $this->placeModule($marr, $nrow, $ncol, $row-2, $col-2, $chr, 1);
        $marr = $this->placeModule($marr, $nrow, $ncol, $row-2, $col-1, $chr, 2);
        $marr = $this->placeModule($marr, $nrow, $ncol, $row-1, $col-2, $chr, 3);
        $marr = $this->placeModule($marr, $nrow, $ncol, $row-1, $col-1, $chr, 4);
        $marr = $this->placeModule($marr, $nrow, $ncol, $row-1, $col, $chr, 5);
        $marr = $this->placeModule($marr, $nrow, $ncol, $row, $col-2, $chr, 6);
        $marr = $this->placeModule($marr, $nrow, $ncol, $row, $col-1, $chr, 7);
        $marr = $this->placeModule($marr, $nrow, $ncol, $row, $col, $chr, 8);
        return $marr;
    }

    /**
     * Places the 8 bits of the first special corner case.
     * (Annex F - ECC 200 symbol character placement)
     *
     * @param array $marr  Array of symbols
     * @param int   $nrow  Number of rows
     * @param int   $ncol  Number of columns
     * @param int   $chr   Char byte
     * @param int   $row   Current row
     * @param int   $col   Current column
     *
     * @return array
     */
    protected function placeCornerA($marr, $nrow, $ncol, &$chr, $row, $col)
    {
        if (($row != $nrow) || ($col != 0)) {
            return $marr;
        }
        $marr = $this->placeModule($marr, $nrow, $ncol, $nrow-1, 0, $chr, 1);
        $marr = $this->placeModule($marr, $nrow, $ncol, $nrow-1, 1, $chr, 2);
        $marr = $this->placeModule($marr, $nrow, $ncol, $nrow-1, 2, $chr, 3);
        $marr = $this->placeModule($marr, $nrow, $ncol, 0, $ncol-2, $chr, 4);
        $marr = $this->placeModule($marr, $nrow, $ncol, 0, $ncol-1, $chr, 5);
        $marr = $this->placeModule($marr, $nrow, $ncol, 1, $ncol-1, $chr, 6);
        $marr = $this->placeModule($marr, $nrow, $ncol, 2, $ncol-1, $chr, 7);
        $marr = $this->placeModule($marr, $nrow, $ncol, 3, $ncol-1, $chr, 8);
        ++$chr;
        return $marr;
    }

    /**
     * Places the 8 bits of the second special corner case.
     * (Annex F - ECC 200 symbol character placement)
     *
     * @param array $marr  Array of symbols
     * @param int   $nrow  Number of rows
     * @param int   $ncol  Number of columns
     * @param int   $chr   Char byte
     * @param int   $row   Current row
     * @param int   $col   Current column
     *
     * @return array
     */
    protected function placeCornerB($marr, $nrow, $ncol, &$chr, $row, $col)
    {
        if (($row != ($nrow - 2)) || ($col != 0) || (($ncol % 4) == 0)) {
            return $marr;
        }
        $marr = $this->placeModule($marr, $nrow, $ncol, $nrow-3, 0, $chr, 1);
        $marr = $this->placeModule($marr, $nrow, $ncol, $nrow-2, 0, $chr, 2);
        $marr = $this->placeModule($marr, $nrow, $ncol, $nrow-1, 0, $chr, 3);
        $marr = $this->placeModule($marr, $nrow, $ncol, 0, $ncol-4, $chr, 4);
        $marr = $this->placeModule($marr, $nrow, $ncol, 0, $ncol-3, $chr, 5);
        $marr = $this->placeModule($marr, $nrow, $ncol, 0, $ncol-2, $chr, 6);
        $marr = $this->placeModule($marr, $nrow, $ncol, 0, $ncol-1, $chr, 7);
        $marr = $this->placeModule($marr, $nrow, $ncol, 1, $ncol-1, $chr, 8);
        ++$chr;
        return $marr;
    }

    /**
     * Places the 8 bits of the third special corner case.
     * (Annex F - ECC 200 symbol character placement)
     *
     * @param array $marr  Array of symbols
     * @param int   $nrow  Number of rows
     * @param int   $ncol  Number of columns
     * @param int   $chr   Char byte
     * @param int   $row   Current row
     * @param int   $col   Current column
     *
     * @return array
     */
    protected function placeCornerC($marr, $nrow, $ncol, &$chr, $row, $col)
    {
        if (($row != ($nrow - 2)) || ($col != 0) || (($ncol % 8) != 4)) {
            return $marr;
        }
        $marr = $this->placeModule($marr, $nrow, $ncol, $nrow-3, 0, $chr, 1);
        $marr = $this->placeModule($marr, $nrow, $ncol, $nrow-2, 0, $chr, 2);
        $marr = $this->placeModule($marr, $nrow, $ncol, $nrow-1, 0, $chr, 3);
        $marr = $this->placeModule($marr, $nrow, $ncol, 0, $ncol-2, $chr, 4);
        $marr = $this->placeModule($marr, $nrow, $ncol, 0, $ncol-1, $chr, 5);
        $marr = $this->placeModule($marr, $nrow, $ncol, 1, $ncol-1, $chr, 6);
        $marr = $this->placeModule($marr, $nrow, $ncol, 2, $ncol-1, $chr, 7);
        $marr = $this->placeModule($marr, $nrow, $ncol, 3, $ncol-1, $chr, 8);
        ++$chr;
        return $marr;
    }

    /**
     * Places the 8 bits of the fourth special corner case.
     * (Annex F - ECC 200 symbol character placement)
     *
     * @param array $marr  Array of symbols
     * @param int   $nrow  Number of rows
     * @param int   $ncol  Number of columns
     * @param int   $chr   Char byte
     * @param int   $row   Current row
     * @param int   $col   Current column
     *
     * @return array
     */
    protected function placeCornerD($marr, $nrow, $ncol, &$chr, $row, $col)
    {
        if (($row != ($nrow + 4)) || ($col != 2) || ($ncol % 8)) {
            return $marr;
        }
        $marr = $this->placeModule($marr, $nrow, $ncol, $nrow-1, 0, $chr, 1);
        $marr = $this->placeModule($marr, $nrow, $ncol, $nrow-1, $ncol-1, $chr, 2);
        $marr = $this->placeModule($marr, $nrow, $ncol, 0, $ncol-3, $chr, 3);
        $marr = $this->placeModule($marr, $nrow, $ncol, 0, $ncol-2, $chr, 4);
        $marr = $this->placeModule($marr, $nrow, $ncol, 0, $ncol-1, $chr, 5);
        $marr = $this->placeModule($marr, $nrow, $ncol, 1, $ncol-3, $chr, 6);
        $marr = $this->placeModule($marr, $nrow, $ncol, 1, $ncol-2, $chr, 7);
        $marr = $this->placeModule($marr, $nrow, $ncol, 1, $ncol-1, $chr, 8);
        ++$chr;
        return $marr;
    }

    

    /**
     * Sweep upward diagonally, inserting successive characters,
     * (Annex F - ECC 200 symbol character placement)
     *
     * @param array $marr  Array of symbols
     * @param int   $nrow  Number of rows
     * @param int   $ncol  Number of columns
     * @param int   $chr   Char byte
     * @param int   $row   Current row
     * @param int   $col   Current column
     *
     * @return array
     */
    protected function placeSweepUpward($marr, $nrow, $ncol, &$chr, &$row, &$col)
    {
        do {
            if (($row < $nrow) && ($col >= 0) && (!$marr[(($row * $ncol) + $col)])) {
                $marr = $this->placeUtah($marr, $nrow, $ncol, $row, $col, $chr);
                ++$chr;
            }
            $row -= 2;
            $col += 2;
        } while (($row >= 0) && ($col < $ncol));
        ++$row;
        $col += 3;
        return $marr;
    }

    /**
     * Sweep downward diagonally, inserting successive characters,
     * (Annex F - ECC 200 symbol character placement)
     *
     * @param array $marr  Array of symbols
     * @param int   $nrow  Number of rows
     * @param int   $ncol  Number of columns
     * @param int   $chr   Char byte
     * @param int   $row   Current row
     * @param int   $col   Current column
     *
     * @return array
     */
    protected function placeSweepDownward($marr, $nrow, $ncol, &$chr, &$row, &$col)
    {
        do {
            if (($row >= 0) && ($col < $ncol) && (!$marr[(($row * $ncol) + $col)])) {
                $marr = $this->placeUtah($marr, $nrow, $ncol, $row, $col, $chr);
                ++$chr;
            }
            $row += 2;
            $col -= 2;
        } while (($row < $nrow) && ($col >= 0));
        $row += 3;
        ++$col;
        return $marr;
    }

    /**
     * Build a placement map.
     * (Annex F - ECC 200 symbol character placement)
     *
     * @param int $nrow  Number of rows.
     * @param int $ncol  Number of columns.
     *
     * @return array
     */
    public function getPlacementMap($nrow, $ncol)
    {
        // initialize array with zeros
        $marr = array_fill(0, ($nrow * $ncol), 0);
        // set starting values
        $chr = 1;
        $row = 4;
        $col = 0;
        do {
            // repeatedly first check for one of the special corner cases, then
            $marr = $this->placeCornerA($marr, $nrow, $ncol, $chr, $row, $col);
            $marr = $this->placeCornerB($marr, $nrow, $ncol, $chr, $row, $col);
            $marr = $this->placeCornerC($marr, $nrow, $ncol, $chr, $row, $col);
            $marr = $this->placeCornerD($marr, $nrow, $ncol, $chr, $row, $col);
            // sweep upward diagonally, inserting successive characters,
            $marr = $this->placeSweepUpward($marr, $nrow, $ncol, $chr, $row, $col);
            // & then sweep downward diagonally, inserting successive characters,...
            $marr = $this->placeSweepDownward($marr, $nrow, $ncol, $chr, $row, $col);
            // ... until the entire array is scanned
        } while (($row < $nrow) || ($col < $ncol));
        // lastly, if the lower righthand corner is untouched, fill in fixed pattern
        if (!$marr[(($nrow * $ncol) - 1)]) {
            $marr[(($nrow * $ncol) - 1)] = 1;
            $marr[(($nrow * $ncol) - $ncol - 2)] = 1;
        }
        return $marr;
    }
}
