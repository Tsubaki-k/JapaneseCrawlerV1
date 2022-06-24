<?php

namespace Hex\JapaneseCrawler\App\Crawlers;

use Hex\JapaneseCrawler\App\Interfaces\ICrawler;
use Symfony\Component\DomCrawler\Crawler;

class KanjiCrawler implements ICrawler
{
    #region Attributes

    /**
     * @var array <string> $_kanji_list
     */
    private array $_kanji_list;

    /**
     * Base url
     * @var string $_base_url
     */
    private string $_base_url;

    /**
     * Base url wiki image
     * @var string $_base_url
     */
    private string $_base_url_wiki;

    /**
     * Base url for Wiki Image
     * @var string $_base_url
     */
    private string $_base_url_wiki_image;

    /**
     * Current Target
     * @var array
     */
    private array $_target;

    /**
     * Crawled results are store here.
     * @var array $_result
     */
    protected array $_result;

    /**
     * Last fetched result.
     * @var int
     */
    private int $_last_fetch;

    /**
     * Current Crawler
     * @var Crawler $crawler
     */
    protected Crawler $crawler;

    /**
     * Current wiki Crawler
     * @var Crawler $crawler
     */
    protected Crawler $crawlerWiki;

    /**
     * Current wiki Image Crawler
     * @var Crawler $crawler
     */
    protected Crawler $crawlerWikiImage;

    /**
     * Temp Data
     * @var array
     */
    protected array $_temp;

    /**
     * Total Time
     * @var int
     */
    protected int $_time = 0 ;

    protected bool $is_log_on;

    private $foreground_colors = array();

    private $background_colors = array();

    public function __construct() {
        // Set up shell colors
        $this->foreground_colors['black'] = '0;30';
        $this->foreground_colors['dark_gray'] = '1;30';
        $this->foreground_colors['blue'] = '0;34';
        $this->foreground_colors['light_blue'] = '1;34';
        $this->foreground_colors['green'] = '0;32';
        $this->foreground_colors['light_green'] = '1;32';
        $this->foreground_colors['cyan'] = '0;36';
        $this->foreground_colors['light_cyan'] = '1;36';
        $this->foreground_colors['red'] = '0;31';
        $this->foreground_colors['light_red'] = '1;31';
        $this->foreground_colors['purple'] = '0;35';
        $this->foreground_colors['light_purple'] = '1;35';
        $this->foreground_colors['brown'] = '0;33';
        $this->foreground_colors['yellow'] = '1;33';
        $this->foreground_colors['light_gray'] = '0;37';
        $this->foreground_colors['white'] = '1;37';

        $this->background_colors['black'] = '40';
        $this->background_colors['red'] = '41';
        $this->background_colors['green'] = '42';
        $this->background_colors['yellow'] = '43';
        $this->background_colors['blue'] = '44';
        $this->background_colors['magenta'] = '45';
        $this->background_colors['cyan'] = '46';
        $this->background_colors['light_gray'] = '47';
    }

    #endregion

    #region Core Concept

    /**
     * Main crawler for jisho pages
     * @return $this
     */
    protected function _initCrawler():self
    {
        //Get the whole page
        $page = file_get_contents($this->_getTargetUrl());
        //Initialize the crawler
        $this->crawler = new Crawler($page);
        return $this;
    }

    /**
     * Crawler for wiki page
     * @return $this
     */
    protected function _initCrawlerWiki():self
    {
        // crawler wiki depth 2
        $url = $this->_temp['external_link']['Wiktionary']['link'];
        ini_set('user_agent', 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36');
        $page = file_get_contents($url);
        $this->crawlerWiki      = new Crawler($page);
        return $this;
    }

    /**
     * Crawler for getting image
     * @return $this
     */
    protected function _initCrawlerWikiImage():self
    {
        // crawler wiki depth 3
//        $url = $this->_temp['wiki_image_page'][0];
//        $url = $this->_temp['wiki_image_path']['path_url'];


        $url = $this->_getBaseUrlWikiImage();
        $page = file_get_contents($url);
        $this->crawlerWikiImage = new Crawler($page);
        return $this;
    }

    /**
     * Start crawling.
     * @return void
     */
    public function start(): void
    {
        echo PHP_EOL .  PHP_EOL . PHP_EOL;

        $this->_setIsLogOn(true );

        if( $this->_getIsLogOn() ){
            $this->_str_pad( '═════════════════════════════════════════════════════════════' , ['╔', '╗'] , '═' , 100  );
            $this->_str_pad( '  Kanji Crawler    ' , ['║', '║'] , ' ' , 61  );
            $this->_str_pad( '═════════════════════════════════════════════════════════════' , ['╠', '╣'] , '═' , 100  );
            $this->_str_pad( ' Starting process...' , ['║', '║'] , ' ' , 61  );

        }else{
            $this->_str_pad( '══════════╦═══════════╦══════════════════════════════════════' , ['╔', '╗'] , '═' , 100  );
            $this->_str_pad( '  status  ║  Time(s)  ║               Kanji    ' , ['║', '║'] , ' ' , 65  );
            $this->_str_pad( '══════════╩═══════════╩══════════════════════════════════════' , ['╠', '╣'] , '═' , 100  );
            $this->_str_pad( ' Starting process...' , ['║', '║'] , ' ' , 61  );
        }
        $this
            ->_setBaseUrl('https://jisho.org/search/')
            ->_setBaseUrlWiki('https://en.wiktionary.org')
            ->_initKanjiList()
            ->_process()
            ->_saveToFile()
            ->_saveToDatabase();
        $this->_str_pad( '═════════════════════════════════════════════════════════════' , ['╠', '╣'] , '═' , 100  );
        $this->_str_pad( ' Crawl Finished' , ['║', '║'] , ' ' , 61  );
        $this->_str_pad( ' Total Time:'.$this->_time.'s' , ['║', '║'] , ' ' , 61  );
        $this->_str_pad( '═════════════════════════════════════════════════════════════' , ['╚', '╝'] , '═' , 100  );
        echo PHP_EOL . PHP_EOL;
    }

    /**
     * Crawl concept and logic.
     * @return void
     */
    public function crawl(): void
    {
        $kanji = $this->_getTargetKanji();
        $starting = $this->getColoredString( 'starting', 'light_purple' );
        $finished = $this->getColoredString( 'finished', 'light_red' );
        $this->_str_pad( '══════════╦══════════════════════════════════════════════════' , ['╠', '╣'] , '..' );
        $this->_str_pad( ' '.$starting.' ║ fetching of kanji '.$kanji .' starting... ', ['║', '║'] , ' ' , 75 );
        $this->_str_pad( '══════════╩══════════════════════════════════════════════════' , ['╠', '╣'] , '..' );
        $this->_data_echoer('Going to Jisho.org page...');

        $this
            ->_initCrawler()
            ->_fetchTargetKanji()
            ->_fetchKanjiStroke()
            ->_fetchKunyomi()
            ->_fetchOnyomi()
            ->_fetchMainMeanings()
            ->_fetchKanjiGrade()
            ->_fetchKanjiJlpt()
            ->_fetchKanjiFrequency()
            ->_fetchInternalLink()
            ->_fetchExternalLink()
            ->_fetchOnyomiVocabs()
            ->_fetchKunYomiVocabs()
            ->_initCrawlerWiki()
            ->_fetchWikiGif()
            ->_fetchGetImage()
            ->_push();

        $diff = $this->_getDiff();

        if( $this->_getIsLogOn() ){
            $this->_str_pad( '══════════╦══════════════════════════════════════════════════' , ['╠', '╣'] , '..' );
            $this->_str_pad( ' '.$finished.' ║ fetching of kanji '.$kanji .' Finished, ['. $diff .'sec]' , ['║', '║'] , ' ' , 75 );
            $this->_str_pad( '══════════╩══════════════════════════════════════════════════' , ['╠', '╣'] , '..' );

        }else{
            $this->_str_pad( '     √    ║     ' .  $diff  .'    ║      '.$kanji .'     ' , ['║', '║'] , ' ' , 68 );
        }

        $this->_updateFetchTime();

    }

    #endregion

    #region Kanji List
    /**
     * Initialize Kanji List
     * @return $this
     */
    private function _initKanjiList(): self
    {
        // '中', '一', '七', '万', '三', '上', '下'
//        $this->_kanji_list = ['清', '中', '一', '七', '万', '三', '上', '下'];
//        $this->_kanji_list = [ '清', '中', '万', '下', '中' , '七', '万',];
//        $this->_kanji_list = ['清', '中', '万'];
        $this->_kanji_list = ['清'];

        return $this;
    }

    /**
     * Get Kanji List
     * @return array
     */
    private function _getKanjiList(): array
    {
        return $this->_kanji_list;
    }
    #endregion

    #region Base Url

    /**
     * Set base url
     * @param string $base_url
     * @return KanjiCrawler
     */
    private function _setBaseUrl(string $base_url): KanjiCrawler
    {
        $this->_base_url = $base_url;
        return $this;
    }

    /**
     * Get base url
     * @return string
     */
    private function _getBaseUrl(): string
    {
        return $this->_base_url;
    }

    /**
     * Set Base Url for Wiki
     * @param string $base_url
     * @return KanjiCrawler
     */
    private function _setBaseUrlWiki(string $base_url): KanjiCrawler
    {
        $this->_base_url_wiki = $base_url;
        return $this;
    }

    /**
     * Get Base Url for Wiki
     * @return string
     */
    private function _getBaseUrlWiki(): string
    {
        return $this->_base_url_wiki;
    }

    /**
     * Set Base Url for Image in Wiki
     * @param string $base_url_wiki_image
     * @return KanjiCrawler
     */
    private function _setBaseUrlWikiImage(string $base_url_wiki_image ) :KanjiCrawler
    {
        $this->_base_url_wiki_image = $base_url_wiki_image;
        return $this;
    }

    /**
     * Get Base Url for Image in Wiki
     * @return string
     */
    private function _getBaseUrlWikiImage() :string
    {
        return $this->_base_url_wiki_image;
    }


    #endregion

    #region Actions
    /**
     * Get the url for specified kanji.
     * @param string $kanji
     * @param bool $url_encode
     * @return string
     */
    private function _getUrl(string $kanji, bool $url_encode = false): string
    {
        $url = "$kanji #kanji";
        $url = $url_encode
            ? urlencode($url)
            : $url;

        return $this->_getBaseUrl() . $url;
    }

    /**
     * Process for crawling.
     * @return self
     */
    private function _process(): self
    {

        $this->_updateFetchTime();

        foreach ( $this->_getKanjiList() as $kanji) {

            $url = $this->_getUrl($kanji, true);

            $this
                ->_setTargetUrl($url)
                ->_setTargetKanji($kanji)
                ->crawl();
        }

        return $this;
    }

    /**
     * Update to current time.
     * @return self
     */
    private function _updateFetchTime(): self
    {
        $this->_last_fetch = time();
        return $this;
    }

    /**
     * Get time diff
     * @return int
     */
    private function _getDiff(): int
    {
        $last_fetch = time() - $this->_last_fetch;
        $this->_time += $last_fetch;
        return $last_fetch;
    }

    protected function _setIsLogOn( bool $is ): self
    {
        $this->is_log_on = $is;
        return $this;
    }

    protected function _getIsLogOn( ): bool
    {
        return $this->is_log_on;
    }

    #endregion

    #region Target
    /**
     * Set target url
     * @param string $url
     * @return self
     */
    private function _setTargetUrl(string $url): self
    {
        $this->_target['url'] = $url;
        return $this;
    }

    /**
     * Get target url
     * @return string
     */
    private function _getTargetUrl(): string
    {
        return $this->_target['url'];
    }

    /**
     * Set target uri
     * @param string $kanji
     * @return self
     */
    private function _setTargetKanji(string $kanji): self
    {
        $this->_target['kanji'] = $kanji;
        return $this;
    }

    /**
     * Get target uri
     * @return string
     */
    private function _getTargetKanji(): string
    {
        return $this->_target['kanji'];
    }

    #endregion

    #region CLI Beatifulizer

    protected function _data_echoer( string $string , string $textColor = 'white' , string $bgColor = 'black' , $pad_wall = null , $pad_padding = null , $pad_length = 80){
        if( $this->_getIsLogOn() ){
            $colorize =  $this->getColoredString($string, $textColor, $bgColor);
            $data =  ' '.$this->_getTargetKanji().'│ ' . $colorize  ;
            $this->_str_pad( $data , $pad_wall , $pad_padding , $pad_length );
        }
    }

    protected function _str_pad( $data , $wall = null , $padding = null , int $length = 80 ){

        if( is_null($wall) ){
            $wall_before = '║';
            $wall_after  = '║';
        }else{
            if( is_array($wall) ){
                $wall_before = $wall[0];
                $wall_after  = $wall[1];
            }else{
                $wall_before = $wall;
                $wall_after  = $wall;
            }
        }

        $pad = is_null($padding) ? ' ' : $padding;
        $str = str_pad(   $data , $length , $pad , STR_PAD_RIGHT) ;
        echo '     ' . $wall_before . $str . $wall_after. PHP_EOL;
    }
    protected function _gettingData( string $string ){
        $passing_data = 'getting ' . $string . ' data....';
        $this->_data_echoer($passing_data , 'light_gray', 'black' );
    }

    protected function _dataGetted( string $string ){
        $passing_data = 'data ' . $string . ' fetched correctly.';
        $this->_data_echoer($passing_data, 'green', 'black' );
    }

    // Returns colored string
    protected function getColoredString($string, $foreground_color = null, $background_color = null) {
        $colored_string = "";

        // Check if given foreground color found
        if (isset($this->foreground_colors[$foreground_color])) {
            $colored_string .= "\033[" . $this->foreground_colors[$foreground_color] . "m";
        }
        // Check if given background color found
        if (isset($this->background_colors[$background_color])) {
            $colored_string .= "\033[" . $this->background_colors[$background_color] . "m";
        }

        // Add string and end coloring
        $colored_string .=  $string . "\033[0m";

        return $colored_string;
    }

    // Returns all foreground color names
    protected function getForegroundColors() {
        return array_keys($this->foreground_colors);
    }

    // Returns all background color names
    protected function getBackgroundColors() {
        return array_keys($this->background_colors);
    }
    #endregion

    #region Save
    /**
     * Save results to file
     * @return $this
     */
    private function _saveToFile(): self
    {
        $file = getcwd() . '/App/' . 'Storage' . '/' . 'KanjiCrawler' . '/' . time() . '_result.json';
        file_put_contents($file, urldecode(json_encode($this->_result)));
        return $this;
    }

    /**
     * Save the results into the database.
     * @return $this
     */
    private function _saveToDatabase(): self
    {
        //TODO: Do insert.
        return $this;
    }

    /**
     * File Saver Based on Kanji
     * @param $urll
     * @return false|string
     */
    protected function _fileSave($urll = null ):string
    {

        if( !is_null($urll) ){
            $url = $urll;
        }else{
            $url = 'https://media.sproutsocial.com/uploads/2017/02/10x-featured-social-media-image-size.png';
        }
        $kanji = $this->_temp['kanji'];
        $file_name = basename( $url );
        $extension = pathinfo( $file_name , PATHINFO_EXTENSION );
        $file_dir  = getcwd() . '\\App\\' . 'Storage' . '\\kanjis\\' . $kanji . '\\';
        $file_path = $file_dir . $kanji . '.' . $extension;

        if( !file_exists( $file_dir ) ){
            mkdir( $file_dir , 6, true);
        }

        // get the file from url and save the file by using base name
        $save = file_put_contents( $file_path , file_get_contents($url) );

        return $save ? $file_path : '';
    }
    #endregion

    #region Result Handler

    /**
     * Push data
     * @return void
     */
    private function _push(): void
    {
        $this->_result[$this->_getTargetKanji()] = [
            'kanji'   => $this->_getTargetKanji(),
            'url'     => $this->_getTargetUrl(),
            'data'    => $this->_getTempData(),
            'fetched' => time()
        ];
    }

    /**
     * Get temp data
     * @return array
     */
    private function _getTempData(): array
    {
        return $this->_temp;
    }
    #endregion

    #region Crawl Logic
    /**
     * Get target kanji.
     * @return $this
     */
    private function _fetchTargetKanji(): self
    {
        // Test some basic printing with Colors class
        $name = 'Kanji';;
        $this->_gettingData($name);

        $this->_temp['kanji'] = $this->_getTargetKanji();
        $this->_dataGetted($name);

        return $this;
    }

    /**
     * Get Main Meanings.
     * @return self
     */
    private function _fetchMainMeanings(): self
    {
        $name = 'Main Meaning';
        $this->_gettingData($name);

        $domElement = $this->crawler->filter('div[class="kanji-details__main-meanings"]');
        $mainMeaning = explode(', ', trim($domElement->first()->getNode(0)->textContent));

        $this->_temp['main_meaning'] = $mainMeaning;
        $this->_dataGetted($name);

        return $this;
    }

    /**
     * Fetch Kanji Grade
     * @return $this
     */
    private function _fetchKanjiGrade(): self
    {
        $name = 'Grade';
        $this->_gettingData($name);

        $domElement = $this->crawler->filter('div[class="kanji_stats"] > div[class="grade"]');
        $details = explode('taught in', $domElement->getNode(0)->textContent);

        $this->_temp['grade'] = [
            'type'  => trim(str_replace(', taught in', '', $details[0])),
            'level' => trim($details[1])
        ];

        $this->_dataGetted($name);
        return $this;
    }

    /**
     * Fetch Kanji JLPT
     * @return $this
     */
    private function _fetchKanjiJlpt(): self
    {
        $name = 'JLPT Level';
        $this->_gettingData($name);

        $domElement = $this->crawler->filter('div[class="kanji_stats"] > div[class="jlpt"]');
        $details = explode('level', $domElement->getNode(0)->textContent);

        $this->_temp['jlpt'] = [
            'position'  => trim($details[0]),
            'frequency' => trim($details[1])
        ] ;

        $this->_dataGetted($name);
        return $this;
    }

    /**
     * Fetch Kanji frequency
     * @return $this
     */
    private function _fetchKanjiFrequency(): self
    {
        $name = 'Kanji Frequency';
        $this->_gettingData($name);

        $domElement = $this->crawler->filter('div[class="kanji_stats"] > div[class="frequency"]');
        $details = explode('of', $domElement->getNode(0)->textContent);

        $this->_temp['frequency'] = [
            'position'  => trim($details[0]),
            'frequency' => 'of ' . trim($details[1])
        ] ;

        $this->_dataGetted($name);
        return $this;
    }

    /**
     * Fetch Kanji Stroke Count
     * todo: make with regular expression
     * @return $this
     */
    private function _fetchKanjiStroke(): self
    {
        $name = 'Stroke';
        $this->_gettingData($name);

        $domElement = $this->crawler->filter('div[class="kanji-details__stroke_count"]');
        $details = explode('strokes', $domElement->getNode(0)->textContent);

        $this->_temp['stroke'] = trim($details[0]);
        $this->_dataGetted($name);
        return $this;
    }

    /**
     * Fetch Kanji Kunyomi
     * @return $this
     */
    private function _fetchKunyomi(): self
    {
        $name = 'Kun-Yomi';
        $this->_gettingData($name);
        $domElements = $this->crawler->filter('div[class="kanji-details__main-readings"] > dl[class="dictionary_entry kun_yomi"] > dd > a');

        $_kun_list = [];

        foreach ($domElements as $domElement)
        {
            $_kun_list[] = $domElement->textContent;
        }

        $this->_temp['kun_yomi'] = $_kun_list;
        $this->_dataGetted($name);
        return $this;
    }

    /**
     * Fetch Kanji Kunyomi
     * @return $this
     */
    private function _fetchOnyomi(): self
    {
        $name = 'On-Yomi';
        $this->_gettingData($name);
        $domElements = $this->crawler->filter('div[class="kanji-details__main-readings"] > dl[class="dictionary_entry on_yomi"] > dd > a');

        $_on_list = [];

        foreach ($domElements as $domElement){
            $_on_list[] = $domElement->textContent ;
        }

        $this->_temp['on_yomi'] = $_on_list;
        $this->_dataGetted($name);
        return $this;
    }

    /**
     * Fetch Kanji Inline links
     * @return $this
     */
    private function _fetchInternalLink(): self
    {
        $name = 'Internal Link';
        $this->_gettingData($name);
        $domElements = $this->crawler->filter('ul[class="inline-list"] > li > a');

        $_link_list = [];

        foreach ($domElements as $domElement){
            $_link_list[] = [
                'link' => $domElement->getAttribute("href"),
                'name' => $domElement->textContent
            ];
        }

        $this->_temp['internal_link'] = $_link_list;
        $this->_dataGetted($name);
        return $this;
    }

    /**
     * Fetch Kanji External Link
     * @return $this
     */
    private function _fetchExternalLink(): self
    {
        $name = 'External Link';
        $this->_gettingData($name);
        $domElements = $this->crawler->filter('a[class="external"]');

        $_link_list = [];
        foreach ($domElements as $domElement){
            $textContent = $domElement->textContent;
            $_link_list[$textContent] = [
                'name' => $textContent,
                'link' => $domElement->getAttribute("href"),
            ];
        }

        $this->_temp['external_link'] = $_link_list;
        $this->_dataGetted($name);
        return $this;
    }

    /**
     * Fetch Kanji OnYomi vocabs with kana and meaning
     * @return $this
     */
    private function _fetchOnYomiVocabs(): self
    {
        $name = 'On-Yomi Vocabs';
        $this->_gettingData($name);

        $domElements = $this->crawler->filter('div[class="small-12 large-6 columns"]:nth-of-type(1) > ul > li');

        $_link_list = [];
        foreach ($domElements as $domElement){

            $_content = $domElement->textContent;
            $_replacer = str_replace(["【" , "】"],"-", $_content);
            $_exploder_vocab = explode('-', $_replacer);
            $exploder_meaning = explode( ',' , $_exploder_vocab[2] );

            $_link_list[] = [
                'word'    => $_exploder_vocab[0],
                'kana'    => $_exploder_vocab[1],
                'meaning' => $exploder_meaning,
            ];

        }

        $this->_temp['onyomi_vocabs'] = $_link_list;
        $this->_dataGetted($name);
        return $this;
    }

    /**
     * Fetch Kanji KunYomi vocabs with kana and meaning
     * @return $this
     */
    private function _fetchKunYomiVocabs(): self
    {
        $name = 'Kun-Yomi Vocabs';
        $this->_gettingData($name);

        $domElements = $this->crawler->filter('div[class="small-12 large-6 columns"]:nth-of-type(2) > ul > li');

        $_link_list = [];
        foreach ($domElements as $domElement){

            $_content = $domElement->textContent;
            $_replacer = str_replace(["【" , "】"],"-", $_content);
            $_exploder_vocab = explode('-', $_replacer);
            $exploder_meaning = explode( ',' , $_exploder_vocab[2] );

            $_link_list[] = [
                'word'    => $_exploder_vocab[0],
                'kana'    => $_exploder_vocab[1],
                'meaning' => $exploder_meaning,
            ];

        }

        $this->_temp['kunyomi_vocabs'] = $_link_list;
        $this->_dataGetted($name);
        return $this;
    }
    #endregion

    #region image logic

    /**
     * Fetch Wiki Page and get Image link
     * @return $this
     */
    public function _fetchWikiGif() : self
    {
        $this->_data_echoer('going to wiki\'s page...');
        $domElements = $this->crawlerWiki->filter('table[class="floatright wikitable"] a[class="image"]');

        $gif = [];
        $this->_data_echoer('Going to get Image Links...');
        foreach ($domElements as $domElement){
            $path = $this->_getBaseUrlWiki() . $domElement->getAttribute("href");
            $gif[] = $path ;
        }

        $this->_temp['wiki_image_page'] =  $gif;
        $this->_data_echoer('Image Links Generated', 'green');
        return $this;
    }

    /**
     * Fetch wiki - Get, Download and push Image path to
     * @return $this
     */
    public function _fetchGetImage() : self
    {

        $image_path = [];
        foreach ( $this->_temp['wiki_image_page'] as $key => $imageUrl ) {

            $this->_setBaseUrlWikiImage( $imageUrl );

            $this->_initCrawlerWikiImage();

            $domElements = $this->crawlerWikiImage->filter('div[class="fullImageLink"] > a > img');

            foreach ($domElements as $domElement){
                $path       = $domElement->getAttribute("src");
                $final_path = str_replace('//' , 'https://' , $path);
                $extention  = pathinfo( $final_path , PATHINFO_EXTENSION );
                $aaaa       = $this->_fileSave($final_path);
                $image_path[] = [
                    'path'  => $final_path,
                    'local' => $aaaa
                ];
                $this->_data_echoer('image [' . $this->_temp['kanji'] .'.'. $extention . '] Downloaded.' , 'blue' , 'black', ['║', '║'] , ' ', 81);
//                $this->_str_pad( ' finished ║ fetching of kanji '.$kanji .' Finished, ['. $diff .'sec]' , ['║', '║'] , ' ' , 64 );

            }

        }

        $this->_temp['wiki_image_path'] = $image_path;
        $this->_data_echoer( 'All Images successfully downloaded!', 'green');
        return $this;
    }

    #endregion


}
