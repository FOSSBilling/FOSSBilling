tFPDF accepts UTF-8 encoded text. It embeds font subsets allowing small PDF files.

It requires a folder 'unifont' as a subfolder of the 'font' folder.

You should make the 'unifont' folder writeable (CHMOD 755 or 644). Although this
is not essential, it allows caching of the font metrics the first time a font is used,
making subsequent uses much faster.

All tFPDF requires is a .ttf TrueType font file. The file should be placed in the
'unifont' directory. Optionally, you can also define the path to your system fonts e.g. 'C:\Windows\Font'
(see the example ex.php file) and reference TrueType fonts in this directory.

Pass a fourth parameter as true when calling AddFont(), and use utf-8 encoded text 
when using Write() etc.

