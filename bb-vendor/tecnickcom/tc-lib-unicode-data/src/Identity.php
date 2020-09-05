<?php
/**
 * Identity.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     UnicodeData
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode-data
 *
 * This file is part of tc-lib-unicode-data software library.
 */

namespace Com\Tecnick\Unicode\Data;

/**
 * Com\Tecnick\Unicode\Data\Identity
 *
 * @since       2011-05-23
 * @category    Library
 * @package     UnicodeData
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode-data
 */
class Identity
{
    /**
     * ToUnicode map for Identity-H stream
     */
    const CIDHMAP = <<<EOD
/CIDInit /ProcSet findresource begin
12 dict begin
begincmap
/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def
/CMapName /Adobe-Identity-UCS def
/CMapType 2 def
/WMode 0 def
1 begincodespacerange
<0000> <FFFF>
endcodespacerange
100 beginbfrange
<0000> <00ff> <0000>
<0100> <01ff> <0100>
<0200> <02ff> <0200>
<0300> <03ff> <0300>
<0400> <04ff> <0400>
<0500> <05ff> <0500>
<0600> <06ff> <0600>
<0700> <07ff> <0700>
<0800> <08ff> <0800>
<0900> <09ff> <0900>
<0a00> <0aff> <0a00>
<0b00> <0bff> <0b00>
<0c00> <0cff> <0c00>
<0d00> <0dff> <0d00>
<0e00> <0eff> <0e00>
<0f00> <0fff> <0f00>
<1000> <10ff> <1000>
<1100> <11ff> <1100>
<1200> <12ff> <1200>
<1300> <13ff> <1300>
<1400> <14ff> <1400>
<1500> <15ff> <1500>
<1600> <16ff> <1600>
<1700> <17ff> <1700>
<1800> <18ff> <1800>
<1900> <19ff> <1900>
<1a00> <1aff> <1a00>
<1b00> <1bff> <1b00>
<1c00> <1cff> <1c00>
<1d00> <1dff> <1d00>
<1e00> <1eff> <1e00>
<1f00> <1fff> <1f00>
<2000> <20ff> <2000>
<2100> <21ff> <2100>
<2200> <22ff> <2200>
<2300> <23ff> <2300>
<2400> <24ff> <2400>
<2500> <25ff> <2500>
<2600> <26ff> <2600>
<2700> <27ff> <2700>
<2800> <28ff> <2800>
<2900> <29ff> <2900>
<2a00> <2aff> <2a00>
<2b00> <2bff> <2b00>
<2c00> <2cff> <2c00>
<2d00> <2dff> <2d00>
<2e00> <2eff> <2e00>
<2f00> <2fff> <2f00>
<3000> <30ff> <3000>
<3100> <31ff> <3100>
<3200> <32ff> <3200>
<3300> <33ff> <3300>
<3400> <34ff> <3400>
<3500> <35ff> <3500>
<3600> <36ff> <3600>
<3700> <37ff> <3700>
<3800> <38ff> <3800>
<3900> <39ff> <3900>
<3a00> <3aff> <3a00>
<3b00> <3bff> <3b00>
<3c00> <3cff> <3c00>
<3d00> <3dff> <3d00>
<3e00> <3eff> <3e00>
<3f00> <3fff> <3f00>
<4000> <40ff> <4000>
<4100> <41ff> <4100>
<4200> <42ff> <4200>
<4300> <43ff> <4300>
<4400> <44ff> <4400>
<4500> <45ff> <4500>
<4600> <46ff> <4600>
<4700> <47ff> <4700>
<4800> <48ff> <4800>
<4900> <49ff> <4900>
<4a00> <4aff> <4a00>
<4b00> <4bff> <4b00>
<4c00> <4cff> <4c00>
<4d00> <4dff> <4d00>
<4e00> <4eff> <4e00>
<4f00> <4fff> <4f00>
<5000> <50ff> <5000>
<5100> <51ff> <5100>
<5200> <52ff> <5200>
<5300> <53ff> <5300>
<5400> <54ff> <5400>
<5500> <55ff> <5500>
<5600> <56ff> <5600>
<5700> <57ff> <5700>
<5800> <58ff> <5800>
<5900> <59ff> <5900>
<5a00> <5aff> <5a00>
<5b00> <5bff> <5b00>
<5c00> <5cff> <5c00>
<5d00> <5dff> <5d00>
<5e00> <5eff> <5e00>
<5f00> <5fff> <5f00>
<6000> <60ff> <6000>
<6100> <61ff> <6100>
<6200> <62ff> <6200>
<6300> <63ff> <6300>
endbfrange
100 beginbfrange
<6400> <64ff> <6400>
<6500> <65ff> <6500>
<6600> <66ff> <6600>
<6700> <67ff> <6700>
<6800> <68ff> <6800>
<6900> <69ff> <6900>
<6a00> <6aff> <6a00>
<6b00> <6bff> <6b00>
<6c00> <6cff> <6c00>
<6d00> <6dff> <6d00>
<6e00> <6eff> <6e00>
<6f00> <6fff> <6f00>
<7000> <70ff> <7000>
<7100> <71ff> <7100>
<7200> <72ff> <7200>
<7300> <73ff> <7300>
<7400> <74ff> <7400>
<7500> <75ff> <7500>
<7600> <76ff> <7600>
<7700> <77ff> <7700>
<7800> <78ff> <7800>
<7900> <79ff> <7900>
<7a00> <7aff> <7a00>
<7b00> <7bff> <7b00>
<7c00> <7cff> <7c00>
<7d00> <7dff> <7d00>
<7e00> <7eff> <7e00>
<7f00> <7fff> <7f00>
<8000> <80ff> <8000>
<8100> <81ff> <8100>
<8200> <82ff> <8200>
<8300> <83ff> <8300>
<8400> <84ff> <8400>
<8500> <85ff> <8500>
<8600> <86ff> <8600>
<8700> <87ff> <8700>
<8800> <88ff> <8800>
<8900> <89ff> <8900>
<8a00> <8aff> <8a00>
<8b00> <8bff> <8b00>
<8c00> <8cff> <8c00>
<8d00> <8dff> <8d00>
<8e00> <8eff> <8e00>
<8f00> <8fff> <8f00>
<9000> <90ff> <9000>
<9100> <91ff> <9100>
<9200> <92ff> <9200>
<9300> <93ff> <9300>
<9400> <94ff> <9400>
<9500> <95ff> <9500>
<9600> <96ff> <9600>
<9700> <97ff> <9700>
<9800> <98ff> <9800>
<9900> <99ff> <9900>
<9a00> <9aff> <9a00>
<9b00> <9bff> <9b00>
<9c00> <9cff> <9c00>
<9d00> <9dff> <9d00>
<9e00> <9eff> <9e00>
<9f00> <9fff> <9f00>
<a000> <a0ff> <a000>
<a100> <a1ff> <a100>
<a200> <a2ff> <a200>
<a300> <a3ff> <a300>
<a400> <a4ff> <a400>
<a500> <a5ff> <a500>
<a600> <a6ff> <a600>
<a700> <a7ff> <a700>
<a800> <a8ff> <a800>
<a900> <a9ff> <a900>
<aa00> <aaff> <aa00>
<ab00> <abff> <ab00>
<ac00> <acff> <ac00>
<ad00> <adff> <ad00>
<ae00> <aeff> <ae00>
<af00> <afff> <af00>
<b000> <b0ff> <b000>
<b100> <b1ff> <b100>
<b200> <b2ff> <b200>
<b300> <b3ff> <b300>
<b400> <b4ff> <b400>
<b500> <b5ff> <b500>
<b600> <b6ff> <b600>
<b700> <b7ff> <b700>
<b800> <b8ff> <b800>
<b900> <b9ff> <b900>
<ba00> <baff> <ba00>
<bb00> <bbff> <bb00>
<bc00> <bcff> <bc00>
<bd00> <bdff> <bd00>
<be00> <beff> <be00>
<bf00> <bfff> <bf00>
<c000> <c0ff> <c000>
<c100> <c1ff> <c100>
<c200> <c2ff> <c200>
<c300> <c3ff> <c300>
<c400> <c4ff> <c400>
<c500> <c5ff> <c500>
<c600> <c6ff> <c600>
<c700> <c7ff> <c700>
endbfrange
56 beginbfrange
<c800> <c8ff> <c800>
<c900> <c9ff> <c900>
<ca00> <caff> <ca00>
<cb00> <cbff> <cb00>
<cc00> <ccff> <cc00>
<cd00> <cdff> <cd00>
<ce00> <ceff> <ce00>
<cf00> <cfff> <cf00>
<d000> <d0ff> <d000>
<d100> <d1ff> <d100>
<d200> <d2ff> <d200>
<d300> <d3ff> <d300>
<d400> <d4ff> <d400>
<d500> <d5ff> <d500>
<d600> <d6ff> <d600>
<d700> <d7ff> <d700>
<d800> <d8ff> <d800>
<d900> <d9ff> <d900>
<da00> <daff> <da00>
<db00> <dbff> <db00>
<dc00> <dcff> <dc00>
<dd00> <ddff> <dd00>
<de00> <deff> <de00>
<df00> <dfff> <df00>
<e000> <e0ff> <e000>
<e100> <e1ff> <e100>
<e200> <e2ff> <e200>
<e300> <e3ff> <e300>
<e400> <e4ff> <e400>
<e500> <e5ff> <e500>
<e600> <e6ff> <e600>
<e700> <e7ff> <e700>
<e800> <e8ff> <e800>
<e900> <e9ff> <e900>
<ea00> <eaff> <ea00>
<eb00> <ebff> <eb00>
<ec00> <ecff> <ec00>
<ed00> <edff> <ed00>
<ee00> <eeff> <ee00>
<ef00> <efff> <ef00>
<f000> <f0ff> <f000>
<f100> <f1ff> <f100>
<f200> <f2ff> <f200>
<f300> <f3ff> <f300>
<f400> <f4ff> <f400>
<f500> <f5ff> <f500>
<f600> <f6ff> <f600>
<f700> <f7ff> <f700>
<f800> <f8ff> <f800>
<f900> <f9ff> <f900>
<fa00> <faff> <fa00>
<fb00> <fbff> <fb00>
<fc00> <fcff> <fc00>
<fd00> <fdff> <fd00>
<fe00> <feff> <fe00>
<ff00> <ffff> <ff00>
endbfrange
endcmap
CMapName currentdict /CMap defineresource pop
end
end
EOD;
}
