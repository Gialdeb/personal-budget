<?php

namespace App\Http\Controllers;

use App\Support\Seo\PublicPageSeoResolver;
use Illuminate\Http\Response;
use SimpleXMLElement;

class PublicSitemapController extends Controller
{
    public function __invoke(PublicPageSeoResolver $resolver): Response
    {
        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>'
        );

        foreach ($resolver->sitemapEntries() as $entry) {
            $url = $xml->addChild('url');
            $url->addChild('loc', $entry['url']);

            if (is_string($entry['lastmod']) && $entry['lastmod'] !== '') {
                $url->addChild('lastmod', $entry['lastmod']);
            }
        }

        return response($xml->asXML() ?: '', 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
