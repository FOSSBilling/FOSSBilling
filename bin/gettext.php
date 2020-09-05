<?php

require_once dirname(__FILE__) . '/../src/bb-load.php';

echo "Caching templates".PHP_EOL;
$dirs = glob(BB_PATH_MODS.'/*/html_admin');
$dirs = array_merge($dirs, glob(BB_PATH_MODS . '/*/html_client'));
$dirs = array_merge($dirs, glob(BB_PATH_THEMES . '/*/html'));
genCache($dirs, "/tmp/bb-translations/");

function genCache($dirs, $tmpDir)
{
    $loader = new Twig_Loader_Filesystem($dirs);
    // force auto-reload to always have the latest version of the template
    $twig = new Twig_Environment($loader, array(
        'cache' => $tmpDir,
        'auto_reload' => true
    ));
    $twig->addExtension(new Twig_Extensions_Extension_I18n());
    $twig->addExtension(new Twig_Extensions_Extension_Debug());
    $twig->addExtension(new Box_TwigExtensions());

    foreach($dirs as $tplDir) {
        if(is_link(pathinfo($tplDir, PATHINFO_DIRNAME))) {
            print 'Skip symlink module'. $tplDir;
            continue;
        }
        
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tplDir), RecursiveIteratorIterator::LEAVES_ONLY|FilesystemIterator::SKIP_DOTS) as $file)
        {
            if($file->getBaseName() == '.svn' || $file->getBaseName() == '.' || $file->getBaseName() == '..') {
                continue;
            }

            $twig->loadTemplate(str_replace($tplDir.'/', '', $file));
            echo ".";
        }
    }
    echo "Done".PHP_EOL;
}

