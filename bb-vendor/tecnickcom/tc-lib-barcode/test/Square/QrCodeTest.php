<?php
/**
 * QrCodeTest.php
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
class QrCodeTest extends TestCase
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
        $this->obj->getBarcodeObj('QRCODE', '');
    }

    /**
     * @expectedException \Com\Tecnick\Barcode\Exception
     */
    public function testCapacityException()
    {
        $code = str_pad('', 4000, 'iVoo{[O17n~>(FXC9{*t1P532}l7E{7/R\' ObO`y?`9G(qjBmu7 GM3ZK!qp|)!P1" sRanqC(:Ky');
        $this->obj->getBarcodeObj('QRCODE', $code);
    }

    /**
     * @dataProvider getGridDataProvider
     */
    public function testGetGrid($options, $code, $expected)
    {
        $bobj = $this->obj->getBarcodeObj('QRCODE'.$options, $code);
        $grid = $bobj->getGrid();
        $this->assertEquals($expected, md5($grid));
    }

    public function getGridDataProvider()
    {
        return array(
            array('', '0123456789', '89e599523008751db7eef3b5befc37ed'),
            array(',L', '0123456789', '89e599523008751db7eef3b5befc37ed'),
            array(',H,NM', '0123456789', '3c4ecb6cc99b7843de8d2d3274a43d9e'),
            array(',L,8B,0,0', '123aeiouàèìòù', '1622068066c77d3e6ea0a3ad420d105c'),
            array(',H,KJ,0,0', 'ぎポ亊', '1d429dd6a1627f6dc1620b3f56862d52'),
            array(',H,ST,0,0', 'ABCdef0123', '3a8260f504bca8de8f55a7b3776080bb'),
            array('', str_pad('', 350, '0123456789'), '3cca7eb0f61bc39c5a79d7eb3e23a409'),
            array('', 'abcdefghijklmnopqrstuvwxyz01234567890123456789', '9c489cd7ded55a82b2d7e3589afbd7d0'),
            array(',H,AN,40,1,0,1,2',
                'abcdefghijklmnopqrstuvwxyz01234567890123456789',
                '5ba221be81b269ab1f105b07bf49b372'
            ),
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
                'd1977c58334ea034ef4201fe95ee4d2b'
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
                'f4bf6b42c6964562a3a91e054fc8ec51'
            ),
            array(
                ',H,NM',
                chr(26).chr(151).chr(224).chr(193).chr(71).chr(32).chr(162).chr(191).chr(30).chr(98).chr(82).chr(13)
                .chr(153).chr(145).chr(69).chr(3).chr(123).chr(227).chr(50).chr(200).chr(24).chr(234).chr(128).chr(81)
                .chr(232).chr(112).chr(147).chr(45).chr(25).chr(8).chr(38).chr(51).chr(158).chr(7).chr(243).chr(229)
                .chr(39).chr(150).chr(165).chr(69).chr(247).chr(246).chr(81).chr(146).chr(137).chr(149).chr(148).chr(6)
                .chr(122).chr(197),
                '4f6fd3799489b48fa07e1a7aef0561fc'
            ),
            array(
                ',H,AN',
                chr(205).chr(146).chr(176).chr(79).chr(226).chr(154).chr(191).chr(118).chr(198).chr(215).chr(126)
                .chr(236).chr(12).chr(29).chr(243).chr(254).chr(4).chr(27).chr(150).chr(168).chr(96).chr(142).chr(160)
                .chr(176).chr(34).chr(42).chr(71).chr(182).chr(48).chr(192).chr(125).chr(252).chr(84).chr(46).chr(77)
                .chr(55).chr(200).chr(13).chr(173).chr(144).chr(227).chr(44).chr(125).chr(238).chr(73).chr(113)
                .chr(238).chr(76).chr(140).chr(133),
                '55cd590ed76d12591c6df3b673904530'
            ),
            array(
                ',H,KJ',
                chr(244).chr(235).chr(21).chr(149).chr(157).chr(54).chr(191).chr(227).chr(235).chr(238).chr(165)
                .chr(105).chr(236).chr(248).chr(151).chr(58).chr(49).chr(97).chr(70).chr(221).chr(240).chr(43).chr(11)
                .chr(111).chr(27).chr(83).chr(223).chr(10).chr(159).chr(109).chr(142).chr(148).chr(89).chr(163).chr(42)
                .chr(246).chr(216).chr(233).chr(218).chr(197).chr(216).chr(129).chr(48).chr(197).chr(122).chr(199)
                .chr(1).chr(170).chr(41).chr(70),
                '92e82c296965d97d35ab7168ece11dd0'
            ),
            array(
                ',H,8B',
                chr(137).chr(27).chr(112).chr(147).chr(137).chr(138).chr(230).chr(106).chr(148).chr(134).chr(214)
                .chr(36).chr(27).chr(49).chr(198).chr(69).chr(40).chr(160).chr(47).chr(4).chr(103).chr(9).chr(133)
                .chr(150).chr(206).chr(254).chr(95).chr(206).chr(170).chr(136).chr(22).chr(53).chr(162).chr(134)
                .chr(199).chr(45).chr(18).chr(174).chr(150).chr(165).chr(54).chr(109).chr(201).chr(81).chr(158)
                .chr(144).chr(150).chr(198).chr(50).chr(196),
                '68799fdb9685b5e2f258245833006425'
            ),
            array(
                ',H,ST',
                chr(201).chr(152).chr(205).chr(79).chr(47).chr(157).chr(79).chr(142).chr(108).chr(249).chr(23).chr(130)
                .chr(47).chr(185).chr(9).chr(246).chr(229).chr(26).chr(166).chr(124).chr(191).chr(219).chr(233)
                .chr(137).chr(45).chr(137).chr(27).chr(194).chr(80).chr(76).chr(136).chr(27).chr(227).chr(87).chr(106)
                .chr(20).chr(243).chr(184).chr(161).chr(97).chr(179).chr(184).chr(226).chr(226).chr(114).chr(235)
                .chr(217).chr(88).chr(6).chr(129),
                '6fb328c418ea40c6c94277f420ba9357'
            ),
            array(
                '',
                'w(fa`nC]e=}OY(K^ 3xN1Vz1g<F=P%!H-h*nWNL>kKnFS;&TN $`W~r?;9\\l?]5MF@<~oh>\\4-#hH*=w*AYaAL!]f^J&<`Tc!'
                .'pcpZ"Nn0RWY\\uQf8+HZXJ8?*bFGDz+Eln7Gqe6"8n[te.\\}:&YrQq3[UY#yU.@B}Xio>!rWoNMV]*Uw0/kb!~>WYAR0PrROK'
                .'=?j>3B/boe@z;8,K$nM$-%]OWm KAOv^oa}#%-ets&p/?|[Dk)Hy.\'IfuI27y*viktmq#Tfv[X\'zUb?Bkh=zofbe1t|+~tuk'
                .'id]l9Edt}kpTO0w<x57h|yO.oM:oB1[-u:z/`_%_Lu4{\',9 BPi?K:M;gh,+yh8p#3!ds&D@|X=$eV%((oGS*uor^{}Ye6JhJ'
                .'LM>Sr^PK|T2SZ:[Jb0UX!I8}Grc^>L)jzG>n\'n:%DMX g5KKF!$GJ=Er0*QOVZ:R#YA+H\\0m*inr :>G;Cof`5Yq@,Avg\\J'
                .'j6lv_J(MUq<IrWg:s,*Zl@5_`B"X*^$utqlT<t#rg@<[w%uk1!G~A]^# `\\*?` 5RmiocmcL. (&&~r7 :6BwuFwW##wc#-7q'
                .'w(Uek#sl+zr*m*+)AN!8tyow|h\\!vssn|IUiMJVXEJc4To>v>?03:!+8ig9`\\-PZW\'D%Qz^wEC,z3JrQj#d&$p>nYXP6f!p'
                .'?)5EZ1$RWH_S[+F-vIr|Nc<==tmT\\oF{x\\ASnF\'FnfyKr@YijLg91$VOyD%V4KS-(tav;h>+P8VCY0.D]u^nz6?tZDLoo }'
                .'xt_p1I0=zIs?#%MHbD(R?>q4y7ai(ah"WIrir$\'nM{.P![yd "7@@*T:A7%IxmKP7?:+CAyp>)B?e<$e><\'_F\\yhs~2ll^>'
                .'%~X1Bz+494VYys^`2zhrBEl+9l>&Y}D_|}p@y|T32,m- Ln3HW&j|sv6`6=;5bz2alS[i(o{5]*6*xIRPP>NE6d&L#Abe=tG+H'
                .'$tQsqgQ{}\\tH0FyNt?eZE2]gtD&jl14p\'fdxO7uMskv$2pS(19bWLA@BeF-RXBD_*)YY@O\'5;~9NolV!\':YS[yuA@$tyF9'
                .'YPC3*cc2y~13N"!%$(@][{WR>xV4r|MMNp`YUDkPD|cr~ex#m9`J69}T2Th&R7S")4[_YG0~EtqDZkHI&*t<CrpVHZ\\zr0|{E'
                .'X[ !l~Faqk<]4fd?[!bNr:vMvr@(p=MJyMKfMrH?^e}sLb3)cuWV0O%(CF04c]_],,EY~ny^TwR"[e+@[cwl3|uWD&l(dLfqY,'
                .'LXzF7P?iRzO^<,B4yV2o.Vvz^[HFM"Ry[NAr~`]R\\.1x;S.5*@%9v|4VX]|\'_P";~C/~%mQa{c[77iMB+R["PpY)NV1/(3K('
                .'W[\'\\IY?E={]Uf+wq^Ts/EM9t%J$-]P65,=rUw2{6.ZktedgE:\\U`+nU09Z>w+T.8r!mk4j"CEn9+S!Qn]\'Ohu%y0`9)lm7'
                .'%a9sMN^Oq$?,0r.ablh2U_8PoxixeX1k;K_hy>9lBXxRL\'5/s~BJ^Z{OSfI:?[&[\'eD!$^mG8gzen1uc08/or+@Fria2FgnM'
                .'N3NRr=z+%uqt\'gY8 h(rtI:g4{zZdi(3}Wfpta|zXoo`WIxX3"L)Kgp_cl:IVB\\UyCGo&Ej^5[?m&8F::rCfZ4F?"`hX$F/~'
                .'iVoo{[O17n~>(FXC9{*t1P532}l7E{7/R\' ObO`y?`9G(qjBmu7 GM3ZK!qp|)!P1" sRanqC(:Ky&mh{&hDS|~ }qqzzrL,u'
                .'L!H/o:RwU}r[l\\XrE|FB{FAm9=i-iv#7wKFgfx`<wTxd1QWVN~yKF<9_Y$lDzo[r<#[${=Atq:Y#k2Z;1UfXq!8K%&p vMs3P'
                .'O7MlYB^s{b`/=|@rcxde21j9#k0P`C!0[N}5p]*m@k|^h>RM883KI~dMkt}L9 ]uN[,@:6/[",:jKl8c%L/OKs}7i{c#{BxK}%'
                .'k9<zt>(0*S}C7#oGS;<QS&N8)KZ"vY(crD_hchxm<v1Tz!{N=9!p?P*H{dKs>TW2x8z]!sK=k]rf',
                '83747986cf0df320b915587609232076'
            ),
        );
    }

    /**
     * @dataProvider getStringDataProvider
     */
    public function testStrings($code)
    {
        $bobj = $this->obj->getBarcodeObj('QRCODE,H,NL,0,1,3,1', $code);
        $this->assertNotNull($bobj);
    }

    public function getStringDataProvider()
    {
        return \Test\TestStrings::$data;
    }
}
