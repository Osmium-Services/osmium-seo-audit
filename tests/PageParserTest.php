<?php

declare(strict_types=1);

namespace Tests\Services\SeoAudit;

use Osmium\Services\SeoAudit\Models\PageParser;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/PageParser.php';

class PageParserTest extends TestCase
{
    private const PAGE = <<<'HTML'
<!doctype html><html><head><title> Solar panels  for homes </title>
<meta name="description" content="Fitted by experts">
<meta name="robots" content="index,follow">
<meta name="viewport" content="width=device-width">
<meta property="og:title" content="Solar"><meta property="og:image" content="/a.jpg">
<link rel="canonical" href="https://example.com/solar/">
<script type="application/ld+json">{"@type":"Thing"}</script>
<script type="application/ld+json">{broken</script>
<script>var hidden = "not counted words";</script></head>
<body><h1>Solar</h1><h2>Why</h2><h4>Skipped</h4>
<p>One two three four five.</p>
<img src="a.jpg" alt="A panel"><img src="b.jpg"><img src="c.jpg" alt="">
<a href="/about/">About</a><a href="contact/">Contact</a><a href="https://example.com/shop/?x=1#top">Shop</a>
<a href="https://other.com/x/">Other</a><a href="mailto:a@b.c">Mail</a><a href="#top">Top</a><a href="/files/a.pdf">PDF</a>
</body></html>
HTML;

    private function parsed(): array
    {
        return PageParser::parse(self::PAGE, '/solar/', 'example.com');
    }

    public function test_it_reads_the_head_tags(): void
    {
        $page = $this->parsed();

        $this->assertSame('Solar panels for homes', $page['title']);
        $this->assertSame('Fitted by experts', $page['description']);
        $this->assertSame('https://example.com/solar/', $page['canonical']);
        $this->assertSame('index,follow', $page['robots']);
        $this->assertTrue($page['has_viewport']);
        $this->assertTrue($page['og_title']);
        $this->assertTrue($page['og_image']);
    }

    public function test_it_counts_headings_images_and_broken_structured_data(): void
    {
        $page = $this->parsed();

        $this->assertSame(1, $page['h1_count']);
        $this->assertSame('1,2,4', $page['heading_levels']);
        $this->assertSame(1, $page['images_missing_alt']);
        $this->assertSame(1, $page['jsonld_invalid']);
    }

    public function test_word_count_ignores_scripts(): void
    {
        $this->assertSame(15, $this->parsed()['word_count']);
    }

    public function test_internal_links_are_resolved_and_external_ones_dropped(): void
    {
        $this->assertSame(['/about/', '/solar/contact/', '/shop/'], $this->parsed()['links']);
    }

    public function test_it_reads_the_language_and_declared_icons(): void
    {
        $html = '<html lang="en-GB"><head><link rel="shortcut icon" href="/favicon.ico"><link rel="icon" href="/a.png">'
            . '<link rel="apple-touch-icon" href="/apple.png"><link rel="stylesheet" href="/x.css"></head><body>x</body></html>';

        $page = PageParser::parse($html, '/', 'example.com');

        $this->assertSame('en-GB', $page['lang']);
        $this->assertSame(['/favicon.ico', '/a.png'], $page['icons']['icon']);
        $this->assertSame(['/apple.png'], $page['icons']['apple']);
    }

    public function test_it_collects_this_sites_images_only(): void
    {
        $html = '<html><body><img src="/a.jpg"><img src="b.png?v=2"><img data-src="/lazy.webp"><img src="https://example.com/c.gif">'
            . '<img src="https://cdn.other.com/x.jpg"><img src="data:image/png;base64,AAAA"><img src="/a.jpg"><img></body></html>';

        $images = PageParser::parse($html, '/shop/', 'example.com')['images'];

        $this->assertSame(['/a.jpg', '/shop/b.png?v=2', '/lazy.webp', '/c.gif'], $images);
    }

    public function test_it_lists_http_resources_but_not_plain_links(): void
    {
        $html = '<html><head><link rel="stylesheet" href="http://x.com/a.css"><link rel="canonical" href="http://example.com/"><script src="http://x.com/a.js"></script></head>'
            . '<body><img src="http://x.com/i.png"><img src="https://x.com/ok.png"><iframe src="http://x.com/f"></iframe><a href="http://x.com/page">link</a></body></html>';

        $this->assertSame(['http://x.com/a.css', 'http://x.com/a.js', 'http://x.com/i.png', 'http://x.com/f'], PageParser::parse($html, '/', 'example.com')['insecure']);
    }

    public function test_cloudflare_proxy_links_are_not_internal_pages(): void
    {
        $html = '<html><body><a href="/cdn-cgi/l/email-protection#abc">mail</a><a href="/about/">About</a></body></html>';

        $this->assertSame(['/about/'], PageParser::parse($html, '/', 'example.com')['links']);
    }

    public function test_the_robots_header_is_added_to_the_meta_value(): void
    {
        $page = PageParser::parse('<html><head><meta name="robots" content="index"></head><body>x</body></html>', '/', 'example.com', 'noindex');

        $this->assertTrue(PageParser::isNoindex($page['robots']));
    }

    public function test_an_empty_page_does_not_crash(): void
    {
        $page = PageParser::parse('', '/', 'example.com');

        $this->assertSame('', $page['title']);
        $this->assertSame(0, $page['word_count']);
        $this->assertNull($page['body_hash']);
    }
}
