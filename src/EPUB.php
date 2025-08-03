<?php

namespace datagutten\epub;

use datagutten\tools\files\files;
use DOMDocument;
use RuntimeException;

class EPUB
{
    private string $folder;

    function __construct(string $epub_file_or_folder, string $unpack_folder = null)
    {
        if (!is_dir($epub_file_or_folder))
        {
            if (empty($unpack_folder))
                throw new RuntimeException('Unpack folder must be provided when epub is a file');
            EPUBUtils::unpackEPUB($epub_file_or_folder, $unpack_folder);
            $this->folder = $unpack_folder;
        }
        else
            $this->folder = $epub_file_or_folder;
    }

    /**
     * Find OPF file and return OPF object
     * @return OPF
     */
    public function opf(): OPF
    {
        $container = files::path_join($this->folder, 'META-INF', 'container.xml');
        $dom = new DomDocument();
        $dom->load($container);
        $rootfile = $dom->getElementsByTagName('rootfile')->item(0);
        $rootfile = files::path_join($this->folder, $rootfile->getAttribute('full-path'));
        return new OPF($rootfile, $this->folder);
    }

    /**
     * Try to find ISBN in the epub
     * @return string ISBN
     */
    public function getISBN(): string
    {
        $opf = $this->opf();
        foreach ($opf->getIdentifiers() as $identifier)
        {
            $id = $identifier->getAttribute('id');
            if (stripos($id, 'isbn') !== false || stripos($identifier->nodeValue, 'isbn') !== false)
            {
                $identifier->setAttribute('id', 'ISBN');
                return preg_replace('/\D*(\d+)/', '$1', $identifier->nodeValue);
            }
        }
        throw new RuntimeException('ISBN not found in OPF');
    }
}