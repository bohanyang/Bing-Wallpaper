<?php

declare(strict_types=1);

namespace BohanYang\BingWallpaper\Tests;

use PHPUnit\Framework\TestCase;
use BohanYang\BingWallpaper\ResponseParser;

class ResponseParserTest extends TestCase
{
    public function testExtractKeyword() : void
    {
        foreach ([
            'javascript:void(0);' => null,
            'http://www.msxiaona.cn/' => null,
            'https://bingdict.chinacloudsites.cn/download?tag=BDPDV' => null,
            'http://www.bing.com/search?q=%E5%BC%80%E6%99%AE%E6%A2%85%E8%8E%BA' => '开普梅莺',
            'https://www.baidu.com/s?q=&wd=%E5%8D%8E%E4%B8%BA' => '华为',
        ] as $url => $expected) {
            $keyword = ResponseParser::extractKeyword($url);
            $this->assertSame($expected, $keyword);
        }
    }

    public function testParseUrlBase() : void
    {
        foreach ([
            '/az/hprichbg/rb/PineBough_ROW6233127332' => [
                'PineBough_ROW6233127332',
                'PineBough',
                'ROW6233127332'
            ],
            '/az/hprichbg/rb/FlowerFes__JA-JP2679822467' => [
                'FlowerFes__JA-JP2679822467',
                'FlowerFes_',
                'JA-JP2679822467'
            ],
            '/th?id=OHR.PingxiSky_EN-GB0458915063' => [
                'PingxiSky_EN-GB0458915063',
                'PingxiSky',
                'EN-GB0458915063'
            ],
        ] as $urlBase => $expected) {
            $actual = ResponseParser::parseUrlBase($urlBase);
            $this->assertSame($expected, $actual);
        }
    }

    public function testParseCopyright() : void
    {
        foreach ([
            'Un ourson noir dans un pin, Parc national Jasper, Alberta' .
            ' (Ursus americanus) (© Donald M. Jones/Minden Pictures)' => [
                'Un ourson noir dans un pin, Parc national Jasper, Alberta (Ursus americanus)',
                'Donald M. Jones/Minden Pictures',
            ],
            '来自人工智能的画作《思念》（© 微软小冰）' => [
                '来自人工智能的画作《思念》',
                '微软小冰',
            ],
            '｢国立科学博物館｣東京, 台東区（©　WindAwake/Shutterstock）' => [
                '｢国立科学博物館｣東京, 台東区',
                'WindAwake/Shutterstock',
            ]
        ] as $copyright => $expected) {
            $actual = ResponseParser::parseCopyright($copyright);
            $this->assertSame($expected, $actual);
        }
    }

    public function testParseFullStartDate() : void
    {
        foreach ([
            '201905221600' => '2019-05-23 00:00:00 +08:00',
            '201905230700' => '2019-05-23 00:00:00 -07:00',
            '201905221830' => '2019-05-23 00:00:00 +05:30',
            '201905221400' => '2019-05-23 00:00:00 +10:00',
        ] as $fullStartDate => $expected) {
            $date = ResponseParser::parseFullStartDate((string) $fullStartDate);
            $this->assertSame($expected, $date->format('Y-m-d H:i:s P'));
        }
    }
}
