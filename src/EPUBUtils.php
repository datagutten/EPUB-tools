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



    public static function buildEPUB($folder, $epub_file = null)
    {
        if (empty($epub_file))
            $epub_file = $folder . '.epub';

        file_put_contents(files::path_join($folder, 'mimetype'), 'application/epub+zip');
        $process = new Process(['zip', '-0', '-X', $epub_file, 'mimetype'], $folder);
        $process->mustRun();
        $process = new Process(['zip', '-rg', $epub_file, '.', '-x', 'mimetype'], $folder);
        $process->mustRun();
        return $epub_file;
    }
}