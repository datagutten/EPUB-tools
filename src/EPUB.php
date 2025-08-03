<?php

namespace datagutten\epub;

use datagutten\tools\files\files;
use DOMDocument;
use FileNotFoundException;
use RuntimeException;

class EPUB
{
    private string $folder;
    public string $content_folder;
    public OPF $opf;

    function __construct(string $epub_file_or_folder, string $unpack_folder = null)
    {
        if (!is_dir($epub_file_or_folder))
        {
            if (empty($unpack_folder))
                throw new RuntimeException('Unpack folder must be provided when epub is a file');
            if (!file_exists($unpack_folder))
                EPUBUtils::unpackEPUB($epub_file_or_folder, $unpack_folder);
            $this->folder = $unpack_folder;
        }
        else
            $this->folder = $epub_file_or_folder;
        $this->opf = $this->get_opf();
    }

    /**
     * Convert a relative link to a file path
     * @param string $link
     * @return string File path
     * @throws FileNotFoundException File does not exist
     */
    public function link_to_file(string $link): string
    {
        $file = files::path_join($this->content_folder, preg_replace('/(.+\.\w+)(?:#.+)?/', '$1', $link));
        if (!file_exists($file))
            throw new FileNotFoundException($file);
        if (DIRECTORY_SEPARATOR != '/')
            $file = str_replace('/', DIRECTORY_SEPARATOR, $file);
        return $file;
    }

    /**
     * Find OPF file and return OPF object
     * @return OPF
     */
    public function get_opf(): OPF
    {
        $container = files::path_join($this->folder, 'META-INF', 'container.xml');
        $dom = new DomDocument();
        $dom->load($container);
        $rootfile = $dom->getElementsByTagName('rootfile')->item(0);
        $rootfile = files::path_join($this->folder, $rootfile->getAttribute('full-path'));
        $this->content_folder = dirname($rootfile);
        return new OPF($rootfile, $this->folder);
    }

    /**
     * Try to find ISBN in the epub
     * @return string ISBN
     */
    public function getISBN(): string
    {
        foreach ($this->opf->getIdentifiers() as $identifier)
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

    public function getCoverImage(): string
    {
        $cover_info = $this->opf->findCover();
        $file = $this->link_to_file($cover_info->getAttribute('href'));
        $dom = new DOMDocument();
        $dom->loadHTMLFile($file);
        $image = $dom->getElementsByTagName('img')->item(0);
        return $this->link_to_file($image->getAttribute('src'));
    }
}