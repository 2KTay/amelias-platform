<?php

declare(strict_types=1);

namespace Amelias\Controllers;

use Amelias\Database\Database;
use Amelias\Http\Request;
use Amelias\Services\Content;
use Amelias\Services\Settings;
use Amelias\Support\Seo;

/**
 * Public marketing / CMS pages (screens 1–3, 2b, 18a, 29–31 content).
 *
 * Task 1.6 binds hero/story/legal copy to the CMS Content service, ships the
 * blog, and wires per-page CSS + JSON-LD. Templates reuse the brand-v3 component
 * classes already in the production CSS; page-specific tweaks live under
 * public/assets/css/pages/<name>.css and are passed via $styles.
 */
final class PublicController extends Controller
{
    public function home(Request $request): void
    {
        $this->viewPublic('public/home', [
            'title'       => null,
            'description' => "Source-to-plate dining, market, wine club and events in Arizona. Amelia's by EAT.",
            'jsonLd'      => Seo::restaurant(),
            'featured'    => $this->featuredProducts(),
        ]);
    }

    /**
     * Featured "From the kitchen" products for the home page.
     *
     * Reads the ordered, comma-separated product IDs from the
     * `home.featured_products` setting and fetches each active product by ID
     * (bound params, one query per ID) so display order is preserved. Returns
     * an empty array when nothing is configured, which omits the whole section.
     *
     * @return list<array<string,mixed>>
     */
    private function featuredProducts(): array
    {
        $raw = (string) Settings::get('home.featured_products', '');
        if (trim($raw) === '') {
            return [];
        }

        $featured = [];
        foreach (explode(',', $raw) as $part) {
            $id = (int) trim($part);
            if ($id <= 0) {
                continue;
            }
            $product = Database::fetch(
                'SELECT id, slug, name, description, price_cents, image_path
                 FROM products WHERE id = ? AND is_active = 1',
                [$id]
            );
            if ($product !== null) {
                $featured[] = $product;
            }
        }

        return $featured;
    }

    public function story(Request $request): void
    {
        $this->viewPublic('public/story', [
            'title'     => 'Our Story',
            'styles'    => ['pages/story.css'],
            'storyBody' => $this->storyBody(),
        ]);
    }

    public function location(Request $request): void
    {
        $this->viewPublic('public/location', [
            'title'            => 'Visit Us',
            'styles'           => ['pages/location.css'],
            'jsonLd'           => Seo::restaurant([
                'address' => [
                    'streetAddress'   => '8240 N Hayden Road, Ste B-105',
                    'addressLocality' => 'Scottsdale',
                    'addressRegion'   => 'AZ',
                    'postalCode'      => '85258',
                    'addressCountry'  => 'US',
                ],
                'telephone' => '+1-602-499-5195',
                'hours'     => ['Su 07:00-15:00', 'Mo-Th 07:00-20:00', 'Fr-Sa 07:00-22:00'],
            ]),
            'formspreeContact' => 'https://formspree.io/f/REPLACE_ME',
        ]);
    }

    public function purveyors(Request $request): void
    {
        $this->viewPublic('public/purveyors', [
            'title'  => 'Our Purveyors',
            'styles' => ['pages/purveyors.css'],
        ]);
    }

    public function careers(Request $request): void
    {
        $this->viewPublic('public/careers', [
            'title'            => 'Now Hiring',
            'styles'           => ['pages/careers.css'],
            'formspreeCareers' => 'https://formspree.io/f/REPLACE_ME',
        ]);
    }

    public function privacy(Request $request): void
    {
        $this->viewPublic('public/privacy', [
            'title' => 'Privacy Policy',
            'page'  => Content::getPage('privacy'),
        ]);
    }

    public function terms(Request $request): void
    {
        $this->viewPublic('public/terms', [
            'title' => 'Terms & Refund Policy',
            'page'  => Content::getPage('terms'),
        ]);
    }

    public function blog(Request $request): void
    {
        $this->viewPublic('public/blog', [
            'title' => 'Journal',
            'posts' => Content::listPosts(),
        ]);
    }

    public function post(Request $request): void
    {
        $slug = (string) $request->param('slug', '');
        $page = $slug !== '' ? Content::getPage($slug) : null;

        if ($page === null || ($page['type'] ?? 'page') !== 'post') {
            $this->viewPublic('errors/404', ['path' => $request->path], 404);
            return;
        }

        $this->viewPublic('public/post', [
            'title'       => $page['seo_title'] ?: $page['title'],
            'description' => $page['seo_description'] ?: ($page['excerpt'] ?? null),
            'post'        => $page,
        ]);
    }

    /** CMS story body (sanitized), wrapped in a paragraph if it's a plain string. */
    private function storyBody(): string
    {
        $body = Content::get('story.body', '');
        if ($body === '') {
            return '';
        }
        // A plain-text block (no markup) becomes a single paragraph.
        if (strip_tags($body) === $body) {
            return '<p>' . e($body) . '</p>';
        }
        return $body;
    }
}
