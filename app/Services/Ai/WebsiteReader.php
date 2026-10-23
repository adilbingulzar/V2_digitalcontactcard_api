<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads the text of a public web page a user mentions, so the card assistant
 * can fill in the card from the user's own website.
 *
 * Only public addresses are fetched: the host must resolve to public IPs only,
 * the request is pinned to the checked IP, and every redirect is checked again.
 */
class WebsiteReader
{
    private const int MAX_BYTES = 500_000;

    private const int MAX_TEXT = 6000;

    private const int MAX_REDIRECTS = 3;

    /**
     * The first web address in a text ("northwind.studio", "https://…"), not counting email addresses.
     */
    public static function firstUrl(string $text): ?string
    {
        $found = preg_match('~(?<![@\w.-])((?:https?://)?(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,24}(?::\d+)?(?:/[^\s<>"\']*)?)~i', $text, $match);

        return $found === 1 ? rtrim($match[1], '.,;:!?)') : null;
    }

    /**
     * Title, description, contact links, pictures and visible text of the page, or null when it cannot be read.
     */
    public function read(string $url): ?string
    {
        $page = $this->download($url, '~^text/(html|plain)~i', 'text/html,text/plain');

        return $page === null ? null : $this->textOf($page['body'], $page['url']);
    }

    /**
     * A picture from a public web address (a logo or photo the user pointed to), or null.
     *
     * @return array{body: string, url: string}|null
     */
    public function image(string $url): ?array
    {
        return $this->download($url, '~^image/(png|jpe?g|webp|gif)~i', 'image/png,image/jpeg,image/webp,image/gif');
    }

    /**
     * @return array{body: string, url: string}|null
     */
    private function download(string $url, string $allowedType, string $accept): ?array
    {
        $url = preg_match('~^https?://~i', $url) === 1 ? $url : 'https://'.$url;

        for ($redirect = 0; $redirect <= self::MAX_REDIRECTS; $redirect++) {
            $target = $this->publicTarget($url);
            if ($target === null) {
                return null;
            }

            try {
                $response = Http::withOptions([
                    'allow_redirects' => false,
                    'curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ip']}"]],
                    'progress' => function (int $expected, int $downloaded): void {
                        if ($downloaded > self::MAX_BYTES) {
                            throw new RuntimeException('The page is too large.');
                        }
                    },
                ])->withHeaders(['Accept' => $accept])->connectTimeout(5)->timeout(10)->get($url);
            } catch (ConnectionException|RuntimeException) {
                return null;
            }

            if ($response->redirect()) {
                $location = $response->header('Location');
                if ($location === '') {
                    return null;
                }
                $url = str_starts_with($location, '/') ? "{$target['scheme']}://{$target['host']}{$location}" : $location;

                continue;
            }

            if (! $response->successful() || preg_match($allowedType, $response->header('Content-Type')) !== 1) {
                return null;
            }

            return ['body' => substr($response->body(), 0, self::MAX_BYTES), 'url' => $url];
        }

        return null;
    }

    /**
     * The IPv4 addresses a host name points to.
     *
     * @return list<string>
     */
    public function addresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        return array_values(gethostbynamel($host) ?: []);
    }

    /**
     * @return array{scheme: string, host: string, port: int, ip: string}|null
     */
    private function publicTarget(string $url): ?array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || ! in_array($port, [80, 443], true)) {
            return null;
        }

        $ips = $this->addresses($host);
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return null;
            }
        }

        return $ips === [] ? null : ['scheme' => $scheme, 'host' => $host, 'port' => $port, 'ip' => $ips[0]];
    }

    private function textOf(string $html, string $url): string
    {
        $title = preg_match('~<title[^>]*>(.*?)</title>~is', $html, $match) === 1 ? $match[1] : '';
        $description = preg_match('~<meta[^>]+name=["\']description["\'][^>]*content=["\']([^"\']*)~i', $html, $match) === 1 ? $match[1] : '';

        preg_match_all('~href=["\']((?:mailto:|tel:|https?://(?:www\.)?(?:instagram|facebook|linkedin|tiktok|x|twitter|youtube|wa)\.(?:com|me))[^"\']*)~i', $html, $links);
        $pictures = $this->pictures($html, $url);

        $body = preg_replace('~<(script|style|noscript|svg|template)\b.*?</\1>~is', ' ', $html) ?? '';
        $body = html_entity_decode(strip_tags(preg_replace('~<(br|p|div|li|h\d|tr)\b~i', "\n<$1", $body) ?? ''), ENT_QUOTES | ENT_HTML5);
        $body = trim(preg_replace(['~[ \t\x{00A0}]+~u', '~\s*\n\s*~'], [' ', "\n"], $body) ?? '');

        $parts = [
            'Address: '.$url,
            'Title: '.trim(html_entity_decode($title, ENT_QUOTES | ENT_HTML5)),
            'Description: '.trim(html_entity_decode($description, ENT_QUOTES | ENT_HTML5)),
            'Contact and social links: '.implode(', ', array_slice(array_unique($links[1]), 0, 15)),
            'Logo candidates: '.($pictures['logos'] === [] ? 'none (no logo picture on this page; the logo may be text or drawn in code)' : implode(', ', $pictures['logos'])),
            'Brand name as shown in the page header: '.($this->brandText($html) ?? 'not found'),
            'Other pictures: '.implode(', ', $pictures['other']),
            "Text:\n".$body,
        ];

        return mb_substr(implode("\n", $parts), 0, self::MAX_TEXT);
    }

    /**
     * Picture addresses on the page, made absolute: likely logos first (images and
     * icons named "logo", touch icons), then the share picture and other images.
     *
     * @return array{logos: list<string>, other: list<string>}
     */
    private function pictures(string $html, string $pageUrl): array
    {
        $absolute = function (string $src) use ($pageUrl): ?string {
            $src = html_entity_decode(trim($src), ENT_QUOTES | ENT_HTML5);
            if ($src === '' || str_starts_with($src, 'data:') || preg_match('~\.svg(\?|$)~i', $src) === 1) {
                return null;
            }
            if (str_starts_with($src, '//')) {
                return 'https:'.$src;
            }
            if (preg_match('~^https?://~i', $src) === 1) {
                return $src;
            }
            $parts = parse_url($pageUrl);
            $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

            return str_starts_with($src, '/') ? $origin.$src : $origin.'/'.ltrim($src, './');
        };

        $logos = [];
        $other = [];
        preg_match_all('~<img\b[^>]*>~i', $html, $images);
        foreach ($images[0] as $tag) {
            if (preg_match('~\bsrc=["\']([^"\']+)~i', $tag, $src) !== 1 || ($url = $absolute($src[1])) === null) {
                continue;
            }
            if (preg_match('~logo~i', $tag) === 1) {
                $logos[] = $url;
            } else {
                $other[] = $url;
            }
        }
        preg_match_all('~<link\b[^>]*rel=["\'][^"\']*(apple-touch-icon|icon)[^"\']*["\'][^>]*>~i', $html, $icons);
        foreach ($icons[0] as $tag) {
            if (preg_match('~\bhref=["\']([^"\']+)~i', $tag, $href) === 1 && ($url = $absolute($href[1])) !== null) {
                $logos[] = $url;
            }
        }
        if (preg_match('~<meta[^>]+property=["\']og:image["\'][^>]*content=["\']([^"\']+)~i', $html, $share) === 1 && ($url = $absolute($share[1])) !== null) {
            $other = [$url, ...$other];
        }

        return ['logos' => array_slice(array_values(array_unique($logos)), 0, 6), 'other' => array_slice(array_values(array_unique($other)), 0, 8)];
    }

    /**
     * The text of the page's logo when it is written rather than a picture:
     * an element named "logo" or "brand", or the first link in the header.
     */
    private function brandText(string $html): ?string
    {
        $candidates = [];
        if (preg_match('~<[a-z]+[^>]*class=["\'][^"\']*\b(logo|brand)[^"\']*["\'][^>]*>(.*?)</[a-z]+>~is', $html, $named) === 1) {
            $candidates[] = $named[2];
        }
        if (preg_match('~<header\b.*?<a\b[^>]*>(.*?)</a>~is', $html, $link) === 1) {
            $candidates[] = $link[1];
        }
        foreach ($candidates as $candidate) {
            $text = trim(preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags($candidate), ENT_QUOTES | ENT_HTML5)) ?? '');
            if ($text !== '' && mb_strlen($text) <= 60) {
                return $text;
            }
        }

        return null;
    }
}
