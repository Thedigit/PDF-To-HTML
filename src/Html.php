<?php

namespace Thedigit\PdfToHtml;

use DOMDocument;
use DOMNode;
use DOMXPath;
use PHPHtmlParser\Dom;
use Pelago\Emogrifier as Emogrifier;
use File;

class Html extends Dom
{
  protected $contents, $total_pages, $current_page, $pdf_file, $locked = false;

  public function __construct($pdf_file)
  {
    $this->getContents($pdf_file);
    return $this;
  }

  private function getContents($pdf_file)
  {
    $this->locked = true;
    $info = new Pdf($pdf_file);
    $pdf = new Base($pdf_file, [
      'singlePage' => true,
      'noFrames'   => false,
    ]);
    $pages = $info->getPages();
    $random_dir = uniqid();
    $this->create_dir();
    $outputDir = base_path().'/public/doc/output/'.$random_dir;
    if (!file_exists($outputDir)) {
      mkdir($outputDir, 0777, true);
    }
    $pdf->setOutputDirectory($outputDir);
    $pdf->generate();
    $fileinfo = pathinfo($pdf_file);
    $base_path = $pdf->outputDir.'/'.$fileinfo['filename'];
    $contents = [];
    for ($i = 1; $i <= $pages; $i++) {
      $content = file_get_contents($base_path.'-'.$i.'.html');
      $content = $this->base64internalimages($content, $outputDir);
      $content = str_replace("Â", "", $content);
      if ($this->inlineCss()) {
        $emo = new Emogrifier();
        $dom = new DOMDocument();
        $dom->loadHTML($content);
        $xpath = new DOMXPath($dom);
        $styles = $dom->saveHTML($xpath->query('//style')->item(0));
        $newstyle = $this->cleanupCSS($styles);
        foreach ($xpath->query('//comment()') as $comment) {
          $comment->parentNode->removeChild($comment);
        }
        $body = $xpath->query('//body')->item(0);
        $content = $body instanceof DOMNode ? $dom->saveHTML($body) : 'something failed';
        $emo->setHtml($content);
        $emo->setCss($newstyle);
        //TODO:Config to only return body.
        if(true) {
          $content = $emo->emogrifyBodyContent();
        }else {
          $content = $emo->emogrify();
        }
      }
      //TODO: save atcutal html
      file_put_contents($base_path.'-'.$i.'.html', $content);
      $contents[ $i ] = file_get_contents($base_path.'-'.$i.'.html');
    }
    $this->contents = $contents;
    $this->goToPage(1);
    $this->remove_temp($outputDir);
  }

  public function goToPage($page = 1)
  {
    if ($page > count($this->contents))
    throw new \Exception("You're asking to go to page {$page} but max page of this document is ".count($this->contents));
    $this->current_page = $page;

    return $this->load($this->contents[ $page ]);
  }

  public function raw($page = 1)
  {
    return $this->contents[ $page ];
  }

  public function getTotalPages()
  {
    return count($this->contents);
  }

  public function base64internalimages($content, $outputDir)
  {
    $imgsrc = array();
    preg_match( '/src="([^"]*)"/i', $content, $imgsrc ) ;
    foreach ($imgsrc as $key => $value) {
      $pos = strpos($value, "src");
      if ($pos === false) {
        $b64 = base64Image($outputDir."/".$value);
        $content = str_replace($value, $b64, $content);
      }
    }
    return $content;
  }
  public function remove_temp($dir)
  {
    File::deleteDirectory($dir);
  }

  public function create_dir()
  {
    if(!File::isDirectory("doc/")) {
      File::makeDirectory("doc/", 0777, true, true);
    }
    if(!File::isDirectory("doc/output")) {
      File::makeDirectory("doc/output", 0777, true, true);
    }
  }

  public function cleanupCSS($styles)
  {
    $newstyle = trim(preg_replace('/\s\s+/', ' ', $styles));
    $newstyle = str_replace('<style type="text/css">', "", $newstyle);
    $newstyle = str_replace('</style>', "", $newstyle);
    $newstyle = str_replace('<!--', "", $newstyle);
    $newstyle = str_replace('-->', "", $newstyle);
    $newstyle = trim(preg_replace('/\s\s+/', ' ', $newstyle));
    $newstyle = str_replace("\r\n","",$newstyle);
    return $newstyle;
  }

  public function getCurrentPage()
  {
    return $this->current_page;
  }

  public function inlineCss()
  {
    return Config::get('pdftohtml.inlineCss', true);
  }
}
