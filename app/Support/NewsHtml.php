<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Treść newsa z edytora w panelu (HTML). Przed zapisem i przy wyświetlaniu przechodzi przez białą listę:
 * akapity, nagłówki, pogrubienia, listy, cytat, linki (też jako ikony social mediów) i obrazki wgrane do
 * storage/news. Wszystko inne jest usuwane (skrypty, style, ramki) albo rozpakowywane (zostaje sam tekst).
 * Starsze newsy to zwykły tekst: render() zamienia je na akapity.
 */
final class NewsHtml
{
    /** Serwisy z ikoną w edytorze (klasa social-{klucz}). */
    public const SOCIALS = [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'x' => 'X',
        'youtube' => 'YouTube',
        'tiktok' => 'TikTok',
        'threads' => 'Threads',
        'whatsapp' => 'WhatsApp',
    ];

    /** Szerokości obrazka w procentach do wyboru w edytorze (i jedyne przyjmowane). */
    public const WIDTHS = [25, 33, 50, 66, 75, 100];

    /** Dozwolone znaczniki. */
    private const TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h2', 'h3', 'ul', 'ol', 'li', 'blockquote', 'a', 'img'];

    /** Znaczniki usuwane razem z zawartością. */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'noscript', 'form', 'input', 'button', 'select', 'textarea', 'head', 'title', 'meta', 'link'];

    /** Czy treść jest już HTML-em z edytora (a nie starym zwykłym tekstem). */
    public static function isHtml(string $body): bool
    {
        return (bool) preg_match('/<(p|br|h2|h3|ul|ol|blockquote|strong|em|a|img)[\s>\/]/i', $body);
    }

    /** HTML do wyświetlenia: stary tekst jako akapity, HTML z edytora po oczyszczeniu. */
    public static function render(string $body): string
    {
        if (! self::isHtml($body)) {
            $paragraphs = preg_split('/\R{2,}/', trim($body)) ?: [];

            return implode('', array_map(
                fn (string $p) => '<p>'.nl2br(e(trim($p)), false).'</p>',
                array_filter($paragraphs, fn (string $p) => trim($p) !== ''),
            ));
        }

        return self::clean($body);
    }

    /** Sam tekst (podgląd w panelu, opis przy udostępnianiu). */
    public static function plain(string $body): string
    {
        $text = self::isHtml($body)
            ? html_entity_decode(strip_tags(preg_replace('/<\/(p|h2|h3|li|blockquote)>|<br\s*\/?>/i', "$0\n", $body) ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : $body;

        return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $text) ?? '') ?? '');
    }

    /** Adres pierwszego obrazka z treści (miniatura na listach) albo null. */
    public static function firstImage(string $body): ?string
    {
        if (! preg_match('/<img[^>]+src="([^"]+)"/i', $body, $m)) {
            return null;
        }
        $src = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_match('#/storage/news/[A-Za-z0-9._-]+$#', (string) parse_url($src, PHP_URL_PATH)) ? $src : null;
    }

    /** Biała lista znaczników i atrybutów. */
    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="news-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('news-root');
        if (! $root) {
            return '';
        }

        self::cleanChildren($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    private static function cleanChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                // Komentarze, instrukcje przetwarzania itp.
                $node->removeChild($child);

                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);

                continue;
            }

            if (! in_array($tag, self::TAGS, true)) {
                // Nieznany znacznik (np. div, span z edytora): zostaje jego zawartość.
                self::cleanChildren($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            // Najpierw zawartość: link z błędnym adresem zostawia ją na swoim miejscu.
            self::cleanChildren($child);

            $keep = match ($tag) {
                'a' => self::cleanLink($child),
                'img' => self::cleanImage($child),
                default => self::stripAttributes($child, []),
            };

            if (! $keep) {
                $node->removeChild($child);
            }
        }
    }

    /** @param  array<string, string>  $set  atrybuty ustawiane po wyczyszczeniu */
    private static function stripAttributes(DOMElement $el, array $set): bool
    {
        foreach (iterator_to_array($el->attributes ?? []) as $attr) {
            $el->removeAttribute($attr->nodeName);
        }
        foreach ($set as $name => $value) {
            $el->setAttribute($name, $value);
        }

        return true;
    }

    /** Link: tylko http(s), mailto albo adres względny; ikona social tylko ze znanej listy. */
    private static function cleanLink(DOMElement $a): bool
    {
        $href = trim($a->getAttribute('href'));
        $social = null;
        foreach (preg_split('/\s+/', $a->getAttribute('class')) ?: [] as $class) {
            if (str_starts_with($class, 'social-') && isset(self::SOCIALS[substr($class, 7)])) {
                $social = substr($class, 7);
            }
        }

        if (! preg_match('#^(https?://|mailto:|/(?!/))#i', $href)) {
            // Zły albo groźny adres (np. javascript:): zostaje sam tekst linku.
            while ($a->firstChild) {
                $a->parentNode?->insertBefore($a->firstChild, $a);
            }

            return false;
        }

        $set = ['href' => $href];
        if (preg_match('#^https?://#i', $href)) {
            $set['target'] = '_blank';
            $set['rel'] = 'noopener nofollow';
        }
        if ($social) {
            $set['class'] = 'social-link social-'.$social;
        }

        return self::stripAttributes($a, $set);
    }

    /** Obrazek: tylko pliki wgrane do storage/news; szerokość i wyrównanie z list. */
    private static function cleanImage(DOMElement $img): bool
    {
        $src = trim($img->getAttribute('src'));
        $path = (string) parse_url($src, PHP_URL_PATH);
        $scheme = parse_url($src, PHP_URL_SCHEME);

        if (! preg_match('#/storage/news/[A-Za-z0-9._-]+$#', $path) || ! in_array($scheme, [null, 'http', 'https'], true)) {
            return false;
        }

        $width = (int) $img->getAttribute('data-width');
        $width = in_array($width, self::WIDTHS, true) ? $width : 100;
        $align = $img->getAttribute('data-align');
        $align = in_array($align, ['left', 'center', 'right'], true) ? $align : 'center';

        return self::stripAttributes($img, [
            'src' => $src,
            'alt' => mb_substr(trim($img->getAttribute('alt')), 0, 150),
            'data-width' => (string) $width,
            'data-align' => $align,
            'style' => 'width: '.$width.'%',
            'class' => 'news-img news-img-'.$align,
            'loading' => 'lazy',
        ]);
    }
}
