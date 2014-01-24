<?php


use Display\Crawler\HtmlDependencyCrawler;

/**
 * @todo is not valid test anymore
 */
class HtmlDependencyCrawlerTest extends \PHPUnit_Framework_TestCase
{
    public static function getMethod($name)
    {
        $method = new \ReflectionMethod('Display\CoreBundle\Util\HtmlDependencyCrawler', $name);
        $method->setAccessible(true);

        return $method;
    }

    public static function getLambdaObject()
    {
        return new HtmlDependencyCrawler('<html></html>', 'http://www.display-interactive.com/');
    }

    public static function randomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $string = '';
        for ($p = 0; $p < $length; $p++) {
            $string .= $characters[mt_rand(0, strlen($characters)-1)];
        }

        return $string;
    }

    public function testResolveUrl()
    {
        $crawler = self::getLambdaObject();
        $method = self::getMethod('resolveUrl');
        $this->assertEquals('https://twitter.com', $method->invokeArgs($crawler, array('http://www.twitter.fr')));
        $this->assertEquals('http://fr-fr.facebook.com/', $method->invokeArgs($crawler, array('http://www.facebook.fr')));
    }

    public function testGetDirectory()
    {
        $crawler = self::getLambdaObject();
        $method = self::getMethod('getDirectory');
        $this->assertEquals(
            'http://www.display-interactive.com/',
            $method->invokeArgs($crawler, array('http://www.display-interactive.com/index-'.rand(0,999999).'.php'))
        );
    }

    public function testIsStartingBySlash()
    {
        $crawler = self::getLambdaObject();
        $method = self::getMethod('isStartingBySlash');
        $string = self::randomString();
        if (rand(0, 1)) {
            $this->assertTrue($method->invokeArgs($crawler, array('/' . $string)));
        } else {
            $this->assertFalse($method->invokeArgs($crawler, array($string)));
        }
    }

    public function testGetRoot()
    {
        $crawler = self::getLambdaObject();
        $method = self::getMethod('getRoot');

        $ds = DIRECTORY_SEPARATOR;
        $uris = array(
            array('http://localhost/ife_v0/bin/www/univers/board_services/comfort/bebes.en_US.html' , 'http://localhost/'),
            array('http://extranet.display-interactive.com/wiki/', 'http://extranet.display-interactive.com/'),
            array(__DIR__ . $ds . 'Fixtures' . $ds . 'sample.html', 'C:'.$ds),
            array($ds.$ds.'TWIX'.$ds.'transferts'.$ds.'florian'.$ds.'Fixtures.'.$ds.'sample.html', $ds.$ds.'TWIX'.$ds),
        );

        foreach ($uris as $uri) {
            $this->assertEquals(addslashes($uri[1]), $method->invokeArgs($crawler, array($uri[0])));
        }
    }

    public function testGetAssetsUri()
    {
        $url = 'http://www.display-interactive.com/';
        //$selector, $attr = null, $excluded_values = null
        $contents = array(
            array('test.jpg', '<img src="test.jpg" alt="" /> <img src="data:blalblalg" alt="" />', 'img', 'src', '`data:`'),
            array('test.js', '<script type="text/javascript" src="test.js"></script>', 'script', 'src', null),
        );
        foreach ($contents as $content) {
            $crawler = new HtmlDependencyCrawler("<html><body>{$content[1]}</body></html>", $url);
            $method = self::getMethod('getAssetsUri');
            $assets = $method->invokeArgs($crawler, array($content[2], $content[3], $content[4]));
            foreach ($assets as $asset) {
                $this->assertContains($content[0], $asset);
            }
        }
    }

    public function testIsReachable()
    {
        $crawler = self::getLambdaObject();
        $method = self::getMethod('isReachable');
        $ds = DIRECTORY_SEPARATOR;
        $this->assertFalse($method->invokeArgs($crawler, array('http://www.display-interactive.com/coconut.php')));
        $this->assertTrue($method->invokeArgs($crawler, array('http://www.display-interactive.com/')));
        $this->assertTrue($method->invokeArgs($crawler, array(__DIR__ . $ds . 'Fixtures' . $ds . 'sample.html')));
    }

    public function testCompleteUri()
    {
        $crawler = self::getLambdaObject();
        $method = self::getMethod('completeUri');
        $uris = array(
            'http://www.display-interactive.com/' => array('http://www.display-interactive.com/', 'test'),
            'http://www.display-interactive.com/html/css/main.css' => array('html/css/main.css', 'http://www.display-interactive.com/'),
            'http://www.display-interactive.com/medias/images/favicon.ico' => array('/medias/images/favicon.ico', 'http://www.display-interactive.com/'),
        );
        foreach ($uris as $real => $given) {
            $this->assertEquals($real, $method->invokeArgs($crawler, $given));
        }
    }

    public function testRealUri()
    {
        $crawler = self::getLambdaObject();
        $method = self::getMethod('realUri');
        $uris = array(
            'http://localhost/test.php' => 'http://localhost/test/../test.php',
            'http://localhost/1/test.php' => 'http://localhost/1/test/test/../../test.php',
            'http://localhost/2/test.php' => 'http://localhost/./2/test.php',
            'C:\\\\test\\/test.php' => 'C:\\\\test\\./test.php'
        );
        foreach ($uris as $real => $given) {
            $this->assertEquals($real, $method->invokeArgs($crawler, array($given)));
        }
    }

    public function testGetCssDependencies()
    {
        $dir = __DIR__ . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR;
        $css = 'stylesheet.css';
        $crawler = self::getLambdaObject();
        $method = self::getMethod('getCssDependencies');
        $assets = $method->invokeArgs($crawler, array(file_get_contents($dir.$css), $dir.$css));
        $this->assertCount(2, $assets);
        foreach ($assets as $asset) {
            $this->assertContains($dir.'php.gif', $asset);
        }
    }

    public function testInDependencies()
    {
        $crawler = self::getLambdaObject();
        $method = self::getMethod('inDependencies');
        $uri = '/test.html';
        $data = array(
            array('given' => 'test', 'absolute' => $uri)
        );

        $this->assertTrue($method->invokeArgs($crawler, array($uri, $data)));
    }

    public function testArrayUnique()
    {
        $crawler = self::getLambdaObject();
        $method = self::getMethod('arrayUnique');

        $data = array(
            array('given' => 'test', 'absolute' => 'test.html', 'content' => '<html></html>'),
            array('given' => 'test', 'absolute' => 'test.html', 'content' => '<html></html>'),
            array('given' => 'test', 'absolute' => 'test.html', 'content' => '<html></html>'),
            array('given' => 'test', 'absolute' => 'test.html', 'content' => '<html></html>'),
        );
        $this->assertCount(1, $method->invokeArgs($crawler, array($data)));
    }

    public function testCheckUri()
    {
        $crawler = self::getLambdaObject();
        $method = self::getMethod('checkUri');

        $data = array(
            array('given' => 'test', 'absolute' => 'test.html', 'content' => '<html></html>'),
            array('given' => 'test', 'absolute' => 'test.html', 'content' => false),
            array('given' => 'test', 'absolute' => 'http://www.display-interactive.com/html/css/main.css', 'content' =>  false),
            array('given' => 'test', 'absolute' => 'test.html', 'content' =>  false),
        );
        $this->assertCount(1, $method->invokeArgs($crawler, array($data)));
    }

    public function provider()
    {
        $ds = DIRECTORY_SEPARATOR;
        return array(
            array('http://www.display-interactive.com/'),
            array('http://redmine.display-interactive.com'),
            array(__DIR__ . $ds . 'Fixtures' . $ds . 'sample.html'),
        );
    }

    /**
     * @dataProvider provider
     */
    public function testGetDependencies($url)
    {
        $crawler = HtmlDependencyCrawler::fromUrl($url);
        $assets = $crawler->getDependencies();
    }
}