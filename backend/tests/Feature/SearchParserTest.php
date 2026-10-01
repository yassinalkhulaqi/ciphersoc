<?php

namespace Tests\Feature;

use App\Support\SearchParser;
use Tests\TestCase;

class SearchParserTest extends TestCase
{
    public function test_parses_event_filters(): void
    {
        $r = SearchParser::parse('source_ip:10.0.0.1 hostname:web-01 severity:critical failed login', 'events');
        $this->assertEquals('10.0.0.1', $r['filters']['source_ip']);
        $this->assertEquals('web-01', $r['filters']['hostname']);
        $this->assertEquals('critical', $r['filters']['severity']);
        $this->assertEquals('failed login', $r['free']);
    }

    public function test_parses_alert_filters(): void
    {
        $r = SearchParser::parse('severity:high status:new mitre:T1110 brute force', 'alerts');
        $this->assertEquals('high', $r['filters']['severity']);
        $this->assertEquals('T1110', $r['filters']['mitre']);
        $this->assertEquals('brute force', $r['free']);
    }

    public function test_empty_and_unknown_keys(): void
    {
        $this->assertEquals([], SearchParser::parse('', 'events')['filters']);
        $r = SearchParser::parse('foo:bar hello', 'events');
        $this->assertArrayNotHasKey('foo', $r['filters']);
        $this->assertStringContainsString('hello', $r['free']);
    }
}
