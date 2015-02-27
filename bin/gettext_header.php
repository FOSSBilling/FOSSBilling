<?php

function _writeHeader() {
    $output = "# en_US translation of BoxBilling Application\n";
    $output .= sprintf("# Copyright %s BoxBilling <info@boxbilling.com>\n", '2011 - '. date('Y'));
    $output .= "#\n";
    $output .= "#, fuzzy\n";
    $output .= "msgid \"\"\n";
    $output .= "msgstr \"\"\n";
    $output .= "\"Project-Id-Version: 4\\n\"\n";
    $output .= "\"POT-Creation-Date: " . date("Y-m-d H:iO") . "\\n\"\n";
    $output .= "\"PO-Revision-Date: YYYY-mm-DD HH:MM+ZZZZ\\n\"\n";
    $output .= "\"Last-Translator: BoxBilling TM <info@boxbilling.com>\\n\"\n";
    $output .= "\"Language-Team: BoxBilling TM <info@boxbilling.com>\\n\"\n";
    $output .= "\"MIME-Version: 1.0\\n\"\n";
    $output .= "\"Content-Type: text/plain; charset=utf-8\\n\"\n";
    $output .= "\"Content-Transfer-Encoding: 8bit\\n\"\n";
    $output .= "\"Plural-Forms: nplurals=INTEGER; plural=EXPRESSION;\\n\"\n\n";
    return $output;
}

function prepend($string, $filename) {
    $context = stream_context_create();
    $fp = fopen($filename, 'r', 1, $context);
    $tmpname = md5($string);
    file_put_contents($tmpname, $string);
    file_put_contents($tmpname, $fp, FILE_APPEND);
    fclose($fp);
    unlink($filename);
    rename($tmpname, $filename);
}

require_once dirname(__FILE__) . '/../src/bb-load.php';

prepend(_writeHeader(), BB_PATH_LANGS.'/messages.pot');
