<?php
/**
 * ImportTest.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * This file is part of tc-lib-pdf-font software library.
 */

namespace Test;

use PHPUnit\Framework\TestCase;

/**
 * Import Test
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class ImportTest extends TestCase
{
    protected $preserveGlobalState = false;
    protected $runTestInSeparateProcess = true;

    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
    }

    /**
     * @expectedException \Com\Tecnick\Pdf\Font\Exception
     */
    public function testImportEmptyName()
    {
        new \Com\Tecnick\Pdf\Font\Import('');
    }

    /**
     * @expectedException \Com\Tecnick\Pdf\Font\Exception
     */
    public function testImportExist()
    {
        $fin = __DIR__.'/../util/vendor/font/core/Helvetica.afm';
        $outdir = __DIR__.'/../target/tmptest/';
        system('rm -rf '.$outdir.' && mkdir -p '.$outdir);
        new \Com\Tecnick\Pdf\Font\Import($fin, $outdir);
        new \Com\Tecnick\Pdf\Font\Import($fin, $outdir);
    }

    /**
     * @expectedException \Com\Tecnick\Pdf\Font\Exception
     */
    public function testImportWrongFile()
    {
        new \Com\Tecnick\Pdf\Font\Import(__DIR__.'/../util/vendor/font/core/Missing.afm');
    }

    /**
     * @expectedException \Com\Tecnick\Pdf\Font\Exception
     */
    public function testImportDefaultOutput()
    {
        define('K_PATH_FONTS', __DIR__.'/../target/tmptest/');
        new \Com\Tecnick\Pdf\Font\Import(__DIR__.'/../util/vendor/font/core/Missing.afm');
    }

    /**
     * @expectedException \Com\Tecnick\Pdf\Font\Exception
     */
    public function testImportUnsupportedType()
    {
        $fin = __DIR__.'/../util/vendor/font/core/Helvetica.afm';
        $outdir = __DIR__.'/../target/tmptest/core/';
        system('rm -rf '.$outdir.' && mkdir -p '.$outdir);
        new \Com\Tecnick\Pdf\Font\Import($fin, $outdir, 'ERROR');
    }

    /**
     * @expectedException \Com\Tecnick\Pdf\Font\Exception
     */
    public function testImportUnsupportedOpenType()
    {
        $outdir = __DIR__.'/../target/tmptest/core/';
        system('rm -rf '.$outdir.' && mkdir -p '.$outdir);
        file_put_contents($outdir.'test.ttf', 'OTTO 1234');
        new \Com\Tecnick\Pdf\Font\Import($outdir.'test.ttf', $outdir);
    }

    /**
     * @dataProvider importDataProvider
     */
    public function testImport($fontdir, $font, $outname, $type = null, $encoding = null)
    {
        $indir = __DIR__.'/../util/vendor/font/'.$fontdir.'/';
        $outdir = __DIR__.'/../target/tmptest/'.$fontdir.'/';
        system('rm -rf '.__DIR__.'/../target/tmptest/ && mkdir -p '.$outdir);
        
        $imp = new \Com\Tecnick\Pdf\Font\Import($indir.$font, $outdir, $type, $encoding);
        $this->assertEquals($outname, $imp->getFontName());

        $json = json_decode(file_get_contents($outdir.$outname.'.json'), true);
        $this->assertNotNull($json);

        $this->assertArrayHasKey('type', $json);
        $this->assertArrayHasKey('name', $json);
        $this->assertArrayHasKey('up', $json);
        $this->assertArrayHasKey('ut', $json);
        $this->assertArrayHasKey('dw', $json);
        $this->assertArrayHasKey('diff', $json);
        $this->assertArrayHasKey('desc', $json);
        $this->assertArrayHasKey('Flags', $json['desc']);

        $metric = $imp->getFontMetrics();
        
        $this->assertEquals('['.$metric['bbox'].']', $json['desc']['FontBBox']);
        $this->assertEquals($metric['italicAngle'], $json['desc']['ItalicAngle']);
        $this->assertEquals($metric['Ascent'], $json['desc']['Ascent']);
        $this->assertEquals($metric['Descent'], $json['desc']['Descent']);
        $this->assertEquals($metric['Leading'], $json['desc']['Leading']);
        $this->assertEquals($metric['CapHeight'], $json['desc']['CapHeight']);
        $this->assertEquals($metric['XHeight'], $json['desc']['XHeight']);
        $this->assertEquals($metric['StemV'], $json['desc']['StemV']);
        $this->assertEquals($metric['StemH'], $json['desc']['StemH']);
        $this->assertEquals($metric['AvgWidth'], $json['desc']['AvgWidth']);
        $this->assertEquals($metric['MaxWidth'], $json['desc']['MaxWidth']);
        $this->assertEquals($metric['MissingWidth'], $json['desc']['MissingWidth']);
    }

    public function importDataProvider()
    {
        return array(
            array('core', 'Courier.afm', 'courier'),
            array('core', 'Courier-Bold.afm', 'courierb'),
            array('core', 'Courier-BoldOblique.afm', 'courierbi'),
            array('core', 'Courier-Oblique.afm', 'courieri'),
            array('core', 'Helvetica.afm', 'helvetica'),
            array('core', 'Helvetica-Bold.afm', 'helveticab'),
            array('core', 'Helvetica-BoldOblique.afm', 'helveticabi'),
            array('core', 'Helvetica-Oblique.afm', 'helveticai'),
            array('core', 'Symbol.afm', 'symbol'),
            array('core', 'Times.afm', 'times'),
            array('core', 'Times-Bold.afm', 'timesb'),
            array('core', 'Times-BoldItalic.afm', 'timesbi'),
            array('core', 'Times-Italic.afm', 'timesi'),
            array('core', 'ZapfDingbats.afm', 'zapfdingbats'),

            array('pdfa/pfb', 'PDFACourierBoldOblique.pfb', 'pdfacourierbi', null, null),
            array('pdfa/pfb', 'PDFACourierBold.pfb', 'pdfacourierb', 'Type1', 'cp1252'),
            array('pdfa/pfb', 'PDFACourierOblique.pfb', 'pdfacourieri', 'Type1', 'cp1252'),
            array('pdfa/pfb', 'PDFACourier.pfb', 'pdfacourier', 'Type1', 'cp1252'),
            array('pdfa/pfb', 'PDFAHelveticaBoldOblique.pfb', 'pdfahelveticabi', 'Type1', 'cp1252'),
            array('pdfa/pfb', 'PDFAHelveticaBold.pfb', 'pdfahelveticab', 'Type1', 'cp1252'),
            array('pdfa/pfb', 'PDFAHelveticaOblique.pfb', 'pdfahelveticai', 'Type1', 'cp1252'),
            array('pdfa/pfb', 'PDFAHelvetica.pfb', 'pdfahelvetica', 'Type1', 'cp1252'),
            array('pdfa/pfb', 'PDFASymbol.pfb', 'pdfasymbol', '', 'symbol'),
            array('pdfa/pfb', 'PDFATimesBoldItalic.pfb', 'pdfatimesbi', 'Type1', 'cp1252'),
            array('pdfa/pfb', 'PDFATimesBold.pfb', 'pdfatimesb', 'Type1', 'cp1252'),
            array('pdfa/pfb', 'PDFATimesItalic.pfb', 'pdfatimesi', 'Type1', 'cp1252'),
            array('pdfa/pfb', 'PDFATimes.pfb', 'pdfatimes', 'Type1', 'cp1252'),
            array('pdfa/pfb', 'PDFAZapfDingbats.pfb', 'pdfazapfdingbats'),

            array('freefont', 'FreeMonoBoldOblique.ttf', 'freemonobi'),
            array('freefont', 'FreeMonoBold.ttf', 'freemonob'),
            array('freefont', 'FreeMonoOblique.ttf', 'freemonoi'),
            array('freefont', 'FreeMono.ttf', 'freemono'),
            array('freefont', 'FreeSansBoldOblique.ttf', 'freesansbi'),
            array('freefont', 'FreeSansBold.ttf', 'freesansb'),
            array('freefont', 'FreeSansOblique.ttf', 'freesansi'),
            array('freefont', 'FreeSans.ttf', 'freesans'),
            array('freefont', 'FreeSerifBoldItalic.ttf', 'freeserifbi'),
            array('freefont', 'FreeSerifBold.ttf', 'freeserifb'),
            array('freefont', 'FreeSerifItalic.ttf', 'freeserifi'),
            array('freefont', 'FreeSerif.ttf', 'freeserif'),

            array('unifont', 'unifont.ttf', 'unifont'),

            array('cid0', 'cid0cs.ttf', 'cid0cs', 'CID0CS'),
            array('cid0', 'cid0ct.ttf', 'cid0ct', 'CID0CT'),
            array('cid0', 'cid0jp.ttf', 'cid0jp', 'CID0JP'),
            array('cid0', 'cid0kr.ttf', 'cid0kr', 'CID0KR'),

            array('dejavu/ttf', 'DejaVuSans.ttf', 'dejavusans'),
            array('dejavu/ttf', 'DejaVuSans-BoldOblique.ttf', 'dejavusansbi'),
            array('dejavu/ttf', 'DejaVuSans-Bold.ttf', 'dejavusansb'),
            array('dejavu/ttf', 'DejaVuSans-Oblique.ttf', 'dejavusansi'),
            array('dejavu/ttf', 'DejaVuSansCondensed.ttf', 'dejavusanscondensed'),
            array('dejavu/ttf', 'DejaVuSansCondensed-BoldOblique.ttf', 'dejavusanscondensedbi'),
            array('dejavu/ttf', 'DejaVuSansCondensed-Bold.ttf', 'dejavusanscondensedb'),
            array('dejavu/ttf', 'DejaVuSansCondensed-Oblique.ttf', 'dejavusanscondensedi'),
            array('dejavu/ttf', 'DejaVuSansMono.ttf', 'dejavusansmono'),
            array('dejavu/ttf', 'DejaVuSansMono-BoldOblique.ttf', 'dejavusansmonobi'),
            array('dejavu/ttf', 'DejaVuSansMono-Bold.ttf', 'dejavusansmonob'),
            array('dejavu/ttf', 'DejaVuSansMono-Oblique.ttf', 'dejavusansmonoi'),
            array('dejavu/ttf', 'DejaVuSans-ExtraLight.ttf', 'dejavusansextralight'),
            array('dejavu/ttf', 'DejaVuSerif.ttf', 'dejavuserif'),
            array('dejavu/ttf', 'DejaVuSerif-BoldItalic.ttf', 'dejavuserifbi'),
            array('dejavu/ttf', 'DejaVuSerif-Bold.ttf', 'dejavuserifb'),
            array('dejavu/ttf', 'DejaVuSerif-Italic.ttf', 'dejavuserifi'),
            array('dejavu/ttf', 'DejaVuSerifCondensed.ttf', 'dejavuserifcondensed'),
            array('dejavu/ttf', 'DejaVuSerifCondensed-BoldItalic.ttf', 'dejavuserifcondensedbi'),
            array('dejavu/ttf', 'DejaVuSerifCondensed-Bold.ttf', 'dejavuserifcondensedb'),
            array('dejavu/ttf', 'DejaVuSerifCondensed-Italic.ttf', 'dejavuserifcondensedi'),

            array('noto', 'NotoEmoji-Regular.ttf', 'notoemoji'),
            array('noto', 'NotoKufiArabic-Bold.ttf', 'notokufiarabicb'),
            array('noto', 'NotoKufiArabic-Regular.ttf', 'notokufiarabic'),
            array('noto', 'NotoNaskhArabic-Bold.ttf', 'notonaskharabicb'),
            array('noto', 'NotoNaskhArabic-Regular.ttf', 'notonaskharabic'),
            array('noto', 'NotoNastaliqUrdu-Regular.ttf', 'notonastaliqurdu'),
            array('noto', 'NotoSansArmenian-Bold.ttf', 'notosansarmenianb'),
            array('noto', 'NotoSansArmenian-Regular.ttf', 'notosansarmenian'),
            array('noto', 'NotoSansAvestan-Regular.ttf', 'notosansavestan'),
            array('noto', 'NotoSansBalinese-Regular.ttf', 'notosansbalinese'),
            array('noto', 'NotoSansBamum-Regular.ttf', 'notosansbamum'),
            array('noto', 'NotoSansBatak-Regular.ttf', 'notosansbatak'),
            array('noto', 'NotoSansBengali-Bold.ttf', 'notosansbengalib'),
            array('noto', 'NotoSansBengali-Regular.ttf', 'notosansbengali'),
            array('noto', 'NotoSans-BoldItalic.ttf', 'notosansbi'),
            array('noto', 'NotoSans-Bold.ttf', 'notosansb'),
            array('noto', 'NotoSansBrahmi-Regular.ttf', 'notosansbrahmi'),
            array('noto', 'NotoSansBuginese-Regular.ttf', 'notosansbuginese'),
            array('noto', 'NotoSansBuhid-Regular.ttf', 'notosansbuhid'),
            array('noto', 'NotoSansCanadianAboriginal-Regular.ttf', 'notosanscanadianaboriginal'),
            array('noto', 'NotoSansCarian-Regular.ttf', 'notosanscarian'),
            array('noto', 'NotoSansCham-Bold.ttf', 'notosanschamb'),
            array('noto', 'NotoSansCham-Regular.ttf', 'notosanscham'),
            array('noto', 'NotoSansCherokee-Regular.ttf', 'notosanscherokee'),
            array('noto', 'NotoSansCoptic-Regular.ttf', 'notosanscoptic'),
            array('noto', 'NotoSansCuneiform-Regular.ttf', 'notosanscuneiform'),
            array('noto', 'NotoSansCypriot-Regular.ttf', 'notosanscypriot'),
            array('noto', 'NotoSansDeseret-Regular.ttf', 'notosansdeseret'),
            array('noto', 'NotoSansDevanagari-Bold.ttf', 'notosansdevanagarib'),
            array('noto', 'NotoSansDevanagari-Regular.ttf', 'notosansdevanagari'),
            array('noto', 'NotoSansEgyptianHieroglyphs-Regular.ttf', 'notosansegyptianhieroglyphs'),
            array('noto', 'NotoSansEthiopic-Bold.ttf', 'notosansethiopicb'),
            array('noto', 'NotoSansEthiopic-Regular.ttf', 'notosansethiopic'),
            array('noto', 'NotoSansGeorgian-Bold.ttf', 'notosansgeorgianb'),
            array('noto', 'NotoSansGeorgian-Regular.ttf', 'notosansgeorgian'),
            array('noto', 'NotoSansGlagolitic-Regular.ttf', 'notosansglagolitic'),
            array('noto', 'NotoSansGothic-Regular.ttf', 'notosansgothic'),
            array('noto', 'NotoSansGujarati-Bold.ttf', 'notosansgujaratib'),
            array('noto', 'NotoSansGujarati-Regular.ttf', 'notosansgujarati'),
            array('noto', 'NotoSansGurmukhi-Bold.ttf', 'notosansgurmukhib'),
            array('noto', 'NotoSansGurmukhi-Regular.ttf', 'notosansgurmukhi'),
            array('noto', 'NotoSansHanunoo-Regular.ttf', 'notosanshanunoo'),
            array('noto', 'NotoSansHebrew-Bold.ttf', 'notosanshebrewb'),
            array('noto', 'NotoSansHebrew-Regular.ttf', 'notosanshebrew'),
            array('noto', 'NotoSansImperialAramaic-Regular.ttf', 'notosansimperialaramaic'),
            array('noto', 'NotoSansInscriptionalPahlavi-Regular.ttf', 'notosansinscriptionalpahlavi'),
            array('noto', 'NotoSansInscriptionalParthian-Regular.ttf', 'notosansinscriptionalparthian'),
            array('noto', 'NotoSans-Italic.ttf', 'notosansi'),
            array('noto', 'NotoSansJavanese-Regular.ttf', 'notosansjavanese'),
            array('noto', 'NotoSansKaithi-Regular.ttf', 'notosanskaithi'),
            array('noto', 'NotoSansKannada-Bold.ttf', 'notosanskannadab'),
            array('noto', 'NotoSansKannada-Regular.ttf', 'notosanskannada'),
            array('noto', 'NotoSansKayahLi-Regular.ttf', 'notosanskayahli'),
            array('noto', 'NotoSansKharoshthi-Regular.ttf', 'notosanskharoshthi'),
            array('noto', 'NotoSansKhmer-Bold.ttf', 'notosanskhmerb'),
            array('noto', 'NotoSansKhmer-Regular.ttf', 'notosanskhmer'),
            array('noto', 'NotoSansLao-Bold.ttf', 'notosanslaob'),
            array('noto', 'NotoSansLao-Regular.ttf', 'notosanslao'),
            array('noto', 'NotoSansLepcha-Regular.ttf', 'notosanslepcha'),
            array('noto', 'NotoSansLimbu-Regular.ttf', 'notosanslimbu'),
            array('noto', 'NotoSansLinearB-Regular.ttf', 'notosanslinearb'),
            array('noto', 'NotoSansLisu-Regular.ttf', 'notosanslisu'),
            array('noto', 'NotoSansLycian-Regular.ttf', 'notosanslycian'),
            array('noto', 'NotoSansLydian-Regular.ttf', 'notosanslydian'),
            array('noto', 'NotoSansMalayalam-Bold.ttf', 'notosansmalayalamb'),
            array('noto', 'NotoSansMalayalam-Regular.ttf', 'notosansmalayalam'),
            array('noto', 'NotoSansMandaic-Regular.ttf', 'notosansmandaic'),
            array('noto', 'NotoSansMeeteiMayek-Regular.ttf', 'notosansmeeteimayek'),
            array('noto', 'NotoSansMongolian-Regular.ttf', 'notosansmongolian'),
            array('noto', 'NotoSansMyanmar-Bold.ttf', 'notosansmyanmarb'),
            array('noto', 'NotoSansMyanmar-Regular.ttf', 'notosansmyanmar'),
            array('noto', 'NotoSansNewTaiLue-Regular.ttf', 'notosansnewtailue'),
            array('noto', 'NotoSansNKo-Regular.ttf', 'notosansnko'),
            array('noto', 'NotoSansOgham-Regular.ttf', 'notosansogham'),
            array('noto', 'NotoSansOlChiki-Regular.ttf', 'notosansolchiki'),
            array('noto', 'NotoSansOldItalic-Regular.ttf', 'notosansoldi'),
            array('noto', 'NotoSansOldPersian-Regular.ttf', 'notosansoldpersian'),
            array('noto', 'NotoSansOldSouthArabian-Regular.ttf', 'notosansoldsoutharabian'),
            array('noto', 'NotoSansOldTurkic-Regular.ttf', 'notosansoldturkic'),
            array('noto', 'NotoSansOriya-Bold.ttf', 'notosansoriyab'),
            array('noto', 'NotoSansOriya-Regular.ttf', 'notosansoriya'),
            array('noto', 'NotoSansOsmanya-Regular.ttf', 'notosansosmanya'),
            array('noto', 'NotoSansPhagsPa-Regular.ttf', 'notosansphagspa'),
            array('noto', 'NotoSansPhoenician-Regular.ttf', 'notosansphoenician'),
            array('noto', 'NotoSans-Regular.ttf', 'notosans'),
            array('noto', 'NotoSansRejang-Regular.ttf', 'notosansrejang'),
            array('noto', 'NotoSansRunic-Regular.ttf', 'notosansrunic'),
            array('noto', 'NotoSansSamaritan-Regular.ttf', 'notosanssamaritan'),
            array('noto', 'NotoSansSaurashtra-Regular.ttf', 'notosanssaurashtra'),
            array('noto', 'NotoSansShavian-Regular.ttf', 'notosansshavian'),
            array('noto', 'NotoSansSinhala-Bold.ttf', 'notosanssinhalab'),
            array('noto', 'NotoSansSinhala-Regular.ttf', 'notosanssinhala'),
            array('noto', 'NotoSansSundanese-Regular.ttf', 'notosanssundanese'),
            array('noto', 'NotoSansSylotiNagri-Regular.ttf', 'notosanssylotinagri'),
            array('noto', 'NotoSansSymbols-Regular.ttf', 'notosanssymbols'),
            array('noto', 'NotoSansSyriacEastern-Regular.ttf', 'notosanssyriaceastern'),
            array('noto', 'NotoSansSyriacEstrangela-Regular.ttf', 'notosanssyriacestrangela'),
            array('noto', 'NotoSansSyriacWestern-Regular.ttf', 'notosanssyriacwestern'),
            array('noto', 'NotoSansTagalog-Regular.ttf', 'notosanstagalog'),
            array('noto', 'NotoSansTagbanwa-Regular.ttf', 'notosanstagbanwa'),
            array('noto', 'NotoSansTaiLe-Regular.ttf', 'notosanstaile'),
            array('noto', 'NotoSansTaiTham-Regular.ttf', 'notosanstaitham'),
            array('noto', 'NotoSansTaiViet-Regular.ttf', 'notosanstaiviet'),
            array('noto', 'NotoSansTamil-Bold.ttf', 'notosanstamilb'),
            array('noto', 'NotoSansTamil-Regular.ttf', 'notosanstamil'),
            array('noto', 'NotoSansTelugu-Bold.ttf', 'notosanstelugub'),
            array('noto', 'NotoSansTelugu-Regular.ttf', 'notosanstelugu'),
            array('noto', 'NotoSansThaana-Bold.ttf', 'notosansthaanab'),
            array('noto', 'NotoSansThaana-Regular.ttf', 'notosansthaana'),
            array('noto', 'NotoSansThai-Bold.ttf', 'notosansthaib'),
            array('noto', 'NotoSansThai-Regular.ttf', 'notosansthai'),
            array('noto', 'NotoSansTibetan-Bold.ttf', 'notosanstibetanb'),
            array('noto', 'NotoSansTibetan-Regular.ttf', 'notosanstibetan'),
            array('noto', 'NotoSansTifinagh-Regular.ttf', 'notosanstifinagh'),
            array('noto', 'NotoSansUgaritic-Regular.ttf', 'notosansugaritic'),
            array('noto', 'NotoSansVai-Regular.ttf', 'notosansvai'),
            array('noto', 'NotoSansYi-Regular.ttf', 'notosansyi'),
            array('noto', 'NotoSerifArmenian-Bold.ttf', 'notoserifarmenianb'),
            array('noto', 'NotoSerifArmenian-Regular.ttf', 'notoserifarmenian'),
            array('noto', 'NotoSerif-BoldItalic.ttf', 'notoserifbi'),
            array('noto', 'NotoSerif-Bold.ttf', 'notoserifb'),
            array('noto', 'NotoSerifGeorgian-Bold.ttf', 'notoserifgeorgianb'),
            array('noto', 'NotoSerifGeorgian-Regular.ttf', 'notoserifgeorgian'),
            array('noto', 'NotoSerif-Italic.ttf', 'notoserifi'),
            array('noto', 'NotoSerifKhmer-Bold.ttf', 'notoserifkhmerb'),
            array('noto', 'NotoSerifKhmer-Regular.ttf', 'notoserifkhmer'),
            array('noto', 'NotoSerifLao-Bold.ttf', 'notoseriflaob'),
            array('noto', 'NotoSerifLao-Regular.ttf', 'notoseriflao'),
            array('noto', 'NotoSerif-Regular.ttf', 'notoserif'),
            array('noto', 'NotoSerifThai-Bold.ttf', 'notoserifthaib'),
            array('noto', 'NotoSerifThai-Regular.ttf', 'notoserifthai'),
        );
    }
}
