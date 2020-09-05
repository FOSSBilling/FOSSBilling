<?php
/**
 * PdfFourOneSevenTest.php
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
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
class PdfFourOneSevenTest extends TestCase
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
        $this->obj->getBarcodeObj('PDF417', '');
    }

    /**
     * @expectedException \Com\Tecnick\Barcode\Exception
     */
    public function testCapacityException()
    {
        $code = str_pad('', 1000, 'X1');
        $this->obj->getBarcodeObj('PDF417', $code);
    }

    /**
     * @dataProvider getGridDataProvider
     */
    public function testGetGrid($options, $code, $expected)
    {
        $bobj = $this->obj->getBarcodeObj('PDF417'.$options, $code);
        $grid = $bobj->getGrid();
        $this->assertEquals($expected, md5($grid));
    }

    public function getGridDataProvider()
    {
        return array(
            array('', str_pad('', 1850, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'), '38e205c911b94a62c72b7d20fa4361f8'), // max text
            array('', str_pad('', 2710, '123456789'), '32ba9be56f3e66559b4d4a50f6276da7'), // max digits
            array('', 'abc/abc', '831874fe7d1b3d865c222858eba3507c'),
            array('', '0123456789', '4f9cdac81d62f0020beb93fc3ecdd8ad'),
            array(',2,8,1,0,0,0,1,2', str_pad('', 1750, 'X'), 'f0874a35e15f11f9aa8bc070a4be24bf'),
            array(',15,8,1,0,0,0,1,2', str_pad('', 1750, 'X'), '0288f0a87cc069fc34d6168d7a9f7846'),
            array('', str_pad('', 350, '0123456789'), '394d93048831fee232413da29fb709fb'),
            array('', 'abcdefghijklmnopqrstuvwxyz01234567890123456789', 'bd4f4215aca0bbc3452a35b81fcf7bdb'),
            array(
                '',
                chr(158).chr(19).chr(192).chr(8).chr(71).chr(113).chr(107).chr(252).chr(171).chr(169).chr(114)
                .chr(114).chr(204).chr(151).chr(183).chr(20).chr(180).chr(26).chr(73).chr(76).chr(193).chr(16)
                .chr(69).chr(212).chr(232).chr(90).chr(248).chr(115).chr(9).chr(104).chr(149).chr(167).chr(123)
                .chr(86).chr(175).chr(193).chr(199).chr(27).chr(190).chr(115).chr(196).chr(50).chr(228).chr(146)
                .chr(201).chr(156).chr(165).chr(126).chr(182).chr(237).chr(201).chr(121).chr(253).chr(15).chr(78)
                .chr(231).chr(105).chr(72).chr(92).chr(114).chr(175).chr(240).chr(26).chr(43).chr(71).chr(200)
                .chr(236).chr(15).chr(227).chr(172).chr(129).chr(169).chr(221).chr(103).chr(60).chr(167).chr(5)
                .chr(225).chr(39).chr(186).chr(208).chr(240).chr(52).chr(206).chr(254).chr(130).chr(183).chr(105)
                .chr(201).chr(20).chr(218).chr(122).chr(5).chr(244).chr(165).chr(76).chr(189).chr(146).chr(91)
                .chr(162).chr(63).chr(220).chr(76).chr(30).chr(68).chr(135).chr(196).chr(73).chr(106).chr(235)
                .chr(5).chr(59).chr(220).chr(56).chr(11).chr(220).chr(186).chr(194).chr(70).chr(132).chr(213)
                .chr(34).chr(254).chr(218).chr(23).chr(164).chr(40).chr(212).chr(56).chr(130).chr(119).chr(118)
                .chr(95).chr(194).chr(148).chr(163).chr(75).chr(90).chr(236).chr(180).chr(70).chr(240).chr(239)
                .chr(35).chr(42).chr(250).chr(254).chr(227).chr(189).chr(70).chr(105).chr(148).chr(103).chr(104)
                .chr(112).chr(126).chr(13).chr(151).chr(83).chr(68).chr(27).chr(201).chr(186).chr(121).chr(141)
                .chr(80).chr(30).chr(215).chr(169).chr(12).chr(141).chr(238).chr(251).chr(126).chr(18).chr(39)
                .chr(121).chr(18).chr(12).chr(56).chr(88).chr(116).chr(203).chr(190).chr(220).chr(60).chr(61)
                .chr(233).chr(211).chr(144).chr(47).chr(237).chr(90).chr(232).chr(104).chr(230).chr(57).chr(134)
                .chr(191).chr(226).chr(145).chr(77).chr(209).chr(142).chr(202).chr(227).chr(180).chr(69).chr(245)
                .chr(191).chr(124).chr(78).chr(53).chr(73).chr(13).chr(18).chr(133).chr(74).chr(250).chr(89)
                .chr(217).chr(42).chr(71).chr(53).chr(20).chr(175).chr(29).chr(77).chr(54).chr(219).chr(48).chr(198)
                .chr(41).chr(3).chr(85).chr(243).chr(229).chr(11).chr(57).chr(219).chr(201).chr(180).chr(43).chr(253)
                .chr(252).chr(56).chr(17).chr(131).chr(129).chr(12).chr(219).chr(92).chr(54).chr(36).chr(145).chr(74)
                .chr(210).chr(173).chr(151).chr(9).chr(137).chr(198).chr(207).chr(178).chr(201).chr(38).chr(166)
                .chr(175).chr(48).chr(223).chr(140).chr(249).chr(149).chr(182).chr(248).chr(147).chr(237).chr(10)
                .chr(23).chr(112).chr(22).chr(241).chr(204).chr(76).chr(23).chr(94).chr(150).chr(232).chr(13).chr(46)
                .chr(241).chr(149).chr(243).chr(193).chr(73).chr(190).chr(230).chr(239).chr(110).chr(24),
                '1cae7ca47cde6ca52522ce31771a5c54'
            ),
            array(
                '',
                chr(158).chr(198).chr(45).chr(94).chr(231).chr(83).chr(60).chr(45).chr(104).chr(62).chr(122).chr(88)
                .chr(245).chr(8).chr(21).chr(76).chr(170).chr(250).chr(87).chr(6).chr(162).chr(32).chr(43).chr(208)
                .chr(17).chr(96).chr(139).chr(43).chr(192).chr(15).chr(57).chr(95).chr(212).chr(102).chr(189).chr(188)
                .chr(184).chr(249).chr(233).chr(34).chr(56).chr(101).chr(122).chr(47).chr(108).chr(142).chr(122)
                .chr(24).chr(137).chr(209).chr(30).chr(45).chr(240).chr(72).chr(253).chr(2).chr(167).chr(138).chr(44)
                .chr(105).chr(152).chr(101).chr(200).chr(109).chr(202).chr(135).chr(43).chr(132).chr(129).chr(21)
                .chr(165).chr(185).chr(121).chr(32).chr(231).chr(229).chr(174).chr(99).chr(252).chr(57).chr(53)
                .chr(27).chr(101).chr(38).chr(99).chr(100).chr(39).chr(12).chr(237).chr(83).chr(116).chr(134).chr(183)
                .chr(62).chr(243).chr(131).chr(196).chr(31).chr(8).chr(70).chr(52).chr(172).chr(254).chr(173).chr(204)
                .chr(231).chr(147).chr(124).chr(75).chr(145).chr(180).chr(128).chr(172).chr(27).chr(165).chr(16)
                .chr(126).chr(204).chr(27).chr(109).chr(33).chr(143).chr(242).chr(216).chr(204).chr(231).chr(92)
                .chr(145).chr(7).chr(99).chr(214).chr(59).chr(17).chr(214).chr(231).chr(221).chr(190).chr(124).chr(90)
                .chr(11).chr(14).chr(16).chr(138).chr(186).chr(43).chr(49).chr(201).chr(168).chr(252).chr(227).chr(22)
                .chr(31).chr(116).chr(10).chr(246).chr(65).chr(240).chr(83).chr(209).chr(247).chr(182).chr(169)
                .chr(52).chr(199).chr(129).chr(29).chr(165).chr(65).chr(152).chr(1).chr(75).chr(166).chr(17).chr(212)
                .chr(97).chr(59).chr(7).chr(44).chr(227).chr(4).chr(17).chr(249).chr(35).chr(132).chr(5).chr(26)
                .chr(196).chr(244).chr(108).chr(151).chr(237).chr(36).chr(66).chr(34).chr(234).chr(194).chr(63)
                .chr(145).chr(4).chr(214).chr(146).chr(79).chr(126).chr(162).chr(36).chr(222).chr(220).chr(42)
                .chr(12).chr(193).chr(46).chr(28).chr(187).chr(80).chr(159).chr(191).chr(106).chr(100).chr(181)
                .chr(213).chr(251).chr(164).chr(249).chr(62).chr(198).chr(228).chr(2).chr(6).chr(119).chr(5).chr(220)
                .chr(10).chr(83).chr(91).chr(171).chr(119).chr(59).chr(137).chr(161).chr(70).chr(75).chr(207).chr(97)
                .chr(8).chr(33).chr(1).chr(198).chr(138).chr(101).chr(125).chr(97).chr(97).chr(34).chr(91).chr(159)
                .chr(231).chr(65).chr(160).chr(237).chr(183).chr(165).chr(202).chr(192).chr(248).chr(38).chr(108)
                .chr(112).chr(96).chr(245).chr(19).chr(166).chr(65).chr(225).chr(8).chr(72).chr(4).chr(9).chr(16)
                .chr(141).chr(109).chr(141).chr(237).chr(206).chr(174).chr(73).chr(111).chr(151).chr(138).chr(16)
                .chr(133).chr(66).chr(180).chr(81).chr(3).chr(173).chr(118).chr(111).chr(31).chr(214).chr(101).chr(49)
                .chr(125).chr(166).chr(20).chr(133).chr(238).chr(23).chr(141).chr(254).chr(163).chr(250).chr(140)
                .chr(146).chr(202).chr(60).chr(219).chr(58).chr(211).chr(102).chr(73).chr(90).chr(167).chr(253)
                .chr(170).chr(170).chr(172).chr(34).chr(27).chr(202).chr(247).chr(128).chr(251).chr(117).chr(39)
                .chr(17).chr(249).chr(23).chr(39).chr(136).chr(22).chr(202).chr(131).chr(162).chr(94).chr(78).chr(222)
                .chr(58).chr(136).chr(178).chr(159).chr(209).chr(14).chr(72).chr(207).chr(183).chr(242).chr(124)
                .chr(217).chr(14).chr(72).chr(209).chr(141).chr(69).chr(72).chr(180).chr(85).chr(67).chr(202).chr(124)
                .chr(202).chr(224).chr(72).chr(79).chr(131).chr(165).chr(157).chr(99).chr(223).chr(38).chr(23)
                .chr(128).chr(246).chr(36).chr(199).chr(199).chr(219).chr(187).chr(69).chr(181).chr(200).chr(140)
                .chr(136).chr(86).chr(208).chr(207).chr(11).chr(39).chr(19).chr(213).chr(162).chr(221).chr(182)
                .chr(233).chr(46).chr(59).chr(144).chr(202).chr(157).chr(112).chr(240).chr(179).chr(240).chr(231)
                .chr(215).chr(185).chr(176).chr(180).chr(117).chr(244).chr(106).chr(62).chr(129).chr(242).chr(148)
                .chr(83).chr(194).chr(159).chr(121).chr(213).chr(117).chr(29).chr(179).chr(44).chr(8).chr(224)
                .chr(103).chr(151).chr(172).chr(5).chr(9).chr(157).chr(184).chr(248).chr(134).chr(145).chr(179)
                .chr(55).chr(70).chr(41).chr(44).chr(176).chr(102).chr(173).chr(163).chr(250).chr(2).chr(103).chr(154)
                .chr(123).chr(61).chr(16).chr(151).chr(240).chr(60).chr(159).chr(210).chr(162).chr(55).chr(127)
                .chr(167).chr(64).chr(30).chr(97).chr(58).chr(163).chr(241).chr(236).chr(218).chr(57).chr(22).chr(8)
                .chr(233).chr(124).chr(180).chr(141).chr(119).chr(182).chr(243).chr(18).chr(51).chr(50).chr(34)
                .chr(201).chr(35).chr(93).chr(106).chr(244).chr(1).chr(161).chr(116).chr(167).chr(224).chr(146).chr(9)
                .chr(28).chr(54).chr(250).chr(10).chr(17).chr(53).chr(32).chr(24).chr(31).chr(155).chr(204).chr(172)
                .chr(20).chr(132).chr(160).chr(38).chr(182).chr(209).chr(72).chr(129).chr(243).chr(164).chr(234)
                .chr(233).chr(164).chr(140).chr(95).chr(77).chr(110).chr(240).chr(86).chr(137).chr(39).chr(81)
                .chr(146).chr(56).chr(134).chr(177).chr(80).chr(164).chr(78).chr(30).chr(81).chr(98).chr(161).chr(241)
                .chr(135).chr(88).chr(196).chr(206).chr(216).chr(185).chr(116).chr(195).chr(163).chr(26).chr(81)
                .chr(3).chr(102).chr(190).chr(243).chr(187).chr(72).chr(28).chr(14).chr(218).chr(83).chr(147).chr(141)
                .chr(163).chr(57).chr(218).chr(192).chr(137).chr(61).chr(98).chr(124).chr(196).chr(186).chr(65)
                .chr(148).chr(147).chr(249).chr(9).chr(88).chr(157).chr(34).chr(168).chr(160).chr(135).chr(103)
                .chr(148).chr(68).chr(175).chr(176).chr(81).chr(138).chr(4).chr(228).chr(25).chr(167).chr(30).chr(242)
                .chr(104).chr(167).chr(49).chr(202).chr(36).chr(244).chr(133).chr(100).chr(138).chr(26).chr(94)
                .chr(146).chr(113).chr(251).chr(180).chr(27).chr(157).chr(61).chr(129).chr(51).chr(128).chr(50)
                .chr(226).chr(209).chr(188).chr(230).chr(182).chr(212).chr(142).chr(212).chr(200).chr(246).chr(124)
                .chr(248).chr(193).chr(159).chr(238).chr(71).chr(4).chr(121).chr(96).chr(98).chr(13).chr(209).chr(95)
                .chr(193).chr(235).chr(251).chr(253).chr(110).chr(47).chr(127).chr(159).chr(18).chr(81).chr(93)
                .chr(247).chr(9).chr(50).chr(135).chr(220).chr(249).chr(126).chr(90).chr(243).chr(64).chr(248)
                .chr(227).chr(135).chr(252).chr(94).chr(231).chr(96).chr(106).chr(186).chr(190).chr(44).chr(166)
                .chr(187).chr(43).chr(21).chr(233).chr(169).chr(180).chr(251).chr(250).chr(18).chr(244).chr(5).chr(68)
                .chr(124).chr(225).chr(63).chr(250).chr(60).chr(52).chr(59).chr(53).chr(24).chr(194).chr(51).chr(117)
                .chr(171).chr(146).chr(223).chr(102).chr(82).chr(12).chr(13).chr(14).chr(54).chr(34).chr(246).chr(223)
                .chr(214).chr(243).chr(218).chr(232).chr(232).chr(222).chr(45).chr(102).chr(192).chr(108).chr(97)
                .chr(252).chr(159).chr(156).chr(50).chr(183).chr(96).chr(101).chr(45).chr(12).chr(246).chr(13)
                .chr(113).chr(73).chr(25).chr(126).chr(87).chr(79).chr(160).chr(78).chr(47).chr(119).chr(67).chr(11)
                .chr(96).chr(45).chr(233).chr(141).chr(146).chr(171).chr(249).chr(242).chr(168).chr(153).chr(143)
                .chr(218).chr(81).chr(238).chr(64).chr(126).chr(250).chr(55).chr(139).chr(109).chr(128).chr(163)
                .chr(234).chr(214).chr(242).chr(139).chr(38).chr(34).chr(4).chr(104).chr(45).chr(100).chr(148).chr(23)
                .chr(241).chr(40).chr(194).chr(235).chr(27).chr(107).chr(133).chr(170).chr(70).chr(214).chr(154)
                .chr(133).chr(86).chr(149).chr(188).chr(224).chr(3).chr(61).chr(133).chr(237).chr(21).chr(121)
                .chr(122).chr(59).chr(154).chr(125).chr(163).chr(199).chr(225).chr(56).chr(222).chr(211).chr(96)
                .chr(161).chr(191).chr(122).chr(13).chr(70).chr(38).chr(82).chr(30).chr(191).chr(215).chr(115).chr(86)
                .chr(148).chr(85).chr(89).chr(209).chr(218).chr(71).chr(230).chr(84).chr(193).chr(34).chr(238).chr(63)
                .chr(196).chr(182).chr(34).chr(252).chr(149).chr(244).chr(93).chr(55).chr(180).chr(215).chr(68)
                .chr(249).chr(252).chr(150).chr(24).chr(189).chr(110).chr(139).chr(21).chr(4).chr(223).chr(109)
                .chr(213).chr(186).chr(180).chr(188).chr(16).chr(118).chr(221).chr(253).chr(181).chr(163).chr(180)
                .chr(214).chr(160).chr(75).chr(203).chr(252).chr(130).chr(129).chr(213).chr(197).chr(124).chr(210)
                .chr(92).chr(148).chr(145).chr(202).chr(32).chr(166).chr(205).chr(1).chr(21).chr(164).chr(187)
                .chr(200).chr(97).chr(202).chr(64).chr(64).chr(200).chr(245).chr(226).chr(126).chr(205).chr(132)
                .chr(201).chr(154).chr(130).chr(76).chr(28).chr(88).chr(18).chr(152).chr(44).chr(110).chr(45).chr(188)
                .chr(57).chr(76).chr(100).chr(8).chr(76).chr(120).chr(172).chr(9).chr(65).chr(14).chr(210).chr(129)
                .chr(78).chr(156).chr(120).chr(50).chr(28).chr(70).chr(181).chr(228).chr(223).chr(56).chr(49).chr(251)
                .chr(143).chr(67).chr(148).chr(187).chr(176).chr(192).chr(120).chr(233).chr(14).chr(219).chr(241)
                .chr(90).chr(85).chr(158).chr(98).chr(150).chr(172).chr(54).chr(24).chr(249).chr(209).chr(143).chr(45)
                .chr(236).chr(213).chr(225).chr(210).chr(182).chr(27).chr(4).chr(179).chr(169).chr(70).chr(72)
                .chr(101).chr(246).chr(10).chr(221).chr(225).chr(24).chr(186).chr(212).chr(113).chr(16).chr(115)
                .chr(211).chr(165).chr(33).chr(10).chr(189).chr(28).chr(219),
                '6c1033648fc11250ad22006398cd1bdc'
            ),
        );
    }

    /**
     * @dataProvider getStringDataProvider
     */
    public function testStrings($code)
    {
        $bobj = $this->obj->getBarcodeObj('PDF417', $code);
        $this->assertNotNull($bobj);
    }

    public function getStringDataProvider()
    {
        return \Test\TestStrings::$data;
    }
}
