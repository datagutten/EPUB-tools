<?php


namespace datagutten\epub;


use DOMDocument;
use DOMElement;
use DOMXPath;

class Page
{
    /**
     * @var DOMDocument
     */
    public $dom;
    /**
     * @var DOMXPath
     */
    public $xpath;

    function __construct($file)
    {
        $this->dom = new DOMDocument();
        $input_string = file_get_contents($file);
        @$this->dom->loadHTML($input_string);
        $this->xpath = new DOMXPath($this->dom);
    }

    function saveFile($file)
    {
        $this->dom->formatOutput = true;
        $this->dom->preserveWhiteSpace = false;
        $output_string = $this->dom->saveXML($this->dom->documentElement);
        $prefix = '<?xml version="1.0" encoding="utf-8"?>'."\n<!DOCTYPE HTML>\n";
        file_put_contents($file, $prefix.trim($output_string));
    }

    function addTitle($title_string)
    {
        $title = $this->xpath->query('//head/title');
        if($title->length==0)
        {
            $head =  $this->dom->getElementsByTagName('head')[0];
            $title = $this->dom->createElement('title', $title_string);
            $head->appendChild($title);
        }
    }

    function removeTag($tag)
    {
        $divs = $this->xpath->query('//'.$tag);
        /** @var DOMElement $div */
        foreach($divs as $div)
        {
            $div->parentNode->removeChild($div);
        }
    }
}