<?php


use datagutten\epub\EPUB;
use datagutten\epub\EPUBCheck;
use datagutten\epub\EPUBUtils;
use datagutten\tools\files\files;
use PHPUnit\Framework\TestCase;
use WpOrg\Requests\Requests;

class EPUBUtilsTest extends TestCase
{
    public array $test_books = [
        'https://github.com/IDPF/epub3-samples/releases/download/20230704/childrens-literature.epub',
        'https://github.com/IDPF/epub3-samples/releases/download/20230704/accessible_epub_3.epub',
    ];
    public array $test_files;
    public string $unpack_folder;

    public function setUp(): void
    {
        $filesystem = new Symfony\Component\Filesystem\Filesystem();
        $this->unpack_folder = files::path_join(sys_get_temp_dir(), 'epub_unpack');
        if (file_exists($this->unpack_folder))
            $filesystem->remove($this->unpack_folder);
        $filesystem->mkdir(files::path_join(__DIR__, 'test_data'));
        foreach ($this->test_books as $test_url)
        {
            $test_file = files::path_join(__DIR__, 'test_data', basename($test_url));
            if (!file_exists($test_file))
                Requests::get($test_url, options: ['filename' => $test_file]);
            $this->test_files[basename($test_url)] = $test_file;
        }
    }

    public function testBuildEPUB()
    {
        $folder = EPUBUtils::unpackEPUB($this->test_files['childrens-literature.epub'], $this->unpack_folder);
        $file = EPUBUtils::buildEPUB($folder);
        $check = EPUBCheck::check($file);
        $this->assertEmpty($check['messages']);
    }

    public function testGetISBN()
    {
        $folder = EPUBUtils::unpackEPUB($this->test_files['accessible_epub_3.epub'], $this->unpack_folder);
        $epub = new EPUB($folder);
        $isbn = $epub->getISBN();
        $this->assertEquals('9781449328030', $isbn);
    }

    public function testGetPrimaryKey()
    {
        $epub = new EPUB($this->test_files['accessible_epub_3.epub'], $this->unpack_folder);
        $key = $epub->opf->getPrimaryKey();
        $this->assertEquals('urn:isbn:9781449328030', $key->nodeValue);
    }

    public function testGetPageFiles()
    {
        $epub = new EPUB($this->test_files['accessible_epub_3.epub'], $this->unpack_folder);
        $pages = $epub->getPageFiles();
        $this->assertStringContainsString('cover.xhtml', $pages[0]);
    }

    public function testGetFiles()
    {
        $epub = new EPUB($this->test_files['accessible_epub_3.epub'], $this->unpack_folder);
        $files = $epub->getFiles();
        $this->assertStringContainsString('epub.css',  $files[1]);
    }

    public function testGetItem()
    {
        $epub = new EPUB($this->test_files['accessible_epub_3.epub'], $this->unpack_folder);
        $page = $epub->opf->getItem('id-id2442754');
        $this->assertEquals('index.xhtml', $page->getAttribute('href'));
    }
}
