<?php


namespace App\Services;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Class AmazonParserPage
 * @package App\Services
 */
class AmazonParserPage
{
    private  $context;
    private $obj;
    protected $crawler;

    /**
     * AmazonParserPage constructor.
     * @param $context
     */
    public function __construct($context)
    {
        $this->context = $context;
        $this->crawler = new Crawler($context);
        $this->obj = new \stdClass();
    }

    /**
     *
     * @return \stdClass
     */
    public function run()
    {
        $this->obj->productTitle = $this->getProductTitle();
        $this->obj->imgLinks = $this->getImageLinks();
        $this->obj->ASIN = $this->getASIN();
        $this->obj->productDescription = $this->getProductDescription();
        $this->obj->productSpecifications = $this->getProductSpecifications();
        $this->obj->price = $this->getPrice();
        return $this->obj;
    }

    /**
     * Product title retrieving
     * @return string
     */
    private function getProductTitle()
    {
        return $this->getTextByFilter("#productTitle");
    }

    /**
     * Image URLs retrieving
     * @return array
     */
    private function getImageLinks()
    {

        //User regular expressions as images don't exist in DOM.
        preg_match("~'colorImages':\s?(\{.*\}),\s*'colorToAsin'~", $this->context, $matches);
        $data = json_decode(str_replace("'", "\"", $matches[1]));
        $imageLinks = [];

        foreach ($data->initial as $key => $item) {
            $imageLinks[$key]['thumb'] = $item->thumb;

            foreach ($item->main as $imgLink => $param) {
                $imageLinks[$key]['main'] = $imgLink;
                break;
            }

        }

        return $imageLinks;
    }

    /**
     * ASIN retrieving
     * @return string
     */
    private function getASIN()
    {
        $addInformation = $this->crawler
            ->filter('#prodDetails .col2 tbody > tr')
            ->each(function ($tr) {
                return $tr->filter('td')
                    ->each(function ($td) {
                        return $td->text();
                    });
            });
        foreach ($addInformation as $item) {
            if ($item[0] === "ASIN") {
                return $item[1];
            }
        }

    }

    /**
     * Product description retrieving
     * @return string
     */
    private function getProductDescription()
    {
        return $this->getTextByFilter("#productDescription > p");
    }

    /**
     * Product specification retrieving
     * @return array
     */
    private function getProductSpecifications()
    {
        /*
          Product specification is stored in a table. Get an object via selector, iterate each row and parse corresponding specs
        */
        $productSpecifications = $this->crawler
            ->filter('#prodDetails .col1 tbody > tr')
            ->each(function ($tr) {
                $specification = $tr->filter('td')
                    ->each(function ($td) {
                        return $td->text();
                    });

                $result = [];
                $result[$specification[0]] = $specification[1];
                return $result;
            });

        return $productSpecifications;
    }

    /**
     * Price retrieving
     * @return array
     */
    private function getPrice()
    {
        $price = [];
        $price["main"] = $this->getTextByFilter("#priceblock_ourprice");
        /*
         *Sometimes there is no exact price on the product page, so I take 'new from' and 'used from' price values as well
         * */
        $price["additional"] = $this->crawler
            ->filter("#olp-sl-new-used > span")
            ->each(function ($span) {
                $a = $span->filter("a");
                if (!$a->count()) {
                    return null;
                }
                if (strpos($a->text(), "new")) {
                    return ["new" => $span->filter(".a-color-price")->text()];
                }
                if (strpos($a->text(), "used")) {
                    return ["used" => $span->filter(".a-color-price")->text()];
                }

            });
        return $price;

    }

    /**
     * Clear text from special characters
     * @param $str
     * @return string
     */
    private function clearText($str)
    {
        return trim(str_replace("\n", " ", $str));
    }

    /**
     * Get value by selector
     * @param $selector
     * @return string
     */
    private function getTextByFilter($selector)
    {
        $el = $this->crawler->filter($selector);
        if ($el->count()) {
            return $this->clearText($el->text());
        }
        return "";
    }
}