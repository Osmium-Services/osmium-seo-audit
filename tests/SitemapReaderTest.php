<?php

declare(strict_types=1);

namespace Tests\Services\SeoAudit;

use Osmium\Services\SeoAudit\Models\SitemapReader;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/SitemapReader.php';

class SitemapReaderTest extends TestCase
{
    public function test_it_returns_this_sites_paths_in_the_form_listed(): void
    {
        $xml = '<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            . '<url><loc>https://example.com</loc></url><url><loc>https://example.com/about/</loc></url>'
            . '<url><loc>https://example.com/shop?page=2</loc></url><url><loc>https://other.com/x/</loc></url>'
            . '<url><loc>https://example.com/about/</loc></url></urlset>';

        $this->assertSame(['/', '/about/', '/shop?page=2'], SitemapReader::paths($xml, 'example.com'));
    }

    public function test_broken_or_empty_xml_gives_nothing(): void
    {
        $this->assertSame([], SitemapReader::paths('<html>not a sitemap', 'example.com'));
        $this->assertSame([], SitemapReader::paths('', 'example.com'));
    }
}
