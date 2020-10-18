<?php


namespace datagutten\epub;


use datagutten\tools\files\files;
use DOMDocument;
use DOMElement;
use DOMXPath;

class OPF
{
    /**
     * @var DOMDocument
     */
    public $dom;
    private $output_folder;
    /**
     * @var DOMXPath
     */
    public $xpath;
    /**
     * @var array
     */
    private $strip_patterns = [];

    function __construct($file, $epub_root)
    {
        $this->dom = new DOMDocument();
        $input_string = file_get_contents($file);
        $this->dom->loadXML($input_string);
        $this->xpath = new DOMXPath($this->dom);
        $this->xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
        $this->output_folder = $epub_root;
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
     * @param string $field
     * @return DOMElement
     */
    function getDc($field)
    {
        return $this->xpath->query('//dc:'.$field)[0];
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