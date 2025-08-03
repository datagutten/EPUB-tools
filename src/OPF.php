<?php


namespace datagutten\epub;


use datagutten\tools\files\files;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use RuntimeException;

class OPF
{
    public DOMDocument $dom;
    public string $output_folder;
    public DOMXPath $xpath;
    private array $strip_patterns = [];
    /**
     * @var string OPF file path
     */
    public string $file;
    public string $content_folder;
    protected array $valid_files = [];

    function __construct($file, $epub_root)
    {
        $this->file = $file;
        $this->dom = new DOMDocument();
        $this->dom->load($file);
        $this->xpath = new DOMXPath($this->dom);
        $this->xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
        $this->xpath->registerNamespace('opf', 'http://www.idpf.org/2007/opf');
        $this->output_folder = $epub_root;
        $this->content_folder = files::path_join($this->output_folder, basename(dirname($file)));
    }

    public function setPrimaryKey(string $key): void
    {
        $package = $this->xpath->query('/opf:package')->item(0);
        $package->setAttribute('unique-identifier', $key);
    }

    /**
     * Remove manifest item entries for missing files
     * @return void
     */
    protected function strip_items(): void
    {
        $manifest = $this->xpath->query('/opf:package/opf:manifest')->item(0);

        foreach ($this->xpath->query('opf:item', $manifest) as $item)
        {
            $file = $item->attributes->getNamedItem('href')->value;
            if (!file_exists(files::path_join($this->content_folder, $file)) && !str_ends_with($file, 'ncx'))
            {
                echo "Missing $file, removing from opf\n";
                $manifest->removeChild($item);
            }
            else
            {
                $id = $item->attributes->getNamedItem('id')->value;
                $this->valid_files[$id] = $file; //Save existing ids
            }
        }
    }

    /**
     * Remove itemref for non-existing items
     * @return void
     */
    protected function strip_spine(): void
    {
        $spine = $this->xpath->query('/opf:package/opf:spine')->item(0);
        foreach ($this->xpath->query('opf:itemref', $spine) as $itemref)
        {
            $id = $itemref->getAttribute('idref');
            if (!isset($this->valid_files[$id]))
                $spine->removeChild($itemref);
        }
    }

    /**
     * Remove guide references to missing spine itemrefs
     * @return void
     */
    protected function strip_guide(): void
    {
        $guide = $this->xpath->query('/opf:package/opf:guide')->item(0);
        foreach ($this->xpath->query('opf:reference', $guide) as $reference)
        {
            $href = $reference->getAttribute('href');
            if (!in_array($href, $this->valid_files))
                $guide->removeChild($reference);
        }
    }

    /**
     * Remove references to missing files
     * @return void
     */
    public function strip_missing(): void
    {
        $this->strip_items();
        $this->strip_spine();
        $this->strip_guide();
    }

    /**
     * Get DC metadata property value
     * @param string $field
     * @return string|null
     */
    function getDc(string $field): ?string
    {
        $elements = $this->xpath->query(sprintf('/opf:package/opf:metadata/dc:%s', $field));
        if ($elements->length == 1)
            return $elements->item(0)->nodeValue;
        else
            return null;
    }

    /**
     * @param $field
     * @param $value
     * @return DOMElement
     */
    function createDc($field, $value)
    {
        return $this->dom->createElementNS('http://purl.org/dc/elements/1.1/', sprintf('dc:%s', $field), $value);
    }

    /**
     * @param string $field
     */
    function removeDc($field)
    {
        $dc = $this->getDc($field);
        $dc->parentNode->removeChild($dc);
    }

    function pregStrip($pattern)
    {
        $this->strip_patterns[] = $pattern;
    }

    /**
     * Get dc:identifier element for primary key defined in header
     * @return DOMElement
     */
    public function getPrimaryKey(): DOMElement
    {
        $package = $this->xpath->query('/opf:package')->item(0);
        $key = $package->getAttribute('unique-identifier');
        return $this->getIdentifiers($key)->item(0);
    }

    /**
     * Get all dc:identifier elements
     * @param string|null $id Identifier id
     * @return DOMNodeList
     */
    public function getIdentifiers(string $id = null): DOMNodeList
    {
        if (!empty($id))
            return $this->xpath->query(sprintf('/opf:package/opf:metadata/dc:identifier[@id="%s"]', $id));
        else
            return $this->xpath->query('/opf:package/opf:metadata/dc:identifier');
    }

    /**
     * Try to find ISBN in OPF
     * @return string ISBN
     * @throws RuntimeException ISBN not found
     */
    public function findISBN(): string
    {
        foreach ($this->getIdentifiers() as $identifier)
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

    public function findCover(): ?DOMElement
    {
        foreach ($this->xpath->query('/opf:package/opf:manifest/opf:item') as $item)
        {
            if ($item->getAttribute('id') == 'cover' || str_contains($item->getAttribute('href'), 'cover'))
                return $item;
        }
        return null;
    }


    function saveFile($file)
    {
        $this->dom->formatOutput = true;
        $this->dom->preserveWhiteSpace = false;
        $output_string = $this->dom->saveXML();
        foreach ($this->strip_patterns as $pattern)
        {
            $output_string = preg_replace($pattern, '', $output_string);
        }

        file_put_contents($file, trim($output_string));
    }
}