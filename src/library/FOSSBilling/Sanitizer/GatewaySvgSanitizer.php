<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\Sanitizer;

/** Rebuild static SVG artwork with a restricted set of elements and attributes. */
final class GatewaySvgSanitizer
{
    private const string SVG_NAMESPACE = 'http://www.w3.org/2000/svg';
    private const array ELEMENTS = [
        'svg', 'g', 'defs', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'title', 'desc', 'linearGradient', 'radialGradient', 'stop', 'clipPath', 'mask', 'pattern',
    ];
    private const array ATTRIBUTES = [
        'id', 'x', 'y', 'x1', 'x2', 'y1', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'width', 'height',
        'viewBox', 'preserveAspectRatio', 'd', 'points', 'transform', 'opacity',
        'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-opacity',
        'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset',
        'clip-path', 'clip-rule', 'mask', 'maskUnits', 'maskContentUnits',
        'gradientUnits', 'gradientTransform', 'spreadMethod', 'offset', 'stop-color', 'stop-opacity',
        'patternUnits', 'patternContentUnits', 'patternTransform',
        'font-family', 'font-size', 'font-weight', 'font-style', 'text-anchor', 'dominant-baseline', 'dx', 'dy', 'color',
    ];
    private const array STYLE_PROPERTIES = [
        'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-opacity',
        'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset',
        'opacity', 'stop-color', 'stop-opacity', 'font-family', 'font-size', 'font-weight', 'font-style', 'text-anchor', 'color',
    ];

    public static function sanitize(string $bytes): string
    {
        $source = new \DOMDocument();
        if (!@$source->loadXML($bytes, LIBXML_NONET) || $source->doctype !== null
            || $source->documentElement?->localName !== 'svg'
            || !in_array($source->documentElement->namespaceURI, [null, self::SVG_NAMESPACE], true)) {
            throw new \FOSSBilling\Exception('Use a valid PNG, JPEG, WebP, AVIF, or SVG image.');
        }

        $clean = new \DOMDocument('1.0', 'UTF-8');
        $clean->appendChild(self::copyElement($source->documentElement, $clean, 0));

        return $clean->saveXML($clean->documentElement);
    }

    private static function copyElement(\DOMElement $source, \DOMDocument $document, int $depth): \DOMElement
    {
        if ($depth > 64) {
            throw new \FOSSBilling\Exception('SVG image is too deeply nested.');
        }
        $element = $document->createElementNS(self::SVG_NAMESPACE, $source->localName);
        foreach ($source->attributes as $attribute) {
            if ($attribute->namespaceURI !== null) {
                continue;
            }
            if (in_array($attribute->name, self::ATTRIBUTES, true) && self::isSafeValue($attribute->value)) {
                $element->setAttribute($attribute->name, $attribute->value);
            } elseif ($attribute->name === 'style') {
                $declarations = [];
                foreach (explode(';', $attribute->value) as $declaration) {
                    $parts = explode(':', $declaration, 2);
                    if (count($parts) === 2 && in_array(trim($parts[0]), self::STYLE_PROPERTIES, true) && self::isSafeValue(trim($parts[1]))) {
                        $declarations[] = trim($parts[0]) . ':' . trim($parts[1]);
                    }
                }
                $element->setAttribute('style', implode(';', $declarations));
            }
        }
        foreach ($source->childNodes as $child) {
            if ($child instanceof \DOMElement && in_array($child->localName, self::ELEMENTS, true)
                && in_array($child->namespaceURI, [null, self::SVG_NAMESPACE], true)) {
                $element->appendChild(self::copyElement($child, $document, $depth + 1));
            } elseif ($child instanceof \DOMText && in_array($source->localName, ['text', 'tspan', 'title', 'desc'], true)) {
                $element->appendChild($document->createTextNode($child->textContent));
            }
        }

        return $element;
    }

    private static function isSafeValue(string $value): bool
    {
        // Only local paint-server references are permitted. CSS escapes,
        // at-rules, external URLs, and arbitrary functions cannot survive.
        $value = preg_replace('/url\(\s*#[a-zA-Z_][a-zA-Z0-9_.-]*\s*\)/i', '', $value);
        $value = preg_replace('/(?:matrix|translate|scale|rotate|skewX|skewY|rgb|rgba|hsl|hsla)\([0-9\s.,%+\/-]*\)/i', '', $value);

        return preg_match('/\A[a-zA-Z0-9\s#.,%+\-]*\z/', $value) === 1;
    }
}
