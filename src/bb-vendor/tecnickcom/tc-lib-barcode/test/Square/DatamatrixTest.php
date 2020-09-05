<?php
/**
 * DatamatrixTest.php
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2020 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 *
 * This file is part of tc-lib-barcode software library.
 */

namespace Test\Square;

use PHPUnit\Framework\TestCase;

/**
 * Barcode class test
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class DatamatrixTest extends TestCase
{
    protected $obj = null;

    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
        $this->obj = new \Com\Tecnick\Barcode\Barcode;
    }

    /**
     * @expectedException \Com\Tecnick\Barcode\Exception
     */
    public function testInvalidInput()
    {
        $this->obj->getBarcodeObj('DATAMATRIX', '');
    }

    /**
     * @expectedException \Com\Tecnick\Barcode\Exception
     */
    public function testCapacityException()
    {
        $code = str_pad('', 3000, 'X');
        $this->obj->getBarcodeObj('DATAMATRIX', $code);
    }
 
    /**
     * @expectedException \Com\Tecnick\Barcode\Exception
     */
    public function testEncodeTXTC40shiftException()
    {
        $obj = new \Com\Tecnick\Barcode\Type\Square\Datamatrix\Encode();
        $chr = null;
        $enc = null;
        $temp_cw = null;
        $ptr = null;
        $obj->encodeTXTC40shift($chr, $enc, $temp_cw, $ptr);
    }
 
    /**
     * @expectedException \Com\Tecnick\Barcode\Exception
     */
    public function testEncodeTXTC40Exception()
    {
        $obj = new \Com\Tecnick\Barcode\Type\Square\Datamatrix\Encode();
        $data = array(chr(0x80));
        $enc = \Com\Tecnick\Barcode\Type\Square\Datamatrix\Data::ENC_X12;
        $temp_cw = null;
        $ptr = null;
        $epos = 0;
        $charset = null;
        $obj->encodeTXTC40($data, $enc, $temp_cw, $ptr, $epos, $charset);
    }

    /**
     * @dataProvider getGridDataProvider
     */
    public function testGetGrid($mode, $code, $expected)
    {
        $bobj = $this->obj->getBarcodeObj($mode, $code);
        $grid = $bobj->getGrid();
        $this->assertEquals($expected, md5($grid));
    }
    
    public function getGridDataProvider()
    {
        return array(
            array('DATAMATRIX', '(400)BS2WZ64PA(00)0', '183514ca2f0465170de1d404a5d7dabd'),
            array('DATAMATRIX', '(400)BS2WZ64QA(00)0', '4293cb60df5ca208922b6f4ce65dbb7c'),
            array('DATAMATRIX', 'LD2B 1 CLNGP', 'f806889d1dbe0908dcfb530f86098041'),
            array('DATAMATRIX', 'XXXXXXXXXNGP', 'c6f2b7b293a2943bae74f2a191ec4aea'),
            array('DATAMATRIX', 'XXXXXXXXXXXXNGP', 'f7679d5a7ab4a8edf12571a6866d92bc'),
            array('DATAMATRIX', 'ABCDABCDAB'.chr(128).'DABCD', '39aca5ed58b922bee369e5ab8e3add8c'),
            array('DATAMATRIX', '123aabcdefghijklmnopqrstuvwxyzc', 'b2d1e957af10655d7a8c3bae86696314'),
            array('DATAMATRIX', 'abcdefghijklmnopqrstuvwxyzabcdefghijklmnopq', 'c45bd372694ad7a20fca7d45f3d459ab'),
            array('DATAMATRIX', 'abcdefghijklmnop', '4fc7940fe3d19fca12454340c38e3421'),
            array('DATAMATRIX', 'abcdefghijklmnopq', 'a452e658e3096d8187969cbdc930909c'),
            array('DATAMATRIX', 'abcdefghij', '8ec27153e5d173aa2cb907845334e68c'),
            array('DATAMATRIX', '30Q324343430794<OQQ', 'e67808f91114fb021851098c4cc65b88'),
            array('DATAMATRIX', '0123456789', 'cc1fd942bc919b2d09b3c7cf508c6ae4'),
            array('DATAMATRIX', 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX', 'c61d8ced313e2a2e79ab56eded67f11a'),
            array('DATAMATRIX', '10f27ce-acb7-4e4e-a7ae-a0b98da6ed4a', '1a56c44e3977f1ac68057230181e49a8'),
            array('DATAMATRIX', 'Hello World', 'e72650689027fe75d1f9377ec759c710'),
            array('DATAMATRIX', 'https://github.com/tecnickcom/tc-lib-barcode', 'efed64acfa2ca29024446fa9816be696'),
            array(
                'DATAMATRIX',
                'abcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcd'
                .'abcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcdabcd',
                '4dc0efb6248b3802c2ab7cf123b884d0'
            ),
            array(
                'DATAMATRIX',
                'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890!@#$%^&*(),./\\',
                '1d41ee32691ff75637224e4fbe68a626'),
            array(
                'DATAMATRIX',
                'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890!@#$%^&*(),./\\'
                .'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890!@#$%^&*(),./\\'
                .'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890!@#$%^&*(),./\\',
                '0b2921466e097ff9cc1ad63719430540'
            ),
            array('DATAMATRIX', chr(128).chr(138).chr(148).chr(158), '9300000cee5a5f7b3b48145d44aa7fff'),
            array('DATAMATRIX', '!"£$%^&*()-+_={}[]\'#@~;:/?,.<>|', '4993e149fd20569c8a4f0d758b6dfa76'),
            array('DATAMATRIX', '!"£$', '792181edb48c6722217dc7e2e4cd4095'),
            array(
                'DATAMATRIX',
                'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890!@#$%^&*(),./\\1234567890',
                '4744c06c576088b40b3523c7d27cf051'
            ),
            array(
                'DATAMATRIX', chr(254).chr(253)
                .'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890!@#$%^&*(),./\\'
                .chr(252).chr(251),
                '0f078e5e5735396312245740484fa6d1'
            ),
            array('DATAMATRIX', 'aABCDEFG', 'f074dee3f0f386d9b2f30b1ce4ad08a8'),
            array('DATAMATRIX', '123 45678', '6c2e6503625e408fe9a4e392743f31a8'),
            array('DATAMATRIX', 'DATA MATRIX', '3ba4f4ef8449d795813b353ddcce4d23'),
            array('DATAMATRIX', '123ABCD89', '7ce2f8433b82c16e80f4a4c59cad5d10'),
            array('DATAMATRIX', 'AB/C123-X', '703318e1964c63d5d500d14a821827cd'),
            array('DATAMATRIX',
                str_pad('', 300, chr(254).chr(253).chr(252).chr(251)),
                'e524bb17821d0461f3db6f313d35018f'),
            array('DATAMATRIX', 'ec:b47'.chr(127).'4#P d*b}gI2#DB|hl{!~[EYH*=cmR{lf'
                .chr(127).'=gcGIa.st286. #*"!eG[.Ryr?Kn,1mIyQqC3 6\'3N>',
                '6d12a9d2d36f76667d56f270649232b0'
            ),
            array('DATAMATRIX', 'eA211101A2raJTGL/r9o93CVk4gtpEvWd2A2Qz8jvPc7l8ybD3m'
                .'Wel91ih727kldinPeHJCjhr7fIBX1KQQfsN7BFMX00nlS8FlZG+',
                'b2f0d45920c7da5b298bbab5cff5d402'
            ),
            // Square
            array(
                'DATAMATRIX,S',
                chr(255).chr(254).chr(253).chr(252).chr(251).chr(250).chr(249).chr(248).chr(247).chr(246).chr(245)
                .chr(244).chr(243).chr(242).chr(241).chr(240).chr(239).chr(238).chr(237).chr(236).chr(235).chr(234)
                .chr(233).chr(232).chr(231).chr(230).chr(229).chr(228).chr(227).chr(226).chr(225).chr(224).chr(223)
                .chr(222).chr(221).chr(220).chr(219).chr(218).chr(217).chr(216).chr(215).chr(214).chr(213).chr(212)
                .chr(211).chr(210).chr(209).chr(208).chr(207).chr(206).chr(205).chr(204).chr(203).chr(202).chr(201)
                .chr(200).chr(199).chr(198).chr(197).chr(196).chr(195).chr(194).chr(193).chr(192).chr(191).chr(190)
                .chr(189).chr(188).chr(187).chr(186).chr(185).chr(184).chr(183).chr(182).chr(181).chr(180).chr(179)
                .chr(178).chr(177).chr(176).chr(175).chr(174).chr(173).chr(172).chr(171).chr(170).chr(169).chr(168)
                .chr(167).chr(166).chr(165).chr(164).chr(163).chr(162).chr(161).chr(160).chr(159).chr(158).chr(157)
                .chr(156).chr(155).chr(154).chr(153).chr(152).chr(151).chr(150).chr(149).chr(148).chr(147).chr(146)
                .chr(145).chr(144).chr(143).chr(142).chr(141).chr(140).chr(139).chr(138).chr(137).chr(136).chr(135)
                .chr(134).chr(133).chr(132).chr(131).chr(130).chr(129).chr(128).chr(127).chr(126).chr(125).chr(124)
                .chr(123).chr(122).chr(121).chr(120).chr(119).chr(118).chr(117).chr(116).chr(115).chr(114).chr(113)
                .chr(112).chr(111).chr(110).chr(109).chr(108).chr(107).chr(106).chr(105).chr(104).chr(103).chr(102)
                .chr(101).chr(100).chr(99).chr(98).chr(97).chr(96).chr(95).chr(94).chr(93).chr(92).chr(91).chr(90)
                .chr(89).chr(88).chr(87).chr(86).chr(85).chr(84).chr(83).chr(82).chr(81).chr(80).chr(79).chr(78)
                .chr(77).chr(76).chr(75).chr(74).chr(73).chr(72).chr(71).chr(70).chr(69).chr(68).chr(67).chr(66)
                .chr(65).chr(64).chr(63).chr(62).chr(61).chr(60).chr(59).chr(58).chr(57).chr(56).chr(55).chr(54)
                .chr(53).chr(52).chr(51).chr(50).chr(49).chr(48).chr(47).chr(46).chr(45).chr(44).chr(43).chr(42)
                .chr(41).chr(40).chr(39).chr(38).chr(37).chr(36).chr(35).chr(34).chr(33).chr(32).chr(31).chr(30)
                .chr(29).chr(28).chr(27).chr(26).chr(25).chr(24).chr(23).chr(22).chr(21).chr(20).chr(19).chr(18)
                .chr(17).chr(16).chr(15).chr(14).chr(13).chr(12).chr(11).chr(10).chr(9).chr(8).chr(7).chr(6)
                .chr(5).chr(4).chr(3).chr(2).chr(1),
                '9dccdf9b0b6d99c7d420af5540a9edfc'
            ),
            // Rectangular shape
            array('DATAMATRIX,R', '01234567890', 'd3811e018f960beed6d3fa5e675e290e'),
            array('DATAMATRIX,R', '01234567890123456789', 'fe3ecb042dabc4b40c5017e204df105b'),
            array('DATAMATRIX,R', '012345678901234567890123456789', '3f8e9aa4413b90f7e1c2e85b4471fd20'),
            array('DATAMATRIX,R', '0123456789012345678901234567890123456789', 'b748b02c1c4cae621a84c8dbba97c710'),
            // Rectangular GS1
            array('DATAMATRIX,R,GS1',
                chr(232).'01034531200000111719112510ABCD1234',
                'f55524d239fc95072d99eafe5363cfeb'),
            array('DATAMATRIX,R,GS1',
                chr(232).'01095011010209171719050810ABCD1234'.chr(232).'2110',
                'e17f2a052271a18cdc00b161908eccb9'),
            array('DATAMATRIX,R,GS1',
                chr(232).'01034531200000111712050810ABCD1234'.chr(232).'4109501101020917',
                '31759950f3253805b100fedf3e536575'),
            // Square GS1
            array('DATAMATRIX,S,GS1',
                chr(232).'01034531200000111719112510ABCD1234',
                'c9efb69a62114fb6a3d2b52f139a372a'),
            array('DATAMATRIX,S,GS1',
                chr(232).'01095011010209171719050810ABCD1234'.chr(232).'2110',
                '9630bdba9fc79b4a4911fc465aa08951'),
            array('DATAMATRIX,S,GS1',
                chr(232).'01034531200000111712050810ABCD1234'.chr(232).'4109501101020917',
                'a29a330a01cce34a346cf7049e2259ee'),
        );
    }

    /**
     * @dataProvider getStringDataProvider
     */
    public function testStrings($code)
    {
        $bobj = $this->obj->getBarcodeObj('DATAMATRIX', $code);
        $this->assertNotNull($bobj);
    }

    public function getStringDataProvider()
    {
        return \Test\TestStrings::$data;
    }
}
