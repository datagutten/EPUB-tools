<?php


namespace datagutten\epub;


use datagutten\tools\files\files;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;

class OPF
{
    public DOMDocument $dom;
    private string $output_folder;
    public DOMXPath $xpath;
    private array $strip_patterns = [];
    /**
     * @var string OPF file path
     */
    public string $file;
    public string $content_folder;

    function __construct($file, $epub_root)
    {
        $this->file = $file;
        $this->dom = new DOMDocument();
        $this->dom->load($file);
        $this->xpath = new DOMXPath($this->dom);
        $this->xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
        $this->xpath->registerNamespace('opf', 'http://www.idpf.org/2007/opf');
        $this->output_folder = $epub_root;
        $this->content_folder = dirname($file);
    }

    function strip_metadata($tags = [])
    {
        foreach($this->dom->getElementsByTagName('metadata')->item(0)->childNodes as $dc) //Process metadata
        {
            if(isset($dc->tagName) && array_search($dc->tagName, $tags)!==false)
            {
                $remove[]=$dc;
            }
        }
    }


    function strip_missing_files($strip_properties = [])
    {
        $valid_files = [];
        /** @var DOMElement[] $remove */
        $remove = [];

        /** @var DOMElement $item */
        foreach ($this->dom->getElementsByTagName('item') as $item) //Remove items for non-existing files
        {
            $file = $item->attributes->getNamedItem('href')->value;
            if (!file_exists(files::path_join($this->output_folder, $file)) && substr($file, -3, 3) != 'ncx') {
                $remove[] = $item;
                //echo "Missing $file, removing from opf\n";
            } else {
                $id = $item->attributes->getNamedItem('id')->value;
                $valid_files[$id] = $file; //Save ID for valid files
            }
            /*foreach ($strip_item_tags as $attribute)
            {
                $item->removeAttribute($attribute);
            }*/
            $properties = $item->getAttribute('properties');
            if(array_search($properties, $strip_properties) !== false)
                $item->removeAttribute('properties');
            elseif(!empty($properties))
                var_dump($properties);
        }

        foreach ($this->dom->getElementsByTagName('itemref') as $itemref) //Remove itemref for non-existing items
        {
            $id = $itemref->attributes->getNamedItem('idref')->value;
            if (!isset($valid_files[$id]))
                $remove[] = $itemref;
        }

        foreach ($this->dom->getElementsByTagName('reference') as $reference) {
            $href = $reference->attributes->getNamedItem('href')->value;
            if (array_search($href, $valid_files) === false)
                $remove[] = $reference;
        }

        if (!empty($remove))
            EPUBUtils::remove_elements($remove);
        else
            echo "No elements removed from OPF\n";
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
        return $this->xpath->query(sprintf('/opf:package/opf:metadata/dc:identifier[@id="%s"]', $key))->item(0);
    }

    /**
     * Get all dc:identifier elements
     * @return DOMNodeList
     */
    public function getIdentifiers(): DOMNodeList
    {
        return $this->xpath->query('//dc:identifier');
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