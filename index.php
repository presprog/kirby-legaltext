<?php

namespace PresProg\Legaltext;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Kirby\Cms\App;
use Kirby\Content\Field;

final class Legaltext
{
  private const ROOT_ID = 'legaltext-root';

  private function __construct(private string $html)
  {
  }

  public static function createFromHtml(string $html): self
  {
    return new self($html);
  }

  public function restoreTocIds(): string
  {
    if (trim($this->html) === '' || str_contains($this->html, 'href="#') === false) {
      return $this->html;
    }

    $document = $this->createDocument();

    if ($document === null) {
      return $this->html;
    }

    $xpath = new DOMXPath($document);
    $root = $document->getElementById(self::ROOT_ID);

    if ($root === null) {
      return $this->html;
    }

    $tocHeading = null;
    foreach ($xpath->query('.//*[self::h2 or self::h3]', $root) as $heading) {
      if ($this->normalizeAnchorText($heading->textContent) === 'table of contents') {
        $tocHeading = $heading;
        break;
      }
    }

    if ($tocHeading === null) {
      return $this->html;
    }

    $tocEntries = [];
    for ($node = $tocHeading->nextSibling; $node !== null; $node = $node->nextSibling) {
      if ($this->isHeadingElement($node) === true) {
        break;
      }

      if ($node instanceof DOMElement === false) {
        continue;
      }

      foreach ($xpath->query('.//a[starts-with(@href, "#")]', $node) as $link) {
        $id = rawurldecode(substr($link->getAttribute('href'), 1));

        if ($this->isValidHtmlId($id) === false) {
          continue;
        }

        $tocEntries[] = [
          'id'   => $id,
          'text' => $this->normalizeAnchorText($link->textContent),
        ];
      }
    }

    if ($tocEntries === []) {
      return $this->html;
    }

    $headingsByText = [];
    $sameLevelHeadings = [];
    $tocLevel = (int)substr($tocHeading->tagName, 1);

    foreach ($xpath->query('.//*[self::h2 or self::h3 or self::h4 or self::h5 or self::h6]', $root) as $heading) {
      if ($heading->isSameNode($tocHeading) === true) {
        continue;
      }

      $text = $this->normalizeAnchorText($heading->textContent);
      $headingsByText[$text][] = $heading;

      if ((int)substr($heading->tagName, 1) === $tocLevel) {
        $sameLevelHeadings[] = $heading;
      }
    }

    foreach ($tocEntries as $entry) {
      foreach ($headingsByText[$entry['text']] ?? [] as $heading) {
        if ($heading->hasAttribute('id') === false && $this->idExists($xpath, $entry['id']) === false) {
          $heading->setAttribute('id', $entry['id']);
          continue 2;
        }
      }
    }

    foreach ($tocEntries as $index => $entry) {
      if ($this->idExists($xpath, $entry['id']) === true || isset($sameLevelHeadings[$index]) === false) {
        continue;
      }

      $heading = $sameLevelHeadings[$index];

      if ($heading->hasAttribute('id') === false) {
        $heading->setAttribute('id', $entry['id']);
      }
    }

    return $this->serializeChildren($document, $root);
  }

  private function createDocument(): ?DOMDocument
  {
    $document = new DOMDocument('1.0', 'UTF-8');

    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML(
      '<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="' . self::ROOT_ID . '">' . $this->html . '</div></body></html>',
      LIBXML_HTML_NODEFDTD | LIBXML_NONET
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if ($loaded === false) {
      return null;
    }

    return $document;
  }

  private function normalizeAnchorText(string $text): string
  {
    return strtolower(preg_replace('/\s+/', ' ', trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
  }

  private function idExists(DOMXPath $xpath, string $id): bool
  {
    return $xpath->query('//*[@id="' . htmlspecialchars($id, ENT_QUOTES | ENT_XML1, 'UTF-8') . '"]')->length > 0;
  }

  private function isHeadingElement(mixed $node, int $minLevel = 2, int $maxLevel = 6): bool
  {
    if ($node instanceof DOMElement === false) {
      return false;
    }

    return preg_match('/^h[' . $minLevel . '-' . $maxLevel . ']$/', strtolower($node->tagName)) === 1;
  }

  private function isValidHtmlId(string $id): bool
  {
    return preg_match('/^[A-Za-z][A-Za-z0-9_:.\\-]*$/', $id) === 1;
  }

  private function serializeChildren(DOMDocument $document, DOMElement $root): string
  {
    $output = '';

    foreach ($root->childNodes as $child) {
      $output .= $document->saveHTML($child);
    }

    return $output;
  }
}

App::plugin('presprog/legaltext', [
  'fieldMethods' => [
    /**
     * It renders the Writer content via kirbytext(), finds the generated “Table of contents”, reads its href="#..." targets, and restores those IDs onto matching headings in the rendered HTML.
     */
    'legaltext' => function (Field $field): string {
      return Legaltext::createFromHtml($field->kirbytext())->restoreTocIds();
    },
  ],
]);
