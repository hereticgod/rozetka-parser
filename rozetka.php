<?php

class Rozetka {
    /**
     * Limit of pages for parsing.
     */
    public $pagesLimit = 1000;
    protected $searchingURL = 'http://rozetka.com.ua/search/?text={text}&p={page}';
    protected $request;
    protected $model;

    public function __construct(){
        $this->request = new Request();
        $this->model = new Model();
    }

    /**
     * This function is for getting list of Rozetka's items by
     * keyword.
     *
     * First we walk throw pages and get all items from pages
     * while page exist. Next we sort it by  price, output in CVS format
     * and put that data to database.
     *
     * @param string $keyword String for searching at Rozetka-s catalog.
     */
    public function parse($keyword){
        $URL = str_replace('{text}', urlencode($keyword), $this->searchingURL);
        $resultItems = array();
        $pageNum = 0;
        while ($pageNum < $this->pagesLimit){
            $currentURL = str_replace('{page}', $pageNum, $URL);
            $pageNum++;
            $htmlpage = $this->request->make_request($currentURL);
            if ( ! $htmlpage){
                // This is a sign that it was the last page.
                if ($this->request->lastHTTPCode == 404 ||
                        $this->request->lastHTTPCode == 301){
                    output('All pages parsed.');
                    break;
                }
                if ($this->request->lastHTTPCode != 200){
                    output('HTTP CODE: '.$this->request->lastHTTPCode);
                    return;
                }
                if ($this->request->lastErrorCode){
                    output('ERROR CODE: '.$this->request->lastHTTPCode);
                    return;
                }
            }
            $items = $this->parse_items_list($htmlpage);
            if (is_array($items)){
                output('Parsed '.count($items).' items from page '.$pageNum);
                /**
                 * Dump everything in a single array.
                 */
                $resultItems = array_merge($resultItems, $items);
            } else {
                break;
            }
        }

        // Now we have array $resultItems with all items from search.

        /**
         * Bubble sorting by price in grivna.
         */
        while (true){
            $replacementAccomplished = 0;
            if (count($resultItems) < 2){
                break;
            }
            foreach ($resultItems as $key=>$item){
                if (! isset($resultItems[$key+1])){
                    break;
                }
                if ($resultItems[$key]['price']['uan'] > $resultItems[$key+1]['price']['uan']){
                    // Shift elements.
                    $tmp = $resultItems[$key];
                    $resultItems[$key] = $resultItems[$key+1];
                    $resultItems[$key+1] = $tmp;
                    $replacementAccomplished = 1;
                }
            }
            if ($replacementAccomplished == 0){
                break;
            }
        }

        // Output sorted items in CVS format.
        $this->output_as_csv($resultItems);

        // Add items to database.
        foreach ($resultItems as $item){
            $this->model->add_item($keyword, json_encode($item));
        }
        return true;
    }

    /**
     * Write all finded items to stdout in CVS format.
     *
     * @param string $data Only parsed data allowed.
     */
    protected function output_as_csv($data){
        if ( ! $outstream = fopen('php://stdout', 'w')){
            die('Error opening stdout!');
        }
        foreach ($data as $line){
            if ( ! fputcsv($outstream,
                    array($line['name'], $line['model'],
                            $line['price']['uan'], $line['link']))){
                die('Error writing stdout!');
            }
        }
        fclose($outstream);
    }

    /**
     * Using regular expressions to find and analyze needed fields.
     *
     * @param string $html Variable containing html code of current search results page.
     */
    protected function parse_items_list($html){

        $onPageItems = array();
        $regexp = '/\<td\ class\=\"detail\"\>.*\<div\ class\=\"title\"\>(.*)\<\/div\>';
        $regexp .= '.*\<table\>.*\<div\ class\=\"price\"\>.*\<div\ class\=\"uah\"\>(\d*)\<span\>';
        $regexp .= '.*\<div\ class\=\"usd\"\>(.*)\<\/div\>';
        $regexp .= '/smU';
        /*
         * $matchesItems[1] is link and name
        * $matchesItems[2] is price in grivna
        * $matchesItems[3] is price in usd
        */
        if ( ! preg_match_all($regexp, $html, $matchesItems)){
            return false;
        }
        foreach ($matchesItems[1] as $itemIndex => $item){
            $regexp = '/\<a\ href\=\"(.*)\"\>(.*)\<\/a\>/smU';
            if ( ! preg_match_all($regexp, trim($item), $matches)){
                continue;
            }
            $currentItem['link'] = trim($matches[1][0]);
            $currentItem['name'] = trim($matches[2][0]);
            $urlParts = explode('/', $currentItem['link']);
            $model = ucfirst(str_replace('_', ' ', $urlParts[3]));
            // Not every url contains model name. In this case 'ru' string is on
            // 3rd position, but we just checking a length of string.
            $model = (strlen($model) === 2) ? $currentItem['name'] : $model;
            $currentItem['model'] = $model;
            $currentItem['price']['uan'] = (int)$matchesItems[2][$itemIndex];
            $currentItem['price']['usd'] = (int)str_replace('$&nbsp;',
                    '', $matchesItems[3][$itemIndex]);
            // Collect items on page to $onPageItems.
            array_push($onPageItems, $currentItem);
        }
        return $onPageItems;
    }
}