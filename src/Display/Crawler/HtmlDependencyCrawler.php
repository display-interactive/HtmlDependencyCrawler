<?php
namespace Display\Crawler;

use Display\CoreBundle\Debug\LogLevel;
use Ife\AnalyticsBundle\Tools\SimpleBench;
use Symfony\Component\DomCrawler\Crawler,
    Display\CoreBundle\Debug\Logger;

/**
 * Get dependency from Html file
 */
class HtmlDependencyCrawler
{
    /**
     * @var string
     */
    private $content;

    /**
     * @var \Symfony\Component\DomCrawler\Crawler
     */
    private $crawler;

    /**
     * @var string
     */
    private $uri;

    /**
     * @var array
     */
    private $parentDependencies;

    /**
     * Dependencies of given uri
     *
     * @var array
     */
    private $dependencies;

    /**
     * Failed parse
     *
     * @var array
     */
    private $failed;

    /**
     * @var array
     */
    private $headers = array();

    /**
     * @var array
     */
    private $directories = array();

    /**
     * @var array
     */
    private $roots = array();

    /**
     * @var string
     */
    private $urlAllowedChar = "[a-z0-9$.+!'()_-]";

    /**
     * @var boolean
     */
    private $verbose;


    /**
     * @var  bool
     */
    private $verboseDot;

    /**
     * @var  SimpleBench
     */
    private $bench;

    /**
     * @var int
     */
    private $level;

    /**
     * @param string $content
     * @param string $uri
     * @param array $parentDependencies
     * @param int $level
     * @throws HtmlDependencyCrawlerException when content is empty
     */
    public function __construct($content, $uri, $parentDependencies = array(), $level = 0)
    {
        if (false === $content) {
            throw new HtmlDependencyCrawlerException('no content to parse '.$uri);
        }
        $this->verbose = true;
        $this->verboseDot = true;
        $this->content = $content;
        $this->crawler = new Crawler($content);
        $this->uri = $this->resolveUrl($uri);
        $this->parentDependencies = $parentDependencies;
        $this->failed = array();
        $this->level = $level;
        if (!$this->verboseDot) {
            $this->log(str_repeat('.', 4*$level) . $uri);
        }
    }

    /**
     * Instanciate from url
     *
     * @param string $uri
     * @param array $parentDependencies
     * @return HtmlDependencyCrawler
     */
    public static function fromUrl($uri, $parentDependencies = array())
    {
        // remove params from uri
        $a = preg_split('/\?/', $uri);
        //print_r($a);
        $uri = $a[0];
        return new self(@file_get_contents($uri), $uri, $parentDependencies);
    }

    /**
     * @param array $excluded_values
     * @param bool $recursive
     * @return array
     */
    public function getDependencies($excluded_values = array(), $recursive = true)
    {
        if (false === isset($this->dependencies)) {
            $this->dependencies = $this->parentDependencies;

            //$this->bench = new SimpleBench();
            //$this->bench->startTimer('getDependencies', 'Start getDependencies '.$this->uri);


            $root = new HtmlDependencyCrawlerItem($this->uri, $this->uri);
            $root->setContent($this->content);
            $this->addDependency($root);

            //$this->debugAssets($this->dependencies);

            //styles
            $styles = $this->getAssetsUri('link[rel="stylesheet"]', 'href', $excluded_values);
            /** @var HtmlDependencyCrawlerItem $path */
            foreach ($styles as $path) {
                if ($path->isLoaded() && !$this->inDependencies($path->getAbsolute(), $this->dependencies)) {
                    $cssItems = $this->getCssDependencies($path->getContent(), $path->getAbsolute());
                    $this->mergeDependencies($this->dependencies, $cssItems);
                }
            }
            $this->mergeDependencies($this->dependencies, $styles);
            //$this->debugAssets($this->dependencies);

            //$this->bench->loop('getDependencies', 'end step1');

            //inner styles
            foreach ($this->getAssetsUri('style', null, $excluded_values) as $style) {
                $cssItems = $this->getCssDependencies($style, $this->uri);
                $this->mergeDependencies($this->dependencies, $cssItems);
            }
            //$this->bench->loop('getDependencies', 'end step2');
            //$this->debugAssets($this->dependencies);

            //scripts
            $scripts = $this->getAssetsUri('script[src]', 'src', $excluded_values);
            $this->mergeDependencies($this->dependencies, $scripts);
            //$this->bench->loop('getDependencies', 'end step3');

            //images
            $images = $this->getAssetsUri('img[src]', 'src', array_merge($excluded_values, array('`data:`')));
            $this->mergeDependencies($this->dependencies, $images);
            //$this->bench->loop('getDependencies', 'end step4');
            //
            //$this->debugAssets($this->dependencies);

            //href excluding external pages and anchor
            $links = $this->getAssetsUri('a[href]', 'href', array_merge($excluded_values, array('`^(http:|#|javascript:)`')));

            //$this->bench->loop('getDependencies', 'end step5');
            //$this->debugAssets($this->dependencies);

            if (true === $recursive) {
                /** @var HtmlDependencyCrawlerItem $link */
                foreach ($links as $link) {
                    $linkAbsolute = $link->getAbsolute();
                    if ($linkAbsolute != $this->uri && !$this->inDependencies($linkAbsolute, $this->dependencies)) {
                        try {
                            if ($this->verboseDot) $this->log(' ', 500);

                            //print '>> found child : '.$link.PHP_EOL;

                            // must add for next childs
                            $this->dependencies[] = $link;

                            $crawler = new HtmlDependencyCrawler($link->getContent(), $linkAbsolute, $this->dependencies, $this->level+1);
                            //$crawler = self::fromUrl($linkAbsolute, $this->dependencies);

                            $crawler->setVerbose($this->verbose);

                            $childDependencies = $crawler->getDependencies($excluded_values);

                            $this->mergeDependencies($this->dependencies, $childDependencies);

                            //$this->debugAssets($this->dependencies);


                            $this->failed = array_merge($this->failed, $crawler->getFailed());
                        } catch(HtmlDependencyCrawlerException $e) {
                            if ($this->verboseDot) $this->log('×', 500);
                            $this->failed[] = $link->getAbsolute();
                        }
                    }
                }
            }

            //$this->dependencies = $this->arrayUnique($this->dependencies);
            //$this->dependencies = $this->checkUri($this->dependencies);

            //$this->debugAssets($this->dependencies);

            //$this->bench->endTimer('getDependencies', 'end for '.$this->uri);


        }


        return $this->dependencies;
    }


    /**
     * Merge dependencies $b in $a
     *
     * @param array $a
     * @param array $b
     */
    protected function mergeDependencies(&$a, $b) {
        /** @var HtmlDependencyCrawlerItem $d */
        foreach ($b as $d) {
            if (!isset($a[$d->getId()])) {
                //print '>> add '.$d->getAbsolute().PHP_EOL;
                $a[$d->getId()] = $d;
            }
        }
    }


    /**
     * @param string $absolute
     * @return string
     */
    protected function getId($absolute) {
        return md5($absolute);
    }

    /**
     * @param HtmlDependencyCrawlerItem $dependency
     * @return bool
     */
    protected function addDependency($dependency) {
        if (!$this->inDependencies($dependency->getAbsolute(), $this->dependencies)) {
            $this->dependencies[$dependency->getId()] = $dependency;
            return true;
        }
        return false;
    }


    /**
     * @param array $assets
     */
    protected function debugAssets($assets) {
        foreach ($assets as $a) {
            print $a.PHP_EOL;
        }
    }

    /**
     * @return array
     */
    public function getFailed()
    {
        return $this->failed;
    }

    private function log($val, $level = LogLevel::DEBUG)
    {
        if ($this->verbose) Logger::log($this, $val, $level);
    }

    /**
     * get Headers wrapper for cache
     *
     * @param string $uri
     * @return array|bool
     */
    private function getHeaders($uri)
    {
        if (false === isset($this->headers[$uri])) {
            $this->headers[$uri] = @get_headers($uri);
        }

        return $this->headers[$uri];
    }

    /**
     * Resolve Url
     *
     * @param string $url
     * @return string
     */
    private function resolveUrl($url)
    {
        $headers = $this->getHeaders($url);
        if (false !== @$headers) {
            foreach($headers as $_i => $_value) {
                if(preg_match('`^Location: (.*)$`i', $_value, $matches)) {
                    # Overwrite the original location with the new one.
                    $url = $matches[1];
                    break;
                }
            }
        }

        return $url;
    }

    /**
     * get root from URI
     *
     * @param string $uri
     * @return string
     */
    private function getDirectory($uri)
    {
        if (false === isset($this->directories[$uri])) {
            if (false === $this->getHeaders($uri)) {
                $this->directories[$uri] = dirname($uri) . DIRECTORY_SEPARATOR;
            } else {
                $data = parse_url($uri);
                $this->directories[$uri] = 'http://' . $data['host'] . $data['path'];

                if (0 === preg_match('`.+\/$`', $this->directories[$uri])) {
                    $this->directories[$uri] = dirname($this->directories[$uri]) . '/';
                }
            }
        }

        return $this->directories[$uri];
    }

    /**
     * string start by slash
     *
     * @param string $uri
     * @return bool
     */
    private function isStartingBySlash($uri)
    {
        return 1 === preg_match('/^\/.+/i', $uri);
    }


    /**
     * get root
     *
     * @param string $dir
     * @return string mixed
     */
    private function getRoot($dir)
    {
        if (false === isset($this->roots[$dir])) {
            $ds = addslashes(DIRECTORY_SEPARATOR);
            $this->roots[$dir] = false;
            if ($this->isStartingBySlash($dir)) {
                $this->roots[$dir] = '/';
            } else if (false !== strpos($dir, 'http://')) { //start http:// = url
                $this->roots[$dir] = preg_replace("`(http://".$this->urlAllowedChar."+/).+`u", '$1', $dir);
            } else if (1 === preg_match('`([A-Z]):'.$ds.'('.$ds.')?.*`', $dir, $matches)) { //win dir
                $this->roots[$dir] = $matches[1] .  ':' . $ds;
            } else if (1 === preg_match('#'.$ds.$ds.'([^'.$ds.']+)'.$ds.'#u', $dir, $matches)) { //network dir
                $this->roots[$dir] = $ds . $ds . $matches[1] .  $ds;
            }
        }

        return $this->roots[$dir];
    }

    /**
     * get assets
     *
     * @param string $selector A CSS selector
     * @param string|null $attr
     * @param array|null $excluded_values
     * @return array
     */
    private function getAssetsUri($selector, $attr = null, $excluded_values = null)
    {
        //$this->bench->startTimer('getAssetsUri', 'start getAssetsUri');

        if ($this->verboseDot) $this->log('.', 500);
        $uris = $this->crawler->filter($selector)->each(function ($node, $i) use ($attr, $excluded_values) {

            if (null === $attr) {
                $value = $node->nodeValue;
            } else {
                $value = $node->getAttribute($attr);
            }

            if (null === $excluded_values) {
                return $value;
            } else {
                foreach ($excluded_values as $regex) {
                    if (preg_match($regex, $value)) {
                        return false;
                    }
                }
                return $value;
            }
        });
        //$this->bench->loop('getAssetsUri', 'step1');

        $uris = array_filter($uris);

        $newUris = array();
        foreach ($uris as $uri) {
            $absolute = $this->completeUri($uri, $this->getDirectory($this->uri));
            if (false === $this->inDependencies($absolute, $this->dependencies)) {
                $dependency = new HtmlDependencyCrawlerItem($absolute, $uri, true);
                //print '>> found '.$dependency.PHP_EOL;
                $newUris[] = $dependency;
            }
        }

        //$this->bench->loop('getAssetsUri', 'step2');


        return $newUris;
    }

    /**
     * Check if an uri is reachable
     *
     * @param string $uri
     * @return bool
     */
    private function isReachable($uri)
    {
        $isReachable = is_readable($uri);
        if (false === $isReachable) {
            $headers = $this->getHeaders($uri);
            if (false !== $headers && $headers[0] !== 'HTTP/1.1 404 Not Found') {
                $isReachable = true;
            }
        }

        return $isReachable;
    }

    /**
     * complete url with root or dir
     *
     * @param string $uri
     * @param string $dir
     * @return string
     */
    private function completeUri($uri, $dir)
    {
        if (false === $this->isReachable($uri)) { //if is not reachable we have to complete it
            if ($this->isStartingBySlash($uri)) { //if starting by '/' then add root
                $uri = $this->getRoot($dir) . substr($uri, 1);
            } else { //add dir
                $uri = $dir . $uri;
            }
        }

        return $this->realUri($uri);
    }

    /**
     * resolve dot on url
     *
     * @param string $uri
     * @return mixed
     */
    private function realUri($uri)
    {
        $realUri = realpath($uri);
        if (false === $realUri) {
            $realUri = $uri;
            do {
                $prev = $realUri;
                $realUri = preg_replace('`/'.$this->urlAllowedChar.'+/\.\./`iU', '/', $realUri);
            } while ($realUri != $prev);

            $realUri = str_replace(array('/./', './'), '/', $realUri);
        }

        return $realUri;
    }

    /**
     * get dependencies from css file
     *
     * @param string $style
     * @param string $url
     * @return array
     */
    private function getCssDependencies($style, $url)
    {
        if ($this->verboseDot) $this->log('.', 500);
        preg_match_all('`url\(((?!data:).+)\)`iU', $style, $matches);

        $dir = $this->getDirectory($url);
        $dependencies = array();
        foreach ($matches[1] as $match) {
            $uri = trim($match, chr('0x22').chr('0x27')); //remove ' and "
            $absolute = $this->completeUri($uri, $dir);
            if (false === $this->inDependencies($absolute, $this->dependencies)) {
                $dependency = new HtmlDependencyCrawlerItem($absolute, $uri, true);
                $dependencies[] = $dependency;
                //print '>> found '.$dependency.PHP_EOL;
            }
        }

        return $dependencies;
    }

    /**
     * check if given uri is in array $links
     *
     * @param string $uri
     * @param array $links
     * @return bool
     */
    private function inDependencies($uri, array $links)
    {
        return isset($links[$this->getId($uri)]);
        /*
        $test = false;
        foreach ($links as $link) {
            if  ($link['absolute'] == $uri) {
                return true;
            }
        }

        return $test;
        */
    }

    /**
     * @param array $array
     * @return array
     */
    private function arrayUnique(array $array)
    {
        $new = array();
        $old = $array;
        foreach ($array as $key => $el) {
            unset($old[$key]);
            $isDuplicated = false;
            foreach ($old as $el2) {
                if ($el2['absolute'] === $el['absolute']) {
                    $isDuplicated = true;
                }
            }
            if (false === $isDuplicated) {
                $new[] = $el;
            }
        }

        return $new;
    }

    /**
     * @param array $dependencies
     * @return array
     */
    private function checkUri(array $dependencies)
    {
        $reachableDependencies = array();
        /** @var HtmlDependencyCrawlerItem $dependency */
        foreach ($dependencies as $dependency) {
            if ($dependency->isLoaded()) {
                $reachableDependencies[] = $dependency;
            }
        }

        return $reachableDependencies;
    }

    /**
     * @param boolean $verbose
     */
    public function setVerbose($verbose)
    {
        $this->verbose = $verbose;
    }

    /**
     * @return boolean
     */
    public function getVerbose()
    {
        return $this->verbose;
    }
}
