<?php

namespace tFPDF;

/**
 * Class PDF
 * @package tFPDF
 */
class PDF
{

    const ORIENTATION_PORTRAIT = 'P';
    const ORIENTATION_LANDSCAPE = 'L';

    const DOCUMENT_STATE_NOT_INITIALIZED = 0;
    const DOCUMENT_STATE_INITIALIZED = 1;
    const DOCUMENT_STATE_CREATING = 2;
    const DOCUMENT_STATE_TERMINATED = 3;

    const FONT_TRUETYPE = 'TTF';

    /**
     * @var bool
     */
    protected $bol_uniform_subset = false;

    /**
     * Current page number
     *
     * @var int
     */
    protected $int_page = 0;

    /**
     * Current object number
     *
     * @var int
     */
    protected $int_current_object = 2;

    /**
     * Array of object offsets
     *
     * @var array
     */
    protected $arr_offsets = [];

    /**
     * Buffer holding in-memory PDF
     *
     * @var string
     */
    protected $str_buffer = '';

    /**
     * Array containing pages
     *
     * @var array
     */
    protected $arr_pages = [];

    /**
     * Current document state
     *
     * @var int
     */
    protected $int_state = self::DOCUMENT_STATE_NOT_INITIALIZED;

    /**
     * Compression flag
     *
     * @var bool
     */
    protected $bol_compress = false;

    /**
     * Scale factor (number of points in user unit)
     *
     * @var int
     */
    protected $flt_scale_factor = 1;

    /**
     * Default orientation
     *
     * @var string
     */
    protected $str_default_orientation = '';

    /**
     * Current orientation
     *
     * @var string
     */
    protected $str_current_orientation = '';

    /**
     * Standard page sizes
     *
     * @var array
     */
    protected $arr_standard_page_sizes = [
        'a3' => array(841.89, 1190.55),
        'a4' => array(595.28, 841.89),
        'a5' => array(420.94, 595.28),
        'letter' => array(612, 792),
        'legal' => array(612, 1008)
    ];

    /**
     * Default page size
     *
     * @var array|string
     */
    protected $arr_default_page_sizes = [];

    /**
     * Current page size
     *
     * @var array|string
     */
    protected $arr_current_page_sizes = [];

    /**
     * Used for pages with non default sizes or orientations
     *
     * @var array
     */
    protected $arr_page_sizes = [];

    /**
     * Dimensions of current page in points
     *
     * @var float
     */
    protected $flt_width_points, $flt_height_points = 0.00;

    /**
     * Dimensions of current page in user units
     *
     * @var float
     */
    protected $flt_current_width, $flt_current_height = 0.00;

    /**
     * Left Margin
     *
     * @var int
     */
    protected $int_left_margin = 0;

    /**
     * Top Margin
     *
     * @var int
     */
    protected $int_top_margin = 0;

    /**
     * Right Margin
     *
     * @var int
     */
    protected $int_right_margin = 0;

    /**
     * Page break margin
     *
     * @var int
     */
    protected $int_break_margin = 0;

    /**
     * Cell Margin
     *
     * @var float|int
     */
    protected $int_cell_margin = 0;

    /**
     * Current position in user unit
     *
     * @var
     */
    protected $flt_position_x, $flt_position_y = 0.00;

    /**
     * Height of last printed cell
     *
     * @var float
     */
    protected $flt_last_cell_height = 0.00;

    /**
     * Line width in user units
     *
     * @var float
     */
    protected $flt_line_width = 0.00;

    /**
     * The path containing fonts
     *
     * @var string
     */
    protected $str_font_path = '';

    /**
     * The path that we use to write the fonts to
     *
     * @var string
     */
    protected $str_font_write_path;

    /**
     * Array of core font names
     *
     * @var array
     */
    protected $arr_core_fonts = [
        'courier',
        'helvetica',
        'times',
        'symbol',
        'zapfdingbats'
    ];

    /**
     * Array of used fonts
     *
     * @var array
     */
    protected $arr_fonts = [];

    /**
     * Array of font files
     *
     * @var array
     */
    protected $arr_font_files = [];

    /**
     * Array of encoding differences
     *
     * @var array
     */
    protected $arr_encoding_diffs = [];

    /**
     * Current font family
     *
     * @var string
     */
    protected $str_current_font_family = '';

    /**
     * Current font style
     *
     * @var string
     */
    protected $str_current_font_style = '';

    /**
     * Underline flag
     *
     * @var bool
     */
    protected $bol_underline = false;

    /**
     * Array of current font info
     *
     * @var array
     */
    protected $arr_current_font_info = [];

    /**
     * Current font size in points
     *
     * @var int
     */
    protected $int_current_font_size = 12;

    /**
     * Current font size in user units
     *
     * @var int
     */
    protected $int_font_size_user = 0;

    /**
     * The draw color
     *
     * @var string
     */
    protected $str_draw_color = '0 G';

    /**
     * The fill color
     *
     * @var string
     */
    protected $str_fill_color = '0 g';

    /**
     * The text color
     *
     * @var string
     */
    protected $str_text_color = '0 g';

    /**
     * Indicates whether fill and text colors are different
     *
     * @var bool
     */
    protected $bol_fill_text_differ = false;

    /**
     * The word spacing
     *
     * @var int
     */
    protected $int_word_spacing = 0;

    /**
     * Array of used images
     *
     * @var array
     */
    protected $arr_images = [];

    /**
     * Array of links in pages
     *
     * @var array
     */
    protected $arr_page_links = [];

    /**
     * Array of internal links
     *
     * @var array
     */
    protected $arr_internal_links = [];

    /**
     * Automatic page breaking
     *
     * @var bool
     */
    protected $bol_auto_page_break = false;

    /**
     * Threshold used to trigger page breaks
     *
     * @var float
     */
    protected $flt_page_break_trigger = 0.00;

    /**
     * Flag set when processing header
     *
     * @var bool
     */
    protected $bol_in_header = false;

    /**
     * Flag set when processing footer
     *
     * @var bool
     */
    protected $bol_in_footer = false;

    /**
     * Zoom display mode
     *
     * @var mixed
     */
    protected $mix_zoom_mode;

    /**
     * Layout display mode
     *
     * @var string
     */
    protected $str_layout_mode = '';

    /**
     * The title
     *
     * @var string
     */
    protected $str_title = '';

    /**
     * The subject
     *
     * @var string
     */
    protected $str_subject = '';

    /**
     * The author
     *
     * @var string
     */
    protected $str_author = '';

    /**
     * The keywords
     *
     * @var string
     */
    protected $str_keywords = '';

    /**
     * The creator
     *
     * @var string
     */
    protected $str_creator = '';

    /**
     * The alias for total number of pages
     *
     * @var string
     */
    protected $str_alias_number_pages = '';

    /**
     * The PDF version number
     *
     * @var string
     */
    protected $str_pdf_version = '1.3';

    /**
     * The unifont directory
     *
     * @var string
     */
    protected $str_unifont_path = 'unifont/';

    /**
     * @var string
     */
    protected $str_font_metrics_file_suffix = '-font-metrics.php';

    /**
     * @var string
     */
    protected $str_character_widths_file_suffix = '-character-widths.dat';

    /**
     * @var string
     */
    protected $str_127_character_widths_file_suffix = '-character-width-127.php';

    /**
     * PDF constructor.
     * @param string $str_orientation
     * @param string $str_units
     * @param string $str_size
     */
    public function __construct($str_orientation = 'P', $str_units = 'mm', $str_size = 'A4')
    {
        $this->str_font_path = __DIR__ . '/../font/';

        if (defined('FPDF_FONTPATH')) {
            $this->str_font_path = FPDF_FONTPATH;
        }

        if (getenv('FPDF_FONTPATH')) {
            $this->str_font_path = getenv('FPDF_FONTPATH');
        }

        if (defined('FPDF_FONT_WRITE_PATH')) {
            $this->str_font_write_path = FPDF_FONT_WRITE_PATH;
        } else if (getenv('FPDF_FONT_WRITE_PATH')) {
            $this->str_font_write_path = getenv('FPDF_FONT_WRITE_PATH');
        } else {
            $this->str_font_write_path = $this->str_font_path;
        }

        $this->configureFontWritePath($this->str_unifont_path);

        // Scale factor
        switch ($str_units) {
            case 'pt':
                $this->flt_scale_factor = 1;
                break;
            case 'mm':
            case 'cm':
                $this->flt_scale_factor = 72 / 25.4;
                break;
            case 'in':
                $this->flt_scale_factor = 72;
                break;
            default:
                throw new \RuntimeException('Invalid unit specified: ' . $str_units);
        }

        $str_size = $this->getPageSize($str_size);
        $this->arr_default_page_sizes = $str_size;
        $this->arr_current_page_sizes = $str_size;
        // Page orientation

        switch ($str_orientation) {

            case 'portrait':
            case self::ORIENTATION_PORTRAIT:
                $this->str_default_orientation = 'P';
                $this->flt_current_width = $str_size[0];
                $this->flt_current_height = $str_size[1];
                break;

            case 'landscape':
            case self::ORIENTATION_LANDSCAPE:
                $this->str_default_orientation = 'L';
                $this->flt_current_width = $str_size[1];
                $this->flt_current_height = $str_size[0];
                break;

            default:
                $this->Error('Incorrect orientation: ' . $str_orientation);
                break;

        }

        $this->str_current_orientation = $this->str_default_orientation;
        $this->flt_width_points = $this->flt_current_width * $this->flt_scale_factor;
        $this->flt_height_points = $this->flt_current_height * $this->flt_scale_factor;
        // Page margins (1 cm)
        $flt_margin = 28.35 / $this->flt_scale_factor;
        $this->setMargins($flt_margin, $flt_margin);
        // Interior cell margin (1 mm)
        $this->int_cell_margin = $flt_margin / 10;
        // Line width (0.2 mm)
        $this->flt_line_width = .567 / $this->flt_scale_factor;
        // Automatic page break
        $this->SetAutoPageBreak(true, 2 * $flt_margin);
        // Default display mode
        $this->SetDisplayMode('default');
        // Enable compression
        $this->SetCompression(true);

    }

    /**
     * @param int $int_left
     * @param int $int_top
     * @param int|null $int_right
     */
    public function setMargins($int_left, $int_top, $int_right = null)
    {
        // Set left, top and right margins
        $this->int_left_margin = $int_left;
        $this->int_top_margin = $int_top;
        if (null === $int_right) {
            $int_right = $int_left;
        }
        $this->int_right_margin = $int_right;
    }

    /**
     * @param int $int_margin
     */
    public function SetLeftMargin($int_margin)
    {
        // Set left margin
        $this->int_left_margin = $int_margin;
        if ($this->int_page > 0 && $this->flt_position_x < $int_margin) {
            $this->flt_position_x = $int_margin;
        }
    }

    /**
     * @param int $int_margin
     */
    public function SetTopMargin($int_margin)
    {
        // Set top margin
        $this->int_top_margin = $int_margin;
    }

    /**
     * @param int $int_margin
     */
    public function SetRightMargin($int_margin)
    {
        // Set right margin
        $this->int_right_margin = $int_margin;
    }

    /**
     * @param $bol_auto
     * @param int $int_margin
     */
    public function SetAutoPageBreak($bol_auto, $int_margin = 0)
    {
        // Set auto page break mode and triggering margin
        $this->bol_auto_page_break = $bol_auto;
        $this->int_break_margin = $int_margin;
        $this->flt_page_break_trigger = $this->flt_current_height - $int_margin;
    }

    /**
     * @param string $str_zoom
     * @param string $str_layout
     */
    public function SetDisplayMode($str_zoom, $str_layout = 'default')
    {
        // Set display mode in viewer
        if ($str_zoom == 'fullpage' || $str_zoom == 'fullwidth' || $str_zoom == 'real' || $str_zoom == 'default' || !is_string($str_zoom)) {
            $this->mix_zoom_mode = $str_zoom;
        } else {
            $this->Error('Incorrect zoom display mode: ' . $str_zoom);
        }
        if ($str_layout == 'single' || $str_layout == 'continuous' || $str_layout == 'two' || $str_layout == 'default') {
            $this->str_layout_mode = $str_layout;
        } else {
            $this->Error('Incorrect layout display mode: ' . $str_layout);
        }
    }

    /**
     * @param $bol_compression
     */
    public function SetCompression($bol_compression)
    {
        // Set page compression
        if (function_exists('gzcompress')) {
            $this->bol_compress = $bol_compression;
        } else {
            $this->bol_compress = false;
        }
    }

    /**
     * @param string $str_title
     * @param bool $bol_utf8
     */
    public function SetTitle($str_title, $bol_utf8 = false)
    {
        // Title of document
        if ($bol_utf8) {
            $str_title = $this->UTF8toUTF16($str_title);
        }
        $this->str_title = $str_title;
    }

    /**
     * @param string $str_subject
     * @param bool $bol_utf8
     */
    public function SetSubject($str_subject, $bol_utf8 = false)
    {
        // Subject of document
        if ($bol_utf8) {
            $str_subject = $this->UTF8toUTF16($str_subject);
        }
        $this->str_subject = $str_subject;
    }

    /**
     * @param string $str_author
     * @param bool $bol_utf8
     */
    public function SetAuthor($str_author, $bol_utf8 = false)
    {
        // Author of document
        if ($bol_utf8) {
            $str_author = $this->UTF8toUTF16($str_author);
        }
        $this->str_author = $str_author;
    }

    /**
     * @param string $str_keywords
     * @param bool $bol_utf8
     */
    public function SetKeywords($str_keywords, $bol_utf8 = false)
    {
        // Keywords of document
        if ($bol_utf8) {
            $str_keywords = $this->UTF8toUTF16($str_keywords);
        }
        $this->str_keywords = $str_keywords;
    }

    /**
     * @param string $str_creator
     * @param bool $bol_utf8
     */
    public function SetCreator($str_creator, $bol_utf8 = false)
    {
        // Creator of document
        if ($bol_utf8) {
            $str_creator = $this->UTF8toUTF16($str_creator);
        }
        $this->str_creator = $str_creator;
    }

    /**
     * @param string $str_alias
     */
    public function AliasNbPages($str_alias = '{nb}')
    {
        // Define an alias for total number of pages
        $this->str_alias_number_pages = $str_alias;
    }

    /**
     * @param string $str_message
     */
    private function Error($str_message)
    {
        // Fatal error
        throw new \RuntimeException('FPDF Error: ' . $str_message);
    }

    /**
     * Inits the document
     */
    public function Open()
    {
        // Begin document
        $this->int_state = self::DOCUMENT_STATE_INITIALIZED;
    }

    /**
     * Closes the document
     */
    public function Close()
    {
        // Terminate document
        if ($this->int_state == self::DOCUMENT_STATE_TERMINATED) {
            return;
        }
        if ($this->int_page == 0) {
            $this->AddPage();
        }
        // Page footer
        $this->bol_in_footer = true;
        $this->Footer();
        $this->bol_in_footer = false;
        // Close page
        $this->EndPage();
        // Close document
        $this->EndDoc();
    }

    /**
     * @param string $str_orientation
     * @param string $str_size
     */
    public function AddPage($str_orientation = '', $str_size = '')
    {
        // Start a new page
        if ($this->int_state == self::DOCUMENT_STATE_NOT_INITIALIZED) {
            $this->Open();
        }
        $str_font_family = $this->str_current_font_family;
        $str_style = $this->str_current_font_style . ($this->bol_underline ? 'U' : '');
        $int_font_size = $this->int_current_font_size;
        $flt_line_width = $this->flt_line_width;
        $str_draw_color = $this->str_draw_color;
        $str_fill_color = $this->str_fill_color;
        $str_text_color = $this->str_text_color;
        $bol_fill_text_differ = $this->bol_fill_text_differ;
        if ($this->int_page > 0) {
            // Page footer
            $this->bol_in_footer = true;
            $this->Footer();
            $this->bol_in_footer = false;
            // Close page
            $this->EndPage();
        }
        // Start new page
        $this->BeginPage($str_orientation, $str_size);
        // Set line cap style to square
        $this->Out('2 J');
        // Set line width
        $this->flt_line_width = $flt_line_width;
        $this->Out(sprintf('%.2F w', $flt_line_width * $this->flt_scale_factor));
        // Set font
        if ($str_font_family) {
            $this->SetFont($str_font_family, $str_style, $int_font_size);
        }
        // Set colors
        $this->str_draw_color = $str_draw_color;
        if ($str_draw_color != '0 G') {
            $this->Out($str_draw_color);
        }
        $this->str_fill_color = $str_fill_color;
        if ($str_fill_color != '0 g') {
            $this->Out($str_fill_color);
        }
        $this->str_text_color = $str_text_color;
        $this->bol_fill_text_differ = $bol_fill_text_differ;
        // Page header
        $this->bol_in_header = true;
        $this->Header();
        $this->bol_in_header = false;
        // Restore line width
        if ($this->flt_line_width != $flt_line_width) {
            $this->flt_line_width = $flt_line_width;
            $this->Out(sprintf('%.2F w', $flt_line_width * $this->flt_scale_factor));
        }
        // Restore font
        if ($str_font_family) {
            $this->SetFont($str_font_family, $str_style, $int_font_size);
        }
        // Restore colors
        if ($this->str_draw_color != $str_draw_color) {
            $this->str_draw_color = $str_draw_color;
            $this->Out($str_draw_color);
        }
        if ($this->str_fill_color != $str_fill_color) {
            $this->str_fill_color = $str_fill_color;
            $this->Out($str_fill_color);
        }
        $this->str_text_color = $str_text_color;
        $this->bol_fill_text_differ = $bol_fill_text_differ;
    }

    /**
     *
     */
    public function Header()
    {
        // To be implemented in your own inherited class
    }

    /**
     *
     */
    public function Footer()
    {
        // To be implemented in your own inherited class
    }

    /**
     * @return int
     */
    public function PageNo()
    {
        // Get current page number
        return $this->int_page;
    }

    /**
     * @param array $args
     * @return string
     */
    protected function getColourString($args)
    {
        if (count($args) && is_array($args[0])) {
            $args = $args[0];
        }
        switch (count($args)) {
            case 1:
                return sprintf('%.3f g', $args[0] / 255);
            case 3:
                return sprintf('%.3f %.3f %.3f rg', $args[0] / 255, $args[1] / 255, $args[2] / 255);
            case 4:
                return sprintf('%.3f %.3f %.3f %.3f k', $args[0] / 100, $args[1] / 100, $args[2] / 100, $args[3] / 100);
            default:
                return '0 g';
        }
    }

    /**
     * @param int $int_red
     * @param int|null $int_green
     * @param int|null $int_blue
     */
    public function SetDrawColor()
    {
        // Set color for all stroking operations
        $args = func_get_args();
        // Setting draw colour (unlike any other colours) requires it to be uppercase
        $this->str_draw_color = strtoupper($this->getColourString($args));
        if ($this->int_page > 0) {
            $this->Out($this->str_draw_color);
        }
    }

    /**
     * @param int $int_red
     * @param int|null $int_green
     * @param int|null $int_blue
     */
    public function SetFillColor()
    {
        // Set color for all filling operations
        $args = func_get_args();
        $this->str_fill_color = $this->getColourString($args);
        $this->bol_fill_text_differ = ($this->str_fill_color != $this->str_text_color);
        if ($this->int_page > 0) {
            $this->Out($this->str_fill_color);
        }
    }

    /**
     * @param int $int_red
     * @param int|null $int_green
     * @param int|null $int_blue
     */
    public function SetTextColor()
    {
        // Set color for text
        $args = func_get_args();
        $this->str_text_color = $this->getColourString($args);
        $this->bol_fill_text_differ = ($this->str_fill_color != $this->str_text_color);
    }

    /**
     * @param string $str_text
     * @return float|int
     */
    public function GetStringWidth($str_text)
    {
        // Get width of a string in the current font
        $str_text = (string)$str_text;
        $arr_character_width = &$this->arr_current_font_info['cw'];
        $flt_width = 0;
        if ($this->bol_uniform_subset) {
            $str_unicode = $this->UTF8StringToArray($str_text);
            foreach ($str_unicode as $str_char) {
                if (isset($arr_character_width[$str_char])) {
                    $flt_width += (ord($arr_character_width[2 * $str_char]) << 8) + ord($arr_character_width[2 * $str_char + 1]);
                } else {
                    if ($str_char > 0 && $str_char < 128 && isset($arr_character_width[chr($str_char)])) {
                        $flt_width += $arr_character_width[chr($str_char)];
                    } else {
                        if (isset($this->arr_current_font_info['desc']['MissingWidth'])) {
                            $flt_width += $this->arr_current_font_info['desc']['MissingWidth'];
                        } else {
                            if (isset($this->arr_current_font_info['MissingWidth'])) {
                                $flt_width += $this->arr_current_font_info['MissingWidth'];
                            } else {
                                $flt_width += 500;
                            }
                        }
                    }
                }
            }
        } else {
            $int_length = strlen($str_text);
            for ($i = 0; $i < $int_length; $i++) {
                $flt_width += $arr_character_width[$str_text[$i]];
            }
        }
        return $flt_width * $this->int_font_size_user / 1000;
    }

    /**
     * @param float $flt_width
     */
    public function SetLineWidth($flt_width)
    {
        // Set line width
        $this->flt_line_width = $flt_width;
        if ($this->int_page > 0) {
            $this->Out(sprintf('%.2F w', $flt_width * $this->flt_scale_factor));
        }
    }

    /**
     * @param float $flt_x_1
     * @param float $flt_y_1
     * @param float $flt_x_2
     * @param float $flt_y_2
     */
    public function Line($flt_x_1, $flt_y_1, $flt_x_2, $flt_y_2)
    {
        // Draw a line
        $this->Out(sprintf('%.2F %.2F m %.2F %.2F l S', $flt_x_1 * $this->flt_scale_factor,
            ($this->flt_current_height - $flt_y_1) * $this->flt_scale_factor, $flt_x_2 * $this->flt_scale_factor,
            ($this->flt_current_height - $flt_y_2) * $this->flt_scale_factor));
    }

    /**
     * @param float $flt_x
     * @param float $flt_y
     * @param float $flt_width
     * @param float $flt_height
     * @param string $str_style
     */
    public function Rect($flt_x, $flt_y, $flt_width, $flt_height, $str_style = '')
    {
        // Draw a rectangle
        if ($str_style == 'F') {
            $op = 'f';
        } elseif ($str_style == 'FD' || $str_style == 'DF') {
            $op = 'B';
        } else {
            $op = 'S';
        }
        $this->Out(sprintf('%.2F %.2F %.2F %.2F re %s', $flt_x * $this->flt_scale_factor,
            ($this->flt_current_height - $flt_y) * $this->flt_scale_factor, $flt_width * $this->flt_scale_factor,
            -$flt_height * $this->flt_scale_factor, $op));
    }

    /**
     * @param string $str_family
     * @param string $str_style
     * @param string $str_file
     * @param bool $bol_unicode
     */
    public function AddFont($str_family, $str_style = '', $str_file = '', $bol_unicode = false)
    {
        // Add a TrueType, OpenType or Type1 font
        $str_family = strtolower($str_family);
        $str_style = strtoupper($str_style);
        if ($str_style === 'IB') {
            $str_style = 'BI';
        }
        if ($str_file === '') {
            if ($bol_unicode) {
                $str_file = str_replace(' ', '', $str_family) . strtolower($str_style) . '.ttf';
            } else {
                $str_file = str_replace(' ', '', $str_family) . strtolower($str_style) . '.php';
            }
        }
        $fontkey = $str_family . $str_style;
        if (isset($this->arr_fonts[$fontkey])) {
            return;
        }
        if ($bol_unicode) {
            if (defined("_SYSTEM_TTFONTS") && file_exists(_SYSTEM_TTFONTS . $str_file)) {
                $str_ttf_filename = _SYSTEM_TTFONTS . $str_file;
            } else {
                $str_ttf_filename = $this->getFontPath() . $this->str_unifont_path . $str_file;
            }
            $str_unicode_file = $this->str_unifont_path . strtolower(substr($str_file, 0, (strpos($str_file, '.'))));
            $str_unicode_filename = $this->getFontPath() . $str_unicode_file;
            $name = '';
            $originalsize = 0;
            $arr_ttf_stat = stat($str_ttf_filename);
            $flt_underline_pos = 0.00;
            $flt_underline_thickness = 0.00;
            $arr_descriptors = [];
            if (file_exists($str_unicode_filename . $this->str_font_metrics_file_suffix)) {
                include($str_unicode_filename . $this->str_font_metrics_file_suffix);
            }

            if (!isset($type) || !isset($name) || $originalsize != $arr_ttf_stat['size']) {

                $obj_ttf = new TTFontFile();
                $obj_ttf->getMetrics($str_ttf_filename);
                $arr_character_widths = $obj_ttf->getCharWidths();
                $name = preg_replace('/[ ()]/', '', $obj_ttf->getFullName());
                $arr_descriptors = array(
                    'Ascent' => round($obj_ttf->getAscent()),
                    'Descent' => round($obj_ttf->getDescent()),
                    'CapHeight' => round($obj_ttf->getCapHeight()),
                    'Flags' => $obj_ttf->getFlags(),
                    'FontBBox' => '[' . round($obj_ttf->getBbox()[0]) . ' ' . round($obj_ttf->getBbox()[1]) . ' ' . round($obj_ttf->getBbox()[2]) . ' ' . round($obj_ttf->getBbox()[3]) . ']',
                    'ItalicAngle' => $obj_ttf->getItalicAngle(),
                    'StemV' => round($obj_ttf->getStemV()),
                    'MissingWidth' => round($obj_ttf->getDefaultWidth())
                );
                $flt_underline_pos = round($obj_ttf->getUnderlinePosition());
                $flt_underline_thickness = round($obj_ttf->getUnderlineThickness());
                $originalsize = $arr_ttf_stat['size'] + 0;
                $type = self::FONT_TRUETYPE;
                // Generate metrics .php file
                $str_metrics_data = '<?php' . "\n";
                $str_metrics_data .= '$name=\'' . $name . "';\n";
                $str_metrics_data .= '$type=\'' . $type . "';\n";
                $str_metrics_data .= '$arr_descriptors=' . var_export($arr_descriptors, true) . ";\n";
                $str_metrics_data .= '$flt_underline_pos=' . $flt_underline_pos . ";\n";
                $str_metrics_data .= '$flt_underline_thickness=' . $flt_underline_thickness . ";\n";
                $str_metrics_data .= '$ttffile=\'' . str_replace(__DIR__ . "/", "", $str_ttf_filename) . "';\n";
                $str_metrics_data .= '$originalsize=' . $originalsize . ";\n";
                $str_metrics_data .= '$fontkey=\'' . $fontkey . "';\n";
                if (is_writable(dirname($this->getFontPath() . $this->str_unifont_path))) {
                    $this->writeFontFile($str_unicode_filename .  $this->str_font_metrics_file_suffix, $str_metrics_data);
                    $this->writeFontFile($str_unicode_filename . $this->str_character_widths_file_suffix, $arr_character_widths);
                    $this->clearFontFile($str_unicode_filename . $this->str_127_character_widths_file_suffix);
                }
                unset($obj_ttf);
            } else {
                $arr_character_widths = $this->readFontFile($str_unicode_filename . $this->str_character_widths_file_suffix);
            }
            $int_font_count = count($this->arr_fonts) + 1;
            if (!empty($this->str_alias_number_pages)) {
                $arr_numbers = range(0, 57);
            } else {
                $arr_numbers = range(0, 32);
            }
            $this->arr_fonts[$fontkey] = array(
                'i' => $int_font_count,
                'type' => $type,
                'name' => $name,
                'desc' => $arr_descriptors,
                'up' => $flt_underline_pos,
                'ut' => $flt_underline_thickness,
                'cw' => $arr_character_widths,
                'ttffile' => $str_ttf_filename,
                'fontkey' => $fontkey,
                'subset' => $arr_numbers,
                'unifilename' => $str_unicode_filename
            );
            $this->arr_font_files[$fontkey] = array('length1' => $originalsize, 'type' => self::FONT_TRUETYPE, 'ttffile' => $str_ttf_filename);
            $this->arr_font_files[$str_file] = array('type' => self::FONT_TRUETYPE);
            unset($arr_character_widths);
        } else {
            $arr_info = $this->LoadFont($str_file);
            $arr_info['i'] = count($this->arr_fonts) + 1;
            if (!empty($arr_info['diff'])) {
                // Search existing encodings
                $int_key = array_search($arr_info['diff'], $this->arr_encoding_diffs);
                if (!$int_key) {
                    $int_key = count($this->arr_encoding_diffs) + 1;
                    $this->arr_encoding_diffs[$int_key] = $arr_info['diff'];
                }
                $arr_info['diffn'] = $int_key;
            }
            if (!empty($arr_info['file'])) {
                // Embedded font
                if ($arr_info['type'] == 'TrueType') {
                    $this->arr_font_files[$arr_info['file']] = array('length1' => $arr_info['originalsize']);
                } else {
                    $this->arr_font_files[$arr_info['file']] = array(
                        'length1' => $arr_info['size1'],
                        'length2' => $arr_info['size2']
                    );
                }
            }
            $this->arr_fonts[$fontkey] = $arr_info;
        }
    }

    /**
     * @param string $str_family
     * @param string $str_style
     * @param int $int_size
     */
    public function SetFont($str_family, $str_style = '', $int_size = 0)
    {
        // Select a font; size given in points
        if ($str_family == '') {
            $str_family = $this->str_current_font_family;
        } else {
            $str_family = strtolower($str_family);
        }
        $str_style = strtoupper($str_style);
        if (strpos($str_style, 'U') !== false) {
            $this->bol_underline = true;
            $str_style = str_replace('U', '', $str_style);
        } else {
            $this->bol_underline = false;
        }
        if ($str_style == 'IB') {
            $str_style = 'BI';
        }
        if ($int_size == 0) {
            $int_size = $this->int_current_font_size;
        }
        // Test if font is already selected
        if ($this->str_current_font_family == $str_family && $this->str_current_font_style == $str_style && $this->int_current_font_size == $int_size) {
            return;
        }
        // Test if font is already loaded
        $str_font_key = $str_family . $str_style;
        if (!isset($this->arr_fonts[$str_font_key])) {
            // Test if one of the core fonts
            if ($str_family == 'arial') {
                $str_family = 'helvetica';
            }
            if (in_array($str_family, $this->arr_core_fonts)) {
                if ($str_family == 'symbol' || $str_family == 'zapfdingbats') {
                    $str_style = '';
                }
                $str_font_key = $str_family . $str_style;
                if (!isset($this->arr_fonts[$str_font_key])) {
                    $this->AddFont($str_family, $str_style);
                }
            } else {
                $this->Error('Undefined font: ' . $str_family . ' ' . $str_style);
            }
        }
        // Select it
        $this->str_current_font_family = $str_family;
        $this->str_current_font_style = $str_style;
        $this->int_current_font_size = $int_size;
        $this->int_font_size_user = $int_size / $this->flt_scale_factor;
        $this->arr_current_font_info = &$this->arr_fonts[$str_font_key];
        if ($this->arr_fonts[$str_font_key]['type'] == self::FONT_TRUETYPE) {
            $this->bol_uniform_subset = true;
        } else {
            $this->bol_uniform_subset = false;
        }
        if ($this->int_page > 0) {
            $this->Out(sprintf('BT /F%d %.2F Tf ET', $this->arr_current_font_info['i'], $this->int_current_font_size));
        }
    }

    /**
     * @param int $int_size
     */
    public function SetFontSize($int_size)
    {
        // Set font size in points
        if ($this->int_current_font_size == $int_size) {
            return;
        }
        $this->int_current_font_size = $int_size;
        $this->int_font_size_user = $int_size / $this->flt_scale_factor;
        if ($this->int_page > 0) {
            $this->Out(sprintf('BT /F%d %.2F Tf ET', $this->arr_current_font_info['i'], $this->int_current_font_size));
        }
    }

    /**
     * @return int
     */
    public function AddLink()
    {
        // Create a new internal link
        $int_count = count($this->arr_internal_links) + 1;
        $this->arr_internal_links[$int_count] = array(0, 0);
        return $int_count;
    }

    /**
     * @param $mix_link_key
     * @param int $int_y
     * @param int $int_page
     */
    public function SetLink($mix_link_key, $int_y = 0, $int_page = -1)
    {
        // Set destination of internal link
        if ($int_y == -1) {
            $int_y = $this->flt_position_y;
        }
        if ($int_page == -1) {
            $int_page = $this->int_page;
        }
        $this->arr_internal_links[$mix_link_key] = array($int_page, $int_y);
    }

    /**
     * @param float $flt_x
     * @param float $flt_y
     * @param float $flt_width
     * @param float $flt_height
     * @param $mix_link_key
     */
    public function Link($flt_x, $flt_y, $flt_width, $flt_height, $mix_link_key)
    {
        // Put a link on the page
        $this->arr_page_links[$this->int_page][] = array(
            $flt_x * $this->flt_scale_factor,
            $this->flt_height_points - $flt_y * $this->flt_scale_factor,
            $flt_width * $this->flt_scale_factor,
            $flt_height * $this->flt_scale_factor,
            $mix_link_key
        );
    }

    /**
     * @param float $flt_x
     * @param float $flt_y
     * @param string $str_text
     */
    public function Text($flt_x, $flt_y, $str_text)
    {
        // Output a string
        if ($this->bol_uniform_subset) {
            $str_text_2 = '(' . $this->EscapeString($this->UTF8ToUTF16BE($str_text, false)) . ')';
            foreach ($this->UTF8StringToArray($str_text) as $uni) {
                $this->arr_current_font_info['subset'][$uni] = $uni;
            }
        } else {
            $str_text_2 = '(' . $this->EscapeString($str_text) . ')';
        }
        $str_output = sprintf('BT %.2F %.2F Td %s Tj ET', $flt_x * $this->flt_scale_factor,
            ($this->flt_current_height - $flt_y) * $this->flt_scale_factor, $str_text_2);
        if ($this->bol_underline && $str_text != '') {
            $str_output .= ' ' . $this->DoUnderline($flt_x, $flt_y, $str_text);
        }
        if ($this->bol_fill_text_differ) {
            $str_output = 'q ' . $this->str_text_color . ' ' . $str_output . ' Q';
        }
        $this->Out($str_output);
    }

    /**
     * @return bool
     */
    public function AcceptPageBreak()
    {
        // Accept automatic page break or not
        return $this->bol_auto_page_break;
    }

    /**
     * @param float $flt_width
     * @param float $flt_height
     * @param string $str_text
     * @param int $int_border
     * @param int $int_line_number
     * @param string $str_alignment
     * @param bool $bol_fill
     * @param string $str_link
     */
    public function Cell($flt_width, $flt_height = 0, $str_text = '', $int_border = 0, $int_line_number = 0, $str_alignment = '', $bol_fill = false, $str_link = '')
    {
        // Output a cell
        $flt_scale = $this->flt_scale_factor;
        if ($this->flt_position_y + $flt_height > $this->flt_page_break_trigger && !$this->bol_in_header && !$this->bol_in_footer && $this->AcceptPageBreak()) {
            // Automatic page break
            $flt_position_x = $this->flt_position_x;
            $int_word_spacing = $this->int_word_spacing;
            if ($int_word_spacing > 0) {
                $this->int_word_spacing = 0;
                $this->Out('0 Tw');
            }
            $this->AddPage($this->str_current_orientation, $this->arr_current_page_sizes);
            $this->flt_position_x = $flt_position_x;
            if ($int_word_spacing > 0) {
                $this->int_word_spacing = $int_word_spacing;
                $this->Out(sprintf('%.3F Tw', $int_word_spacing * $flt_scale));
            }
        }
        if ($flt_width == 0) {
            $flt_width = $this->flt_current_width - $this->int_right_margin - $this->flt_position_x;
        }
        $str_output = '';
        if ($bol_fill || $int_border == 1) {
            if ($bol_fill) {
                $str_operation = ($int_border == 1) ? 'B' : 'f';
            } else {
                $str_operation = 'S';
            }
            $str_output = sprintf('%.2F %.2F %.2F %.2F re %s ', $this->flt_position_x * $flt_scale,
                ($this->flt_current_height - $this->flt_position_y) * $flt_scale, $flt_width * $flt_scale, -$flt_height * $flt_scale, $str_operation);
        }
        if (is_string($int_border)) {
            $flt_position_x = $this->flt_position_x;
            $flt_position_y = $this->flt_position_y;
            if (strpos($int_border, 'L') !== false) {
                $str_output .= sprintf('%.2F %.2F m %.2F %.2F l S ', $flt_position_x * $flt_scale, ($this->flt_current_height - $flt_position_y) * $flt_scale, $flt_position_x * $flt_scale,
                    ($this->flt_current_height - ($flt_position_y + $flt_height)) * $flt_scale);
            }
            if (strpos($int_border, 'T') !== false) {
                $str_output .= sprintf('%.2F %.2F m %.2F %.2F l S ', $flt_position_x * $flt_scale, ($this->flt_current_height - $flt_position_y) * $flt_scale,
                    ($flt_position_x + $flt_width) * $flt_scale, ($this->flt_current_height - $flt_position_y) * $flt_scale);
            }
            if (strpos($int_border, 'R') !== false) {
                $str_output .= sprintf('%.2F %.2F m %.2F %.2F l S ', ($flt_position_x + $flt_width) * $flt_scale, ($this->flt_current_height - $flt_position_y) * $flt_scale,
                    ($flt_position_x + $flt_width) * $flt_scale, ($this->flt_current_height - ($flt_position_y + $flt_height)) * $flt_scale);
            }
            if (strpos($int_border, 'B') !== false) {
                $str_output .= sprintf('%.2F %.2F m %.2F %.2F l S ', $flt_position_x * $flt_scale, ($this->flt_current_height - ($flt_position_y + $flt_height)) * $flt_scale,
                    ($flt_position_x + $flt_width) * $flt_scale, ($this->flt_current_height - ($flt_position_y + $flt_height)) * $flt_scale);
            }
        }
        if ($str_text !== '') {
            if ($str_alignment == 'R') {
                $flt_dimensions = $flt_width - $this->int_cell_margin - $this->GetStringWidth($str_text);
            } elseif ($str_alignment == 'C') {
                $flt_dimensions = ($flt_width - $this->GetStringWidth($str_text)) / 2;
            } else {
                $flt_dimensions = $this->int_cell_margin;
            }
            if ($this->bol_fill_text_differ) {
                $str_output .= 'q ' . $this->str_text_color . ' ';
            }

            // If multibyte, Tw has no effect - do word spacing using an adjustment before each space
            if ($this->int_word_spacing && $this->bol_uniform_subset) {
                foreach ($this->UTF8StringToArray($str_text) as $mix_unicode) {
                    $this->arr_current_font_info['subset'][$mix_unicode] = $mix_unicode;
                }
                $str_space = $this->EscapeString($this->UTF8ToUTF16BE(' ', false));
                $str_output .= sprintf('BT 0 Tw %.2F %.2F Td [', ($this->flt_position_x + $flt_dimensions) * $flt_scale,
                    ($this->flt_current_height - ($this->flt_position_y + .5 * $flt_height + .3 * $this->int_font_size_user)) * $flt_scale);
                $arr_bits = explode(' ', $str_text);
                $int_bit_count = count($arr_bits);
                for ($i = 0; $i < $int_bit_count; $i++) {
                    $str_converted_text = $arr_bits[$i];
                    $str_converted_text = '(' . $this->EscapeString($this->UTF8ToUTF16BE($str_converted_text, false)) . ')';
                    $str_output .= sprintf('%s ', $str_converted_text);
                    if (($i + 1) < $int_bit_count) {
                        $flt_adjustment = -($this->int_word_spacing * $this->flt_scale_factor) * 1000 / $this->int_current_font_size;
                        $str_output .= sprintf('%d(%s) ', $flt_adjustment, $str_space);
                    }
                }
                $str_output .= '] TJ';
                $str_output .= ' ET';
            } else {
                if ($this->bol_uniform_subset) {
                    $str_text_2 = '(' . $this->EscapeString($this->UTF8ToUTF16BE($str_text, false)) . ')';
                    foreach ($this->UTF8StringToArray($str_text) as $mix_unicode) {
                        $this->arr_current_font_info['subset'][$mix_unicode] = $mix_unicode;
                    }
                } else {
                    $str_text_2 = '(' . str_replace(')', '\\)',
                            str_replace('(', '\\(', str_replace('\\', '\\\\', $str_text))) . ')';
                }
                $str_output .= sprintf('BT %.2F %.2F Td %s Tj ET', ($this->flt_position_x + $flt_dimensions) * $flt_scale,
                    ($this->flt_current_height - ($this->flt_position_y + .5 * $flt_height + .3 * $this->int_font_size_user)) * $flt_scale,
                    $str_text_2);
            }
            if ($this->bol_underline) {
                $str_output .= ' ' . $this->DoUnderline($this->flt_position_x + $flt_dimensions,
                        $this->flt_position_y + .5 * $flt_height + .3 * $this->int_font_size_user, $str_text);
            }
            if ($this->bol_fill_text_differ) {
                $str_output .= ' Q';
            }
            if ($str_link) {
                $this->Link($this->flt_position_x + $flt_dimensions,
                    $this->flt_position_y + .5 * $flt_height - .5 * $this->int_font_size_user, $this->GetStringWidth($str_text),
                    $this->int_font_size_user, $str_link);
            }
        }
        if ($str_output) {
            $this->Out($str_output);
        }
        $this->flt_last_cell_height = $flt_height;
        if ($int_line_number > 0) {
            // Go to next line
            $this->flt_position_y += $flt_height;
            if ($int_line_number == 1) {
                $this->flt_position_x = $this->int_left_margin;
            }
        } else {
            $this->flt_position_x += $flt_width;
        }
    }

    /**
     * @param float $flt_width
     * @param float $flt_height
     * @param string $str_text
     * @param int $int_border
     * @param string $str_alignment
     * @param bool $bol_fill
     * @param int int_maxline
     *
     * @return string
     */
    public function MultiCell($flt_width, $flt_height, $str_text, $int_border = 0, $str_alignment = 'J', $bol_fill = false, $int_maxline = 0)
    {
        // Output text with automatic or explicit line breaks
        $arr_character_width = &$this->arr_current_font_info['cw'];
        if ($flt_width == 0) {
            $flt_width = $this->flt_current_width - $this->int_right_margin - $this->flt_position_x;
        }
        $flt_max_width = ($flt_width - 2 * $this->int_cell_margin);
        $str_text = str_replace("\r", '', $str_text);
        if ($this->bol_uniform_subset) {
            $int_length = mb_strlen($str_text, 'utf-8');
            while ($int_length > 0 && mb_substr($str_text, $int_length - 1, 1, 'utf-8') == "\n") {
                $int_length--;
            }
        } else {
            $int_length = strlen($str_text);
            if ($int_length > 0 && $str_text[$int_length - 1] == "\n") {
                $int_length--;
            }
        }
        $mix_adjusted_border = 0;
        $mix_adjusted_border_2 = 0;
        if ($int_border) {
            if ($int_border == 1) {
                $int_border = 'LTRB';
                $mix_adjusted_border = 'LRT';
                $mix_adjusted_border_2 = 'LR';
            } else {
                $mix_adjusted_border_2 = '';
                if (strpos($int_border, 'L') !== false) {
                    $mix_adjusted_border_2 .= 'L';
                }
                if (strpos($int_border, 'R') !== false) {
                    $mix_adjusted_border_2 .= 'R';
                }
                $mix_adjusted_border = (strpos($int_border, 'T') !== false) ? $mix_adjusted_border_2 . 'T' : $mix_adjusted_border_2;
            }
        }
        $int_sep = -1;
        $int_i = 0;
        $int_j = 0;
        $int_l = 0;
        $int_space_count = 0;
        $int_line_count = 1;
        $int_ls = 0;
        while ($int_i < $int_length) {
            // Get next character
            if ($this->bol_uniform_subset) {
                $str_character = mb_substr($str_text, $int_i, 1, 'UTF-8');
            } else {
                $str_character = $str_text[$int_i];
            }
            if ($str_character == "\n") {
                // Explicit line break
                if ($this->int_word_spacing > 0) {
                    $this->int_word_spacing = 0;
                    $this->Out('0 Tw');
                }
                if ($this->bol_uniform_subset) {
                    $this->Cell($flt_width, $flt_height, mb_substr($str_text, $int_j, $int_i - $int_j, 'UTF-8'), $mix_adjusted_border, 2, $str_alignment, $bol_fill);
                } else {
                    $this->Cell($flt_width, $flt_height, substr($str_text, $int_j, $int_i - $int_j), $mix_adjusted_border, 2, $str_alignment, $bol_fill);
                }
                $int_i++;
                $int_sep = -1;
                $int_j = $int_i;
                $int_l = 0;
                $int_space_count = 0;
                $int_line_count++;
                if ($int_border && $int_line_count == 2) {
                    $mix_adjusted_border = $mix_adjusted_border_2;
                }
                if($int_maxline && $int_line_count > $int_maxline) {
                    return substr($str_text, $int_i);
                }
                continue;
            }
            if ($str_character == ' ') {
                $int_sep = $int_i;
                $int_ls = $int_l;
                $int_space_count++;
            }

            if ($this->bol_uniform_subset) {
                $int_l += $this->GetStringWidth($str_character);
            } else {
                $int_l += $arr_character_width[$str_character] * $this->int_font_size_user / 1000;
            }

            if ($int_l > $flt_max_width) {
                // Automatic line break
                if ($int_sep == -1) {
                    if ($int_i == $int_j) {
                        $int_i++;
                    }
                    if ($this->int_word_spacing > 0) {
                        $this->int_word_spacing = 0;
                        $this->Out('0 Tw');
                    }
                    if ($this->bol_uniform_subset) {
                        $this->Cell($flt_width, $flt_height, mb_substr($str_text, $int_j, $int_i - $int_j, 'UTF-8'), $mix_adjusted_border, 2, $str_alignment, $bol_fill);
                    } else {
                        $this->Cell($flt_width, $flt_height, substr($str_text, $int_j, $int_i - $int_j), $mix_adjusted_border, 2, $str_alignment, $bol_fill);
                    }
                } else {
                    if ($str_alignment == 'J') {
                        $this->int_word_spacing = ($int_space_count > 1) ? ($flt_max_width - $int_ls) / ($int_space_count - 1) : 0;
                        $this->Out(sprintf('%.3F Tw', $this->int_word_spacing * $this->flt_scale_factor));
                    }
                    if ($this->bol_uniform_subset) {
                        $this->Cell($flt_width, $flt_height, mb_substr($str_text, $int_j, $int_sep - $int_j, 'UTF-8'), $mix_adjusted_border, 2, $str_alignment, $bol_fill);
                    } else {
                        $this->Cell($flt_width, $flt_height, substr($str_text, $int_j, $int_sep - $int_j), $mix_adjusted_border, 2, $str_alignment, $bol_fill);
                    }
                    $int_i = $int_sep + 1;
                }
                $int_sep = -1;
                $int_j = $int_i;
                $int_l = 0;
                $int_space_count = 0;
                $int_line_count++;
                if ($int_border && $int_line_count == 2) {
                    $mix_adjusted_border = $mix_adjusted_border_2;
                }

                if ($int_maxline && $int_line_count > $int_maxline) {
                    if ($this->int_word_spacing > 0) {
                        $this->int_word_spacing = 0;
                        $this->Out('0 Tw');
                    }
                    return substr($str_text, $int_i);
                }

            } else {
                $int_i++;
            }
        }
        // Last chunk
        if ($this->int_word_spacing > 0) {
            $this->int_word_spacing = 0;
            $this->Out('0 Tw');
        }
        if ($int_border && strpos($int_border, 'B') !== false) {
            $mix_adjusted_border .= 'B';
        }
        if ($this->bol_uniform_subset) {
            $this->Cell($flt_width, $flt_height, mb_substr($str_text, $int_j, $int_i - $int_j, 'UTF-8'), $mix_adjusted_border, 2, $str_alignment, $bol_fill);
        } else {
            $this->Cell($flt_width, $flt_height, substr($str_text, $int_j, $int_i - $int_j), $mix_adjusted_border, 2, $str_alignment, $bol_fill);
        }
        $this->flt_position_x = $this->int_left_margin;
        return '';
    }

    /**
     * @param float $flt_height
     * @param string $str_text
     * @param string $str_link
     */
    public function Write($flt_height, $str_text, $str_link = '')
    {
        // Output text in flowing mode
        $arr_character_widths = &$this->arr_current_font_info['cw'];
        $flt_width = $this->flt_current_width - $this->int_right_margin - $this->flt_position_x;

        $flt_max_width = ($flt_width - 2 * $this->int_cell_margin);
        $str_text = str_replace("\r", '', $str_text);
        if ($this->bol_uniform_subset) {
            $int_length = mb_strlen($str_text, 'UTF-8');
            if ($int_length == 1 && $str_text == " ") {
                $this->flt_position_x += $this->GetStringWidth($str_text);
                return;
            }
        } else {
            $int_length = strlen($str_text);
        }
        $int_sep = -1;
        $int_i = 0;
        $int_j = 0;
        $flt_string_width = 0;
        $int_line_count = 1;
        while ($int_i < $int_length) {
            // Get next character
            if ($this->bol_uniform_subset) {
                $str_character = mb_substr($str_text, $int_i, 1, 'UTF-8');
            } else {
                $str_character = $str_text[$int_i];
            }
            if ($str_character == "\n") {
                // Explicit line break
                if ($this->bol_uniform_subset) {
                    $this->Cell($flt_width, $flt_height, mb_substr($str_text, $int_j, $int_i - $int_j, 'UTF-8'), 0, 2, '', 0, $str_link);
                } else {
                    $this->Cell($flt_width, $flt_height, substr($str_text, $int_j, $int_i - $int_j), 0, 2, '', 0, $str_link);
                }
                $int_i++;
                $int_sep = -1;
                $int_j = $int_i;
                $flt_string_width = 0;
                if ($int_line_count == 1) {
                    $this->flt_position_x = $this->int_left_margin;
                    $flt_width = $this->flt_current_width - $this->int_right_margin - $this->flt_position_x;
                    $flt_max_width = ($flt_width - 2 * $this->int_cell_margin);
                }
                $int_line_count++;
                continue;
            }
            if ($str_character == ' ') {
                $int_sep = $int_i;
            }

            if ($this->bol_uniform_subset) {
                $flt_string_width += $this->GetStringWidth($str_character);
            } else {
                $flt_string_width += $arr_character_widths[$str_character] * $this->int_font_size_user / 1000;
            }

            if ($flt_string_width > $flt_max_width) {
                // Automatic line break
                if ($int_sep == -1) {
                    if ($this->flt_position_x > $this->int_left_margin) {
                        // Move to next line
                        $this->flt_position_x = $this->int_left_margin;
                        $this->flt_position_y += $flt_height;
                        $flt_width = $this->flt_current_width - $this->int_right_margin - $this->flt_position_x;
                        $flt_max_width = ($flt_width - 2 * $this->int_cell_margin);
                        $int_i++;
                        $int_line_count++;
                        continue;
                    }
                    if ($int_i == $int_j) {
                        $int_i++;
                    }
                    if ($this->bol_uniform_subset) {
                        $this->Cell($flt_width, $flt_height, mb_substr($str_text, $int_j, $int_i - $int_j, 'UTF-8'), 0, 2, '', 0, $str_link);
                    } else {
                        $this->Cell($flt_width, $flt_height, substr($str_text, $int_j, $int_i - $int_j), 0, 2, '', 0, $str_link);
                    }
                } else {
                    if ($this->bol_uniform_subset) {
                        $this->Cell($flt_width, $flt_height, mb_substr($str_text, $int_j, $int_sep - $int_j, 'UTF-8'), 0, 2, '', 0, $str_link);
                    } else {
                        $this->Cell($flt_width, $flt_height, substr($str_text, $int_j, $int_sep - $int_j), 0, 2, '', 0, $str_link);
                    }
                    $int_i = $int_sep + 1;
                }
                $int_sep = -1;
                $int_j = $int_i;
                $flt_string_width = 0;
                if ($int_line_count == 1) {
                    $this->flt_position_x = $this->int_left_margin;
                    $flt_width = $this->flt_current_width - $this->int_right_margin - $this->flt_position_x;
                    $flt_max_width = ($flt_width - 2 * $this->int_cell_margin);
                }
                $int_line_count++;
            } else {
                $int_i++;
            }
        }
        // Last chunk
        if ($int_i != $int_j) {
            if ($this->bol_uniform_subset) {
                $this->Cell($flt_string_width, $flt_height, mb_substr($str_text, $int_j, $int_i - $int_j, 'UTF-8'), 0, 0, '', 0, $str_link);
            } else {
                $this->Cell($flt_string_width, $flt_height, substr($str_text, $int_j), 0, 0, '', 0, $str_link);
            }
        }
    }

    /**
     * @param float|null $flt_height
     */
    public function Ln($flt_height = null)
    {
        // Line feed; default value is last cell height
        $this->flt_position_x = $this->int_left_margin;
        if ($flt_height === null) {
            $this->flt_position_y += $this->flt_last_cell_height;
        } else {
            $this->flt_position_y += $flt_height;
        }
    }

    /**
     * @param string $str_file
     * @param float|null $flt_x
     * @param float|null $flt_y
     * @param int $int_width
     * @param int $int_height
     * @param string $str_type
     * @param string $str_link
     */
    public function Image($str_file, $flt_x = null, $flt_y = null, $int_width = 0, $int_height = 0, $str_type = '', $str_link = '')
    {
        // Put an image on the page
        if (!isset($this->arr_images[$str_file])) {
            // First use of this image, get info
            if ($str_type == '') {
                $int_position = strrpos($str_file, '.');
                if (!$int_position) {
                    $this->Error('Image file has no extension and no type was specified: ' . $str_file);
                }
                $str_type = substr($str_file, $int_position + 1);
            }
            $str_type = strtolower($str_type);
            if ($str_type == 'jpeg') {
                $str_type = 'jpg';
            }
            $str_method = '_parse' . $str_type;
            if (!method_exists($this, $str_method)) {
                $this->Error('Unsupported image type: ' . $str_type);
            }
            $arr_image_info = $this->$str_method($str_file);
            $arr_image_info['i'] = count($this->arr_images) + 1;
            $this->arr_images[$str_file] = $arr_image_info;
        } else {
            $arr_image_info = $this->arr_images[$str_file];
        }

        // Automatic width and height calculation if needed
        if ($int_width == 0 && $int_height == 0) {
            // Put image at 96 dpi
            $int_width = -96;
            $int_height = -96;
        }
        if ($int_width < 0) {
            $int_width = -$arr_image_info['w'] * 72 / $int_width / $this->flt_scale_factor;
        }
        if ($int_height < 0) {
            $int_height = -$arr_image_info['h'] * 72 / $int_height / $this->flt_scale_factor;
        }
        if ($int_width == 0) {
            $int_width = $int_height * $arr_image_info['w'] / $arr_image_info['h'];
        }
        if ($int_height == 0) {
            $int_height = $int_width * $arr_image_info['h'] / $arr_image_info['w'];
        }

        // Flowing mode
        if ($flt_y === null) {
            if ($this->flt_position_y + $int_height > $this->flt_page_break_trigger && !$this->bol_in_header && !$this->bol_in_footer && $this->AcceptPageBreak()) {
                // Automatic page break
                $flt_new_x_pos = $this->flt_position_x;
                $this->AddPage($this->str_current_orientation, $this->arr_current_page_sizes);
                $this->flt_position_x = $flt_new_x_pos;
            }
            $flt_y = $this->flt_position_y;
            $this->flt_position_y += $int_height;
        }

        if ($flt_x === null) {
            $flt_x = $this->flt_position_x;
        }
        $this->Out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q', $int_width * $this->flt_scale_factor,
            $int_height * $this->flt_scale_factor, $flt_x * $this->flt_scale_factor,
            ($this->flt_current_height - ($flt_y + $int_height)) * $this->flt_scale_factor, $arr_image_info['i']));
        if ($str_link) {
            $this->Link($flt_x, $flt_y, $int_width, $int_height, $str_link);
        }
    }

    /**
     * @return mixed
     */
    public function GetX()
    {
        // Get x position
        return $this->flt_position_x;
    }

    /**
     * @param float $flt_position_x
     */
    public function SetX($flt_position_x)
    {
        // Set x position
        if ($flt_position_x >= 0) {
            $this->flt_position_x = $flt_position_x;
        } else {
            $this->flt_position_x = $this->flt_current_width + $flt_position_x;
        }
    }

    /**
     * @return float
     */
    public function GetY()
    {
        // Get y position
        return $this->flt_position_y;
    }

    /**
     * @param float $flt_position_y
     */
    public function SetY($flt_position_y)
    {
        // Set y position and reset x
        $this->flt_position_x = $this->int_left_margin;
        if ($flt_position_y >= 0) {
            $this->flt_position_y = $flt_position_y;
        } else {
            $this->flt_position_y = $this->flt_current_height + $flt_position_y;
        }
    }

    /**
     * @param $x
     * @param $y
     */
    public function SetXY($x, $y)
    {
        // Set x and y positions
        $this->SetY($y);
        $this->SetX($x);
    }

    /**
     * @return string
     */
    public function output()
    {
        // Output PDF to some destination
        if ($this->int_state < self::DOCUMENT_STATE_TERMINATED) {
            $this->Close();
        }

        return $this->str_buffer;
    }

    /**
     * @return string
     */
    protected function getFontPath()
    {
        return $this->str_font_path;
    }

    /**
     * @return string
     */
    protected function getFontWritePath()
    {
        return $this->str_font_write_path;
    }

    /**
     *
     */
    protected function checkOutput()
    {
        if (PHP_SAPI !== 'cli') {
            if (headers_sent($str_file, $int_line)) {
                throw new \RuntimeException("Some data has already been output, can't send PDF file (output started at $str_file:$int_line)");
            }
        }

        if (ob_get_length()) {
            // The output buffer is not empty
            if (preg_match('/^(\xEF\xBB\xBF)?\s*$/', ob_get_contents())) {
                // It contains only a UTF-8 BOM and/or whitespace, let's clean it
                ob_clean();
            } else {
                throw new \RuntimeException('Some data has already been output, can\'t send PDF file');
            }
        }
    }

    /**
     * @param $mix_size
     * @return array|string
     */
    private function getPageSize($mix_size)
    {
        if (is_string($mix_size)) {
            $mix_size = strtolower($mix_size);
            if (!isset($this->arr_standard_page_sizes[$mix_size])) {
                $this->Error('Unknown page size: ' . $mix_size);
            }
            $a = $this->arr_standard_page_sizes[$mix_size];
            return array($a[0] / $this->flt_scale_factor, $a[1] / $this->flt_scale_factor);
        } else {
            if ($mix_size[0] > $mix_size[1]) {
                return array($mix_size[1], $mix_size[0]);
            } else {
                return $mix_size;
            }
        }
    }

    /**
     * @param string $str_orientation
     * @param $mix_size
     */
    private function BeginPage($str_orientation, $mix_size)
    {
        $this->int_page++;
        $this->arr_pages[$this->int_page] = '';
        $this->int_state = self::DOCUMENT_STATE_CREATING;
        $this->flt_position_x = $this->int_left_margin;
        $this->flt_position_y = $this->int_top_margin;
        $this->str_current_font_family = '';
        // Check page size and orientation
        if ($str_orientation == '') {
            $str_orientation = $this->str_default_orientation;
        } else {
            $str_orientation = strtoupper($str_orientation[0]);
        }
        if ($mix_size == '') {
            $mix_size = $this->arr_default_page_sizes;
        } else {
            $mix_size = $this->getPageSize($mix_size);
        }
        if ($str_orientation != $this->str_current_orientation || $mix_size[0] != $this->arr_current_page_sizes[0] || $mix_size[1] != $this->arr_current_page_sizes[1]) {
            // New size or orientation
            if ($str_orientation == self::ORIENTATION_PORTRAIT) {
                $this->flt_current_width = $mix_size[0];
                $this->flt_current_height = $mix_size[1];
            } else { // landscape
                $this->flt_current_width = $mix_size[1];
                $this->flt_current_height = $mix_size[0];
            }
            $this->flt_width_points = $this->flt_current_width * $this->flt_scale_factor;
            $this->flt_height_points = $this->flt_current_height * $this->flt_scale_factor;
            $this->flt_page_break_trigger = $this->flt_current_height - $this->int_break_margin;
            $this->str_current_orientation = $str_orientation;
            $this->arr_current_page_sizes = $mix_size;
        }
        if ($str_orientation != $this->str_default_orientation || $mix_size[0] != $this->arr_default_page_sizes[0] || $mix_size[1] != $this->arr_default_page_sizes[1]) {
            $this->arr_page_sizes[$this->int_page] = array($this->flt_width_points, $this->flt_height_points);
        }
    }

    /**
     *
     */
    private function EndPage()
    {
        $this->int_state = self::DOCUMENT_STATE_INITIALIZED;
    }

    /**
     * @param string $str_font
     * @return array
     */
    private function LoadFont($str_font)
    {
        // Load a font definition file from the font directory
        include($this->str_font_path . $str_font);
        $arr_defined_vars = get_defined_vars();
        if (!isset($arr_defined_vars['name'])) {
            $this->Error('Could not include font definition file');
        }
        return $arr_defined_vars;
    }

    /**
     * @param string $str_text
     * @return mixed
     */
    protected function EscapeString($str_text)
    {
        // Escape special characters in strings
        $str_text = str_replace('\\', '\\\\', $str_text);
        $str_text = str_replace('(', '\\(', $str_text);
        $str_text = str_replace(')', '\\)', $str_text);
        $str_text = str_replace("\r", '\\r', $str_text);
        return $str_text;
    }

    /**
     * @param string $s
     * @return string
     */
    protected function TextString($s)
    {
        // Format a text string
        return '(' . $this->EscapeString($s) . ')';
    }

    /**
     * @param string $str_text
     * @return string
     */
    private function UTF8toUTF16($str_text)
    {
        // Convert UTF-8 to UTF-16BE with BOM
        $str_res = "\xFE\xFF";
        $int_length = strlen($str_text);
        $i = 0;
        while ($i < $int_length) {
            $int_character_1 = ord($str_text[$i++]);
            if ($int_character_1 >= 224) {
                // 3-byte character
                $int_character_2 = ord($str_text[$i++]);
                $int_character_3 = ord($str_text[$i++]);
                $str_res .= chr((($int_character_1 & 0x0F) << 4) + (($int_character_2 & 0x3C) >> 2));
                $str_res .= chr((($int_character_2 & 0x03) << 6) + ($int_character_3 & 0x3F));
            } elseif ($int_character_1 >= 192) {
                // 2-byte character
                $int_character_2 = ord($str_text[$i++]);
                $str_res .= chr(($int_character_1 & 0x1C) >> 2);
                $str_res .= chr((($int_character_1 & 0x03) << 6) + ($int_character_2 & 0x3F));
            } else {
                // Single-byte character
                $str_res .= "\0" . chr($int_character_1);
            }
        }
        return $str_res;
    }

    private function DoUnderline($flt_x, $flt_y, $str_text)
    {
        // Underline text
        $flt_underline_position = $this->arr_current_font_info['up'];
        $flt_underline_thickness = $this->arr_current_font_info['ut'];
        $flt_width = $this->GetStringWidth($str_text) + $this->int_word_spacing * substr_count($str_text, ' ');
        return sprintf('%.2F %.2F %.2F %.2F re f', $flt_x * $this->flt_scale_factor,
            ($this->flt_current_height - ($flt_y - $flt_underline_position / 1000 * $this->int_font_size_user)) * $this->flt_scale_factor,
            $flt_width * $this->flt_scale_factor, -$flt_underline_thickness / 1000 * $this->int_current_font_size);
    }

    /**
     * @param string $str_file
     * @return array
     */
    private function _parsejpg($str_file)
    {
        // Extract info from a JPEG file
        $arr_size_data = getimagesize($str_file);
        if (!$arr_size_data) {
            $this->Error('Missing or incorrect image file: ' . $str_file);
        }
        if ($arr_size_data[2] != 2) {
            $this->Error('Not a JPEG file: ' . $str_file);
        }
        if (!isset($arr_size_data['channels']) || $arr_size_data['channels'] == 3) {
            $str_color_space = 'DeviceRGB';
        } elseif ($arr_size_data['channels'] == 4) {
            $str_color_space = 'DeviceCMYK';
        } else {
            $str_color_space = 'DeviceGray';
        }
        $int_bits_per_component = isset($arr_size_data['bits']) ? $arr_size_data['bits'] : 8;
        $str_data = file_get_contents($str_file);
        return array('w' => $arr_size_data[0], 'h' => $arr_size_data[1], 'cs' => $str_color_space, 'bpc' => $int_bits_per_component, 'f' => 'DCTDecode', 'data' => $str_data);
    }

    /**
     * @param string $str_file
     * @return array
     */
    private function _parsepng($str_file)
    {
        // Extract info from a PNG file
        $ptr_file = fopen($str_file, 'rb');
        if (!$ptr_file) {
            $this->Error('Can\'t open image file: ' . $str_file);
        }
        $arr_info = $this->_parsepngstream($ptr_file, $str_file);
        fclose($ptr_file);
        return $arr_info;
    }

    /**
     * @param $ptr_file
     * @param string $str_file
     * @return array
     */
    private function _parsepngstream($ptr_file, $str_file)
    {
        // Check signature
        if ($this->_readstream($ptr_file, 8) != chr(137) . 'PNG' . chr(13) . chr(10) . chr(26) . chr(10)) {
            $this->Error('Not a PNG file: ' . $str_file);
        }

        // Read header chunk
        $this->_readstream($ptr_file, 4);
        if ($this->_readstream($ptr_file, 4) != 'IHDR') {
            $this->Error('Incorrect PNG file: ' . $str_file);
        }
        $int_width = $this->_readint($ptr_file);
        $int_height = $this->_readint($ptr_file);
        $int_bits_per_component = ord($this->_readstream($ptr_file, 1));
        if ($int_bits_per_component > 8) {
            $this->Error('16-bit depth not supported: ' . $str_file);
        }
        $int_color_channels = ord($this->_readstream($ptr_file, 1));
        if ($int_color_channels == 0 || $int_color_channels == 4) {
            $str_color_space = 'DeviceGray';
        } elseif ($int_color_channels == 2 || $int_color_channels == 6) {
            $str_color_space = 'DeviceRGB';
        } elseif ($int_color_channels == 3) {
            $str_color_space = 'Indexed';
        } else {
            $this->Error('Unknown color type: ' . $str_file);
        }
        if (ord($this->_readstream($ptr_file, 1)) != 0) {
            $this->Error('Unknown compression method: ' . $str_file);
        }
        if (ord($this->_readstream($ptr_file, 1)) != 0) {
            $this->Error('Unknown filter method: ' . $str_file);
        }
        if (ord($this->_readstream($ptr_file, 1)) != 0) {
            $this->Error('Interlacing not supported: ' . $str_file);
        }
        $this->_readstream($ptr_file, 4);
        $str_predictor = '/Predictor 15 /Colors ' . ($str_color_space == 'DeviceRGB' ? 3 : 1) . ' /BitsPerComponent ' . $int_bits_per_component . ' /Columns ' . $int_width;

        // Scan chunks looking for palette, transparency and image data
        $str_palette = '';
        $arr_transparency_data = '';
        $str_data = '';
        do {
            $int_line = $this->_readint($ptr_file);
            $str_type = $this->_readstream($ptr_file, 4);
            if ($str_type == 'PLTE') {
                // Read palette
                $str_palette = $this->_readstream($ptr_file, $int_line);
                $this->_readstream($ptr_file, 4);
            } elseif ($str_type == 'tRNS') {
                // Read transparency info
                $str_transparency = $this->_readstream($ptr_file, $int_line);
                if ($int_color_channels == 0) {
                    $arr_transparency_data = array(ord(substr($str_transparency, 1, 1)));
                } elseif ($int_color_channels == 2) {
                    $arr_transparency_data = array(ord(substr($str_transparency, 1, 1)), ord(substr($str_transparency, 3, 1)), ord(substr($str_transparency, 5, 1)));
                } else {
                    $int_pos = strpos($str_transparency, chr(0));
                    if ($int_pos !== false) {
                        $arr_transparency_data = array($int_pos);
                    }
                }
                $this->_readstream($ptr_file, 4);
            } elseif ($str_type == 'IDAT') {
                // Read image data block
                $str_data .= $this->_readstream($ptr_file, $int_line);
                $this->_readstream($ptr_file, 4);
            } elseif ($str_type == 'IEND') {
                break;
            } else {
                $this->_readstream($ptr_file, $int_line + 4);
            }
        } while ($int_line);

        if ($str_color_space == 'Indexed' && empty($str_palette)) {
            $this->Error('Missing palette in ' . $str_file);
        }
        $arr_info = array(
            'w' => $int_width,
            'h' => $int_height,
            'cs' => $str_color_space,
            'bpc' => $int_bits_per_component,
            'f' => 'FlateDecode',
            'dp' => $str_predictor,
            'pal' => $str_palette,
            'trns' => $arr_transparency_data
        );
        if ($int_color_channels >= 4) {
            // Extract alpha channel
            if (!function_exists('gzuncompress')) {
                $this->Error('Zlib not available, can\'t handle alpha channel: ' . $str_file);
            }
            $str_data = gzuncompress($str_data);
            $str_color = '';
            $str_alpha = '';
            if ($int_color_channels == 4) {
                // Gray image
                $int_length = 2 * $int_width;
                for ($i = 0; $i < $int_height; $i++) {
                    $int_pos = (1 + $int_length) * $i;
                    $str_color .= $str_data[$int_pos];
                    $str_alpha .= $str_data[$int_pos];
                    $str_line = substr($str_data, $int_pos + 1, $int_length);
                    $str_color .= preg_replace('/(.)./s', '$1', $str_line);
                    $str_alpha .= preg_replace('/.(.)/s', '$1', $str_line);
                }
            } else {
                // RGB image
                $int_length = 4 * $int_width;
                for ($i = 0; $i < $int_height; $i++) {
                    $int_pos = (1 + $int_length) * $i;
                    $str_color .= $str_data[$int_pos];
                    $str_alpha .= $str_data[$int_pos];
                    $str_line = substr($str_data, $int_pos + 1, $int_length);
                    $str_color .= preg_replace('/(.{3})./s', '$1', $str_line);
                    $str_alpha .= preg_replace('/.{3}(.)/s', '$1', $str_line);
                }
            }
            unset($str_data);
            $str_data = gzcompress($str_color);
            $arr_info['smask'] = gzcompress($str_alpha);
            if ($this->str_pdf_version < '1.4') {
                $this->str_pdf_version = '1.4';
            }
        }
        $arr_info['data'] = $str_data;
        return $arr_info;
    }

    /**
     * @param $ptr_file
     * @param int $int_bytes
     * @return string
     */
    private function _readstream($ptr_file, $int_bytes)
    {
        // Read n bytes from stream
        $str_result = '';
        while ($int_bytes > 0 && !feof($ptr_file)) {
            $str_data = fread($ptr_file, $int_bytes);
            if ($str_data === false) {
                $this->Error('Error while reading stream');
            }
            $int_bytes -= strlen($str_data);
            $str_result .= $str_data;
        }
        if ($int_bytes > 0) {
            $this->Error('Unexpected end of stream');
        }
        return $str_result;
    }

    /**
     * @param $ptr_file
     * @return mixed
     */
    private function _readint($ptr_file)
    {
        // Read a 4-byte integer from stream
        $arr_data = unpack('Ni', $this->_readstream($ptr_file, 4));
        return $arr_data['i'];
    }

    /**
     * @param string $str_file
     * @return array
     */
    private function _parsegif($str_file)
    {
        // Extract info from a GIF file (via PNG conversion)
        if (!function_exists('imagepng')) {
            $this->Error('GD extension is required for GIF support');
        }
        if (!function_exists('imagecreatefromgif')) {
            $this->Error('GD has no GIF read support');
        }
        $obj_image = imagecreatefromgif($str_file);
        if (!$obj_image) {
            $this->Error('Missing or incorrect image file: ' . $str_file);
        }
        imageinterlace($obj_image, 0);
        $ptr_file = @fopen('php://temp', 'rb+');
        if ($ptr_file) {
            // Perform conversion in memory
            ob_start();
            imagepng($obj_image);
            $str_data = ob_get_clean();
            imagedestroy($obj_image);
            fwrite($ptr_file, $str_data);
            rewind($ptr_file);
            $arr_info = $this->_parsepngstream($ptr_file, $str_file);
            fclose($ptr_file);
        } else {
            // Use temporary file
            $str_tmp = tempnam('.', 'gif');
            if (!$str_tmp) {
                $this->Error('Unable to create a temporary file');
            }
            if (!imagepng($obj_image, $str_tmp)) {
                $this->Error('Error while saving to temporary file');
            }
            imagedestroy($obj_image);
            $arr_info = $this->_parsepng($str_tmp);
            unlink($str_tmp);
        }
        return $arr_info;
    }

    /**
     * Creates a new object
     */
    protected function NewObject()
    {
        $this->int_current_object++;
        $this->arr_offsets[$this->int_current_object] = strlen($this->str_buffer);
        $this->Out($this->int_current_object . ' 0 obj');
    }

    /**
     * @param string $str_data
     */
    protected function PutStream($str_data)
    {
        $this->Out('stream');
        $this->Out($str_data);
        $this->Out('endstream');
    }

    /**
     * @param string $str_data
     */
    protected function Out($str_data)
    {
        // Add a line to the document
        if ($this->int_state == self::DOCUMENT_STATE_CREATING) {
            $this->arr_pages[$this->int_page] .= $str_data . "\n";
        } else {
            $this->str_buffer .= $str_data . "\n";
        }
    }

    /**
     * Puts the pages into the document
     */
    protected function PutPages()
    {
        $int_page = $this->int_page;
        if (!empty($this->str_alias_number_pages)) {
            // Replace number of pages in fonts using subsets
            $str_alias = $this->UTF8ToUTF16BE($this->str_alias_number_pages, false);
            $str_replace = $this->UTF8ToUTF16BE("$int_page", false);
            for ($n = 1; $n <= $int_page; $n++) {
                $this->arr_pages[$n] = str_replace($str_alias, $str_replace, $this->arr_pages[$n]);
            }
            // Now repeat for no pages in non-subset fonts
            for ($n = 1; $n <= $int_page; $n++) {
                $this->arr_pages[$n] = str_replace($this->str_alias_number_pages, $int_page, $this->arr_pages[$n]);
            }
        }
        if ($this->str_default_orientation == self::ORIENTATION_PORTRAIT) {
            $flt_page_width = $this->arr_default_page_sizes[0] * $this->flt_scale_factor;
            $flt_page_height = $this->arr_default_page_sizes[1] * $this->flt_scale_factor;
        } else {
            $flt_page_width = $this->arr_default_page_sizes[1] * $this->flt_scale_factor;
            $flt_page_height = $this->arr_default_page_sizes[0] * $this->flt_scale_factor;
        }
        $str_filter = ($this->bol_compress) ? '/Filter /FlateDecode ' : '';
        for ($n = 1; $n <= $int_page; $n++) {
            // Page
            $this->NewObject();
            $this->Out('<</Type /Page');
            $this->Out('/Parent 1 0 R');
            if (isset($this->arr_page_sizes[$n])) {
                $this->Out(sprintf('/MediaBox [0 0 %.2F %.2F]', $this->arr_page_sizes[$n][0],
                    $this->arr_page_sizes[$n][1]));
            }
            $this->Out('/Resources 2 0 R');
            if (isset($this->arr_page_links[$n])) {
                // Links
                $str_annotations = '/Annots [';
                foreach ($this->arr_page_links[$n] as $arr_page_link) {
                    $str_rectangle = sprintf('%.2F %.2F %.2F %.2F', $arr_page_link[0], $arr_page_link[1], $arr_page_link[0] + $arr_page_link[2], $arr_page_link[1] - $arr_page_link[3]);
                    $str_annotations .= '<</Type /Annot /Subtype /Link /Rect [' . $str_rectangle . '] /Border [0 0 0] ';
                    if (is_string($arr_page_link[4])) {
                        $str_annotations .= '/A <</S /URI /URI ' . $this->TextString($arr_page_link[4]) . '>>>>';
                    } else {
                        $arr_link = $this->arr_internal_links[$arr_page_link[4]];
                        $flt_height = isset($this->arr_page_sizes[$arr_link[0]]) ? $this->arr_page_sizes[$arr_link[0]][1] : $flt_page_height;
                        $str_annotations .= sprintf('/Dest [%d 0 R /XYZ 0 %.2F null]>>', 1 + 2 * $arr_link[0],
                            $flt_height - $arr_link[1] * $this->flt_scale_factor);
                    }
                }
                $this->Out($str_annotations . ']');
            }
            if ($this->str_pdf_version > '1.3') {
                $this->Out('/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>');
            }
            $this->Out('/Contents ' . ($this->int_current_object + 1) . ' 0 R>>');
            $this->Out('endobj');
            // Page content
            $str_page_content = ($this->bol_compress) ? gzcompress($this->arr_pages[$n]) : $this->arr_pages[$n];
            $this->NewObject();
            $this->Out('<<' . $str_filter . '/Length ' . strlen($str_page_content) . '>>');
            $this->PutStream($str_page_content);
            $this->Out('endobj');
        }
        // Pages root
        $this->arr_offsets[1] = strlen($this->str_buffer);
        $this->Out('1 0 obj');
        $this->Out('<</Type /Pages');
        $str_kids = '/Kids [';
        for ($i = 0; $i < $int_page; $i++) {
            $str_kids .= (3 + 2 * $i) . ' 0 R ';
        }
        $this->Out($str_kids . ']');
        $this->Out('/Count ' . $int_page);
        $this->Out(sprintf('/MediaBox [0 0 %.2F %.2F]', $flt_page_width, $flt_page_height));
        $this->Out('>>');
        $this->Out('endobj');
    }

    /**
     *
     */
    public function PutFonts()
    {
        $int_current_object = $this->int_current_object;
        foreach ($this->arr_encoding_diffs as $str_diff) {
            // Encodings
            $this->NewObject();
            $this->Out('<</Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [' . $str_diff . ']>>');
            $this->Out('endobj');
        }
        foreach ($this->arr_font_files as $str_file => $arr_info) {
            if (!isset($arr_info['type']) || $arr_info['type'] != self::FONT_TRUETYPE) {
                // Font file embedding
                $this->NewObject();
                $this->arr_font_files[$str_file]['n'] = $this->int_current_object;
                $str_font = $this->readFontFile($this->getFontPath() . $str_file);
                /*
                $ptr_file = fopen($this->getFontPath() . $str_file, 'rb', 1);
                if (!$ptr_file) {
                    $this->Error('Font file not found');
                }
                while (!feof($ptr_file)) {
                    $str_font .= fread($ptr_file, 8192);
                }
                fclose($ptr_file);
                */
                $bol_compressed_file = (substr($str_file, -2) == '.z');
                if (!$bol_compressed_file && isset($arr_info['length2'])) {
                    $bol_header = (ord($str_font[0]) == 128);
                    if ($bol_header) {
                        // Strip first binary header
                        $str_font = substr($str_font, 6);
                    }
                    if ($bol_header && ord($str_font[$arr_info['length1']]) == 128) {
                        // Strip second binary header
                        $str_font = substr($str_font, 0, $arr_info['length1']) . substr($str_font, $arr_info['length1'] + 6);
                    }
                }
                $this->Out('<</Length ' . strlen($str_font));
                if ($bol_compressed_file) {
                    $this->Out('/Filter /FlateDecode');
                }
                $this->Out('/Length1 ' . $arr_info['length1']);
                if (isset($arr_info['length2'])) {
                    $this->Out('/Length2 ' . $arr_info['length2'] . ' /Length3 0');
                }
                $this->Out('>>');
                $this->PutStream($str_font);
                $this->Out('endobj');
            }
        }
        foreach ($this->arr_fonts as $str_key => $arr_font_data) {
            // Font objects
            //$this->fonts[$k]['n']=$this->n+1;
            $str_type = $arr_font_data['type'];
            $str_name = $arr_font_data['name'];
            if ($str_type == 'Core') {
                // Standard font
                $this->arr_fonts[$str_key]['n'] = $this->int_current_object + 1;
                $this->NewObject();
                $this->Out('<</Type /Font');
                $this->Out('/BaseFont /' . $str_name);
                $this->Out('/Subtype /Type1');
                if ($str_name != 'Symbol' && $str_name != 'ZapfDingbats') {
                    $this->Out('/Encoding /WinAnsiEncoding');
                }
                $this->Out('>>');
                $this->Out('endobj');
            } elseif ($str_type == 'Type1' || $str_type == 'TrueType') {
                // Additional Type1 or TrueType font
                $this->arr_fonts[$str_key]['n'] = $this->int_current_object + 1;
                $this->NewObject();
                $this->Out('<</Type /Font');
                $this->Out('/BaseFont /' . $str_name);
                $this->Out('/Subtype /' . $str_type);
                $this->Out('/FirstChar 32 /LastChar 255');
                $this->Out('/Widths ' . ($this->int_current_object + 1) . ' 0 R');
                $this->Out('/FontDescriptor ' . ($this->int_current_object + 2) . ' 0 R');
                if ($arr_font_data['enc']) {
                    if (isset($arr_font_data['diff'])) {
                        $this->Out('/Encoding ' . ($int_current_object + $arr_font_data['diff']) . ' 0 R');
                    } else {
                        $this->Out('/Encoding /WinAnsiEncoding');
                    }
                }
                $this->Out('>>');
                $this->Out('endobj');
                // Widths
                $this->NewObject();
                $int_character_width =& $arr_font_data['cw'];
                $str_data = '[';
                for ($i = 32; $i <= 255; $i++) {
                    $str_data .= $int_character_width[chr($i)] . ' ';
                }
                $this->Out($str_data . ']');
                $this->Out('endobj');
                // Descriptor
                $this->NewObject();
                $str_data = '<</Type /FontDescriptor /FontName /' . $str_name;
                foreach ($arr_font_data['desc'] as $str_inner_key => $str_value) {
                    $str_data .= ' /' . $str_inner_key . ' ' . $str_value;
                }
                $str_file = $arr_font_data['file'];
                if ($str_file) {
                    $str_data .= ' /FontFile' . ($str_type == 'Type1' ? '' : '2') . ' ' . $this->arr_font_files[$str_file]['n'] . ' 0 R';
                }
                $this->Out($str_data . '>>');
                $this->Out('endobj');
            } // TrueType embedded SUBSETS or FULL
            else {
                if ($str_type === self::FONT_TRUETYPE) {
                    $this->arr_fonts[$str_key]['n'] = $this->int_current_object + 1;

                    $obj_ttf = new TTFontFile();
                    $str_font_name = 'MPDFAA' . '+' . $arr_font_data['name'];
                    $str_subset = $arr_font_data['subset'];
                    unset($str_subset[0]);
                    $str_ttf_font_stream = $obj_ttf->makeSubset($arr_font_data['ttffile'], $str_subset);
                    $int_ttf_font_size = strlen($str_ttf_font_stream);
                    $str_font_stream_compressed = gzcompress($str_ttf_font_stream);
                    $str_code_to_glyph = $obj_ttf->getCodeToGlyph();
                    unset($str_code_to_glyph[0]);

                    // Type0 Font
                    // A composite font - a font composed of other fonts, organized hierarchically
                    $this->NewObject();
                    $this->Out('<</Type /Font');
                    $this->Out('/Subtype /Type0');
                    $this->Out('/BaseFont /' . $str_font_name . '');
                    $this->Out('/Encoding /Identity-H');
                    $this->Out('/DescendantFonts [' . ($this->int_current_object + 1) . ' 0 R]');
                    $this->Out('/ToUnicode ' . ($this->int_current_object + 2) . ' 0 R');
                    $this->Out('>>');
                    $this->Out('endobj');

                    // CIDFontType2
                    // A CIDFont whose glyph descriptions are based on TrueType font technology
                    $this->NewObject();
                    $this->Out('<</Type /Font');
                    $this->Out('/Subtype /CIDFontType2');
                    $this->Out('/BaseFont /' . $str_font_name . '');
                    $this->Out('/CIDSystemInfo ' . ($this->int_current_object + 2) . ' 0 R');
                    $this->Out('/FontDescriptor ' . ($this->int_current_object + 3) . ' 0 R');
                    if (isset($arr_font_data['desc']['MissingWidth'])) {
                        $this->Out('/DW ' . $arr_font_data['desc']['MissingWidth'] . '');
                    }

                    $this->PutTTFontWidths($arr_font_data, $obj_ttf->getMaxUni());

                    $this->Out('/CIDToGIDMap ' . ($this->int_current_object + 4) . ' 0 R');
                    $this->Out('>>');
                    $this->Out('endobj');

                    // ToUnicode
                    $this->NewObject();
                    $str_to_unicode = "/CIDInit /ProcSet findresource begin\n";
                    $str_to_unicode .= "12 dict begin\n";
                    $str_to_unicode .= "begincmap\n";
                    $str_to_unicode .= "/CIDSystemInfo\n";
                    $str_to_unicode .= "<</Registry (Adobe)\n";
                    $str_to_unicode .= "/Ordering (UCS)\n";
                    $str_to_unicode .= "/Supplement 0\n";
                    $str_to_unicode .= ">> def\n";
                    $str_to_unicode .= "/CMapName /Adobe-Identity-UCS def\n";
                    $str_to_unicode .= "/CMapType 2 def\n";
                    $str_to_unicode .= "1 begincodespacerange\n";
                    $str_to_unicode .= "<0000> <FFFF>\n";
                    $str_to_unicode .= "endcodespacerange\n";
                    $str_to_unicode .= "1 beginbfrange\n";
                    $str_to_unicode .= "<0000> <FFFF> <0000>\n";
                    $str_to_unicode .= "endbfrange\n";
                    $str_to_unicode .= "endcmap\n";
                    $str_to_unicode .= "CMapName currentdict /CMap defineresource pop\n";
                    $str_to_unicode .= "end\n";
                    $str_to_unicode .= "end";
                    $this->Out('<</Length ' . (strlen($str_to_unicode)) . '>>');
                    $this->PutStream($str_to_unicode);
                    $this->Out('endobj');

                    // CIDSystemInfo dictionary
                    $this->NewObject();
                    $this->Out('<</Registry (Adobe)');
                    $this->Out('/Ordering (UCS)');
                    $this->Out('/Supplement 0');
                    $this->Out('>>');
                    $this->Out('endobj');

                    // Font descriptor
                    $this->NewObject();
                    $this->Out('<</Type /FontDescriptor');
                    $this->Out('/FontName /' . $str_font_name);
                    foreach ($arr_font_data['desc'] as $str_inner_inner_key => $str_value) {
                        if ($str_inner_inner_key == 'Flags') {
                            $str_value = $str_value | 4;
                            $str_value = $str_value & ~32;
                        }   // SYMBOLIC font flag
                        $this->Out(' /' . $str_inner_inner_key . ' ' . $str_value);
                    }
                    $this->Out('/FontFile2 ' . ($this->int_current_object + 2) . ' 0 R');
                    $this->Out('>>');
                    $this->Out('endobj');

                    // Embed CIDToGIDMap
                    // A specification of the mapping from CIDs to glyph indices
                    $str_cid_to_gid_map = str_pad('', 256 * 256 * 2, "\x00");
                    foreach ($str_code_to_glyph as $cc => $str_clyph) {
                        $str_cid_to_gid_map[$cc * 2] = chr($str_clyph >> 8);
                        $str_cid_to_gid_map[$cc * 2 + 1] = chr($str_clyph & 0xFF);
                    }
                    $str_cid_to_gid_map = gzcompress($str_cid_to_gid_map);
                    $this->NewObject();
                    $this->Out('<</Length ' . strlen($str_cid_to_gid_map) . '');
                    $this->Out('/Filter /FlateDecode');
                    $this->Out('>>');
                    $this->PutStream($str_cid_to_gid_map);
                    $this->Out('endobj');

                    //Font file
                    $this->NewObject();
                    $this->Out('<</Length ' . strlen($str_font_stream_compressed));
                    $this->Out('/Filter /FlateDecode');
                    $this->Out('/Length1 ' . $int_ttf_font_size);
                    $this->Out('>>');
                    $this->PutStream($str_font_stream_compressed);
                    $this->Out('endobj');
                    unset($obj_ttf);
                } else {
                    // Allow for additional types
                    $this->arr_fonts[$str_key]['n'] = $this->int_current_object + 1;
                    $str_method = '_put' . strtolower($str_type);
                    if (!method_exists($this, $str_method)) {
                        $this->Error('Unsupported font type: ' . $str_type);
                    }
                    $this->$str_method($arr_font_data);
                }
            }
        }
    }

    function PutTTFontWidths(&$font, $maxUni)
    {
        $str_font_file = $font['unifilename'] . $this->str_127_character_widths_file_suffix;
        if (file_exists($str_font_file)) {
            include($str_font_file);
            $startcid = 128;
        } else {
            $rangeid = 0;
            $range = array();
            $prevcid = -2;
            $prevwidth = -1;
            $interval = false;
            $startcid = 1;
        }
        $cwlen = $maxUni + 1;
        // for each character
        for ($cid = $startcid; $cid < $cwlen; $cid++) {
            if ($cid == 128 && (!file_exists($str_font_file))) {
                if (is_writable(dirname($this->getFontPath() . $this->str_unifont_path))) {
                    $fh = fopen($str_font_file, "wb");
                    $cw127 = '<?php' . "\n";
                    $cw127 .= '$rangeid=' . $rangeid . ";\n";
                    $cw127 .= '$prevcid=' . $prevcid . ";\n";
                    $cw127 .= '$prevwidth=' . $prevwidth . ";\n";
                    if ($interval) {
                        $cw127 .= '$interval=true' . ";\n";
                    } else {
                        $cw127 .= '$interval=false' . ";\n";
                    }
                    $cw127 .= '$range=' . var_export($range, true) . ";\n";
                    $cw127 .= "?>";
                    fwrite($fh, $cw127, strlen($cw127));
                    fclose($fh);
                }
            }
            if ($font['cw'][$cid * 2] == "\00" && $font['cw'][$cid * 2 + 1] == "\00") {
                continue;
            }
            $width = (ord($font['cw'][$cid * 2]) << 8) + ord($font['cw'][$cid * 2 + 1]);
            if ($width == 65535) {
                $width = 0;
            }
            if ($cid > 255 && (!isset($font['subset'][$cid]) || !$font['subset'][$cid])) {
                continue;
            }
            if (!isset($font['dw']) || (isset($font['dw']) && $width != $font['dw'])) {
                if ($cid == ($prevcid + 1)) {
                    if ($width == $prevwidth) {
                        if ($width == $range[$rangeid][0]) {
                            $range[$rangeid][] = $width;
                        } else {
                            array_pop($range[$rangeid]);
                            // new range
                            $rangeid = $prevcid;
                            $range[$rangeid] = array();
                            $range[$rangeid][] = $prevwidth;
                            $range[$rangeid][] = $width;
                        }
                        $interval = true;
                        $range[$rangeid]['interval'] = true;
                    } else {
                        if ($interval) {
                            // new range
                            $rangeid = $cid;
                            $range[$rangeid] = array();
                            $range[$rangeid][] = $width;
                        } else {
                            $range[$rangeid][] = $width;
                        }
                        $interval = false;
                    }
                } else {
                    $rangeid = $cid;
                    $range[$rangeid] = array();
                    $range[$rangeid][] = $width;
                    $interval = false;
                }
                $prevcid = $cid;
                $prevwidth = $width;
            }
        }
        $prevk = -1;
        $nextk = -1;
        $prevint = false;
        foreach ($range as $k => $ws) {
            $cws = count($ws);
            if (($k == $nextk) AND (!$prevint) AND ((!isset($ws['interval'])) OR ($cws < 4))) {
                if (isset($range[$k]['interval'])) {
                    unset($range[$k]['interval']);
                }
                $range[$prevk] = array_merge($range[$prevk], $range[$k]);
                unset($range[$k]);
            } else {
                $prevk = $k;
            }
            $nextk = $k + $cws;
            if (isset($ws['interval'])) {
                if ($cws > 3) {
                    $prevint = true;
                } else {
                    $prevint = false;
                }
                unset($range[$k]['interval']);
                --$nextk;
            } else {
                $prevint = false;
            }
        }
        $w = '';
        foreach ($range as $k => $ws) {
            if (count(array_count_values($ws)) == 1) {
                $w .= ' ' . $k . ' ' . ($k + count($ws) - 1) . ' ' . $ws[0];
            } else {
                $w .= ' ' . $k . ' [ ' . implode(' ', $ws) . ' ]' . "\n";
            }
        }
        $this->Out('/W [' . $w . ' ]');
    }

    /**
     *
     */
    private function PutImages()
    {
        foreach (array_keys($this->arr_images) as $str_file) {
            $this->PutImage($this->arr_images[$str_file]);
            unset($this->arr_images[$str_file]['data']);
            unset($this->arr_images[$str_file]['smask']);
        }
    }

    /**
     * @param array $arr_info
     */
    public function PutImage(&$arr_info)
    {
        $this->NewObject();
        $arr_info['n'] = $this->int_current_object;
        $this->Out('<</Type /XObject');
        $this->Out('/Subtype /Image');
        $this->Out('/Width ' . $arr_info['w']);
        $this->Out('/Height ' . $arr_info['h']);
        if ($arr_info['cs'] == 'Indexed') {
            $this->Out('/ColorSpace [/Indexed /DeviceRGB ' . (strlen($arr_info['pal']) / 3 - 1) . ' ' . ($this->int_current_object + 1) . ' 0 R]');
        } else {
            $this->Out('/ColorSpace /' . $arr_info['cs']);
            if ($arr_info['cs'] == 'DeviceCMYK') {
                $this->Out('/Decode [1 0 1 0 1 0 1 0]');
            }
        }
        $this->Out('/BitsPerComponent ' . $arr_info['bpc']);
        if (isset($arr_info['f'])) {
            $this->Out('/Filter /' . $arr_info['f']);
        }
        if (isset($arr_info['dp'])) {
            $this->Out('/DecodeParms <<' . $arr_info['dp'] . '>>');
        }
        if (isset($arr_info['trns']) && is_array($arr_info['trns'])) {
            $trns = '';
            for ($i = 0; $i < count($arr_info['trns']); $i++) {
                $trns .= $arr_info['trns'][$i] . ' ' . $arr_info['trns'][$i] . ' ';
            }
            $this->Out('/Mask [' . $trns . ']');
        }
        if (isset($arr_info['smask'])) {
            $this->Out('/SMask ' . ($this->int_current_object + 1) . ' 0 R');
        }
        $this->Out('/Length ' . strlen($arr_info['data']) . '>>');
        $this->PutStream($arr_info['data']);
        $this->Out('endobj');
        // Soft mask
        if (isset($arr_info['smask'])) {
            $str_dp = '/Predictor 15 /Colors 1 /BitsPerComponent 8 /Columns ' . $arr_info['w'];
            $arr_smask = array(
                'w' => $arr_info['w'],
                'h' => $arr_info['h'],
                'cs' => 'DeviceGray',
                'bpc' => 8,
                'f' => $arr_info['f'],
                'dp' => $str_dp,
                'data' => $arr_info['smask']
            );
            $this->PutImage($arr_smask);
        }
        // Palette
        if ($arr_info['cs'] == 'Indexed') {
            $str_filter = ($this->bol_compress) ? '/Filter /FlateDecode ' : '';
            $str_pal = ($this->bol_compress) ? gzcompress($arr_info['pal']) : $arr_info['pal'];
            $this->NewObject();
            $this->Out('<<' . $str_filter . '/Length ' . strlen($str_pal) . '>>');
            $this->PutStream($str_pal);
            $this->Out('endobj');
        }
    }

    /**
     *
     */
    public function PutXObjectDict()
    {
        foreach ($this->arr_images as $arr_image) {
            $this->Out('/I' . $arr_image['i'] . ' ' . $arr_image['n'] . ' 0 R');
        }
    }

    /**
     *
     */
    public function PutResourceDict()
    {
        $this->Out('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
        $this->Out('/Font <<');
        foreach ($this->arr_fonts as $arr_font) {
            $this->Out('/F' . $arr_font['i'] . ' ' . $arr_font['n'] . ' 0 R');
        }
        $this->Out('>>');
        $this->Out('/XObject <<');
        $this->PutXObjectDict();
        $this->Out('>>');
    }

    /**
     *
     */
    public function PutResources()
    {
        $this->PutFonts();
        $this->PutImages();
        // Resource dictionary
        $this->arr_offsets[2] = strlen($this->str_buffer);
        $this->Out('2 0 obj');
        $this->Out('<<');
        $this->PutResourceDict();
        $this->Out('>>');
        $this->Out('endobj');
    }

    /**
     *
     */
    public function PutInfo()
    {
        $this->Out('/Producer ' . $this->TextString('tFPDF ' . $this->str_pdf_version));
        if (!empty($this->str_title)) {
            $this->Out('/Title ' . $this->TextString($this->str_title));
        }
        if (!empty($this->str_subject)) {
            $this->Out('/Subject ' . $this->TextString($this->str_subject));
        }
        if (!empty($this->str_author)) {
            $this->Out('/Author ' . $this->TextString($this->str_author));
        }
        if (!empty($this->str_keywords)) {
            $this->Out('/Keywords ' . $this->TextString($this->str_keywords));
        }
        if (!empty($this->str_creator)) {
            $this->Out('/Creator ' . $this->TextString($this->str_creator));
        }
        $this->Out('/CreationDate ' . $this->TextString('D:' . @date('YmdHis')));
    }

    /**
     *
     */
    public function PutCatalog()
    {
        $this->Out('/Type /Catalog');
        $this->Out('/Pages 1 0 R');
        if ($this->mix_zoom_mode == 'fullpage') {
            $this->Out('/OpenAction [3 0 R /Fit]');
        } elseif ($this->mix_zoom_mode == 'fullwidth') {
            $this->Out('/OpenAction [3 0 R /FitH null]');
        } elseif ($this->mix_zoom_mode == 'real') {
            $this->Out('/OpenAction [3 0 R /XYZ null null 1]');
        } elseif (!is_string($this->mix_zoom_mode)) {
            $this->Out('/OpenAction [3 0 R /XYZ null null ' . sprintf('%.2F', $this->mix_zoom_mode / 100) . ']');
        }
        if ($this->str_layout_mode == 'single') {
            $this->Out('/PageLayout /SinglePage');
        } elseif ($this->str_layout_mode == 'continuous') {
            $this->Out('/PageLayout /OneColumn');
        } elseif ($this->str_layout_mode == 'two') {
            $this->Out('/PageLayout /TwoColumnLeft');
        }
    }

    /**
     *
     */
    public function PutHeader()
    {
        $this->Out('%PDF-' . $this->str_pdf_version);
    }

    /**
     *
     */
    public function PutTrailer()
    {
        $this->Out('/Size ' . ($this->int_current_object + 1));
        $this->Out('/Root ' . $this->int_current_object . ' 0 R');
        $this->Out('/Info ' . ($this->int_current_object - 1) . ' 0 R');
    }

    /**
     *
     */
    public function EndDoc()
    {
        $this->PutHeader();
        $this->PutPages();
        $this->PutResources();
        // Info
        $this->NewObject();
        $this->Out('<<');
        $this->PutInfo();
        $this->Out('>>');
        $this->Out('endobj');
        // Catalog
        $this->NewObject();
        $this->Out('<<');
        $this->PutCatalog();
        $this->Out('>>');
        $this->Out('endobj');
        // Cross-ref
        $int_buffer_size = strlen($this->str_buffer);
        $this->Out('xref');
        $this->Out('0 ' . ($this->int_current_object + 1));
        $this->Out('0000000000 65535 f ');
        for ($i = 1; $i <= $this->int_current_object; $i++) {
            $this->Out(sprintf('%010d 00000 n ', $this->arr_offsets[$i]));
        }
        // Trailer
        $this->Out('trailer');
        $this->Out('<<');
        $this->PutTrailer();
        $this->Out('>>');
        $this->Out('startxref');
        $this->Out($int_buffer_size);
        $this->Out('%%EOF');
        $this->int_state = self::DOCUMENT_STATE_TERMINATED;
    }

    /**
     * Converts UTF-8 strings to UTF16-BE
     *
     * @param string $str_input
     * @param bool $bol_set_byte_order_mark
     * @return string
     */
    public function UTF8ToUTF16BE($str_input, $bol_set_byte_order_mark = true)
    {
        $str_output = "";
        if ($bol_set_byte_order_mark) {
            $str_output .= "\xFE\xFF";
        }
        $str_output .= mb_convert_encoding($str_input, 'UTF-16BE', 'UTF-8');
        return $str_output;
    }

    /**
     * Converts UTF-8 strings to codepoints array
     *
     * @param string $str_input
     * @return array
     */
    public function UTF8StringToArray($str_input)
    {
        $arr_output = array();
        $int_string_length = strlen($str_input);
        for ($i = 0; $i < $int_string_length; $i++) {
            $uni = -1;
            $int_char = ord($str_input[$i]);
            if ($int_char <= 0x7F) {
                $uni = $int_char;
            } elseif ($int_char >= 0xC2) {
                if (($int_char <= 0xDF) && ($i < $int_string_length - 1)) {
                    $uni = ($int_char & 0x1F) << 6 | (ord($str_input[++$i]) & 0x3F);
                } elseif (($int_char <= 0xEF) && ($i < $int_string_length - 2)) {
                    $uni = ($int_char & 0x0F) << 12 | (ord($str_input[++$i]) & 0x3F) << 6
                        | (ord($str_input[++$i]) & 0x3F);
                } elseif (($int_char <= 0xF4) && ($i < $int_string_length - 3)) {
                    $uni = ($int_char & 0x0F) << 18 | (ord($str_input[++$i]) & 0x3F) << 12
                        | (ord($str_input[++$i]) & 0x3F) << 6
                        | (ord($str_input[++$i]) & 0x3F);
                }
            }
            if ($uni >= 0) {
                $arr_output[] = $uni;
            }
        }
        return $arr_output;
    }

    /**
     * @param string $str_name
     * @param string $str_data
     * @return int
     */
    public function writeFontFile($str_name, $str_data)
    {
        return file_put_contents($str_name, $str_data);
    }

    /**
     * @param string $str_name
     * @return string
     */
    public function readFontFile($str_name)
    {
        return file_get_contents($str_name);
    }

    /**
     * @param string $str_name
     * @return bool
     */
    public function clearFontFile($str_name)
    {
        if (file_exists($str_name)) {
            unlink($str_name);
        }

        return true;
    }

    /**
     * @param string $str_path
     */
    public function configureFontWritePath($str_path)
    {
        if (!is_dir($this->str_font_write_path . $str_path)) {
            if (!mkdir($this->str_font_write_path . $str_path, 0777, true)) {
                $this->Error("Unable to create unifont directory in path {$this->str_font_write_path}");
            }
        }
    }

    /**
     * Get current page width
     *
     * @return float
     */
    public function GetPageWidth()
    {
        return $this->flt_current_width;
    }

    /**
     * Get current page height
     *
     * @return float
     */
    public function GetPageHeight()
    {
        return $this->flt_current_height;
    }

}
