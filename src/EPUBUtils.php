<?php


namespace datagutten\epub;


use datagutten\tools\files\files;
use DOMElement;
use Symfony\Component\Process\Process;

class EPUBUtils
{

    /**
     * @param DOMElement[] $elementsToRemove
     */
    public static function remove_elements(array $elementsToRemove)
    {
        //http://php.net/manual/en/domnode.removechild.php#90292
        foreach($elementsToRemove as $domElement)
        {
            if(!is_object($domElement->parentNode))
                print_r($domElement);
            $domElement->parentNode->removeChild($domElement);
        }
    }

    /**
     * @param $file
     */
    public static function epubCheck($file)
    {
        $jar = realpath(files::path_join(__DIR__, '..', 'bin', 'epubcheck', 'epubcheck.jar'));
        $process = new Process(['java', '-jar', $jar, $file]);
        $process->run();
        echo $process->getOutput();
        echo $process->getErrorOutput();
    }

    public static function buildEPUB($folder, $epub_file = null)
    {
        if(empty($epub_file))
            $epub_file = $folder.'.epub';

        file_put_contents(files::path_join($folder, 'mimetype'), 'application/epub+zip');
        shell_exec($cmd=sprintf('cd "%s" && zip -0 -X "%s" mimetype 2>&1',$folder, $epub_file));
        shell_exec($cmd=sprintf('cd "%s" && zip -rg "%s" * -x mimetype 2>&1',$folder, $epub_file));

        return $epub_file;
    }
}