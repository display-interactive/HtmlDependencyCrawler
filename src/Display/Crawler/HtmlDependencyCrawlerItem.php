<?php

namespace Display\Crawler;


class HtmlDependencyCrawlerItem {

    /** @var  string */
    protected $id;

    /** @var  string */
    protected $absolute;

    /** @var  string */
    protected $given;

    /** @var  string */
    protected $content;


    /**
     * @param $absolute
     * @param $given
     * @param bool $autoLoad
     */
    public function __construct($absolute, $given, $autoLoad = false) {

        $this->setAbsolute($absolute);

        $this->setGiven($given);

        if ($autoLoad) {
            $this->load();
        }

    }

    /**
     * @return string
     */
    public function formatUri() {
        $uri = $this->absolute;
        if ($this->isStartingBySlash() || preg_match('/.+\.(jpg|png|jpeg|gif)/', $this->absolute)) {
            list($uri) = explode('?', $this->absolute);
        }

        return $uri;
    }

    /**
     * Load content
     */
    public function load() {
        $uri = $this->formatUri();
        $this->content = @file_get_contents($uri);
    }

    /**
     * @return bool
     */
    public function isLoaded() {
        return false !== $this->content;
    }

    /**
     * @param string $absolute
     */
    public function setAbsolute($absolute) {
        $this->absolute = $absolute;
        $this->setId(md5($absolute));
    }

    /**
     * @return string
     */
    public function getAbsolute() {
        return $this->absolute;
    }

    /**
     * @param string $content
     */
    public function setContent($content) {
        $this->content = $content;
    }

    /**
     * @return string
     */
    public function getContent() {
        return $this->content;
    }

    /**
     * @param string $given
     */
    public function setGiven($given) {
        $this->given = $given;
    }

    /**
     * @return string
     */
    public function getGiven() {
        return $this->given;
    }

    /**
     * @param string $id
     */
    public function setId($id) {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @return string
     */
    public function __toString() {
        return $this->absolute.' '.($this->isLoaded() ? 'loaded':'ko');
        //return $this->absolute.' '.$this->given.' '.($this->isLoaded() ? 'loaded':'ko');
    }

    /**
     * string start by slash
     *
     * @return bool
     */
    private function isStartingBySlash() {
        return 1 === preg_match('/^[A-Z]:|\/.+/i', $this->absolute);
    }

}