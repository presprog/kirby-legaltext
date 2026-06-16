<?php declare(strict_types=1);

namespace PresProg\Legaltext\Tests;

use PHPUnit\Framework\TestCase;
use PresProg\Legaltext\Legaltext;

final class LegaltextTest extends TestCase
{
  public function testRestoresTocIdsByMatchingHeadingText(): void
  {
    $html = <<<'HTML'
<h2>Table of contents</h2>
<ul>
  <li><a href="#privacy">Privacy</a></li>
</ul>
<h2>Privacy</h2>
HTML;

    $result = Legaltext::createFromHtml($html)->restoreTocIds();

    self::assertStringContainsString('<h2 id="privacy">Privacy</h2>', $result);
  }

  public function testDoesNotDuplicateExistingIds(): void
  {
    $html = <<<'HTML'
<h2>Table of contents</h2>
<ul>
  <li><a href="#privacy">Privacy</a></li>
</ul>
<h2 id="privacy">Privacy</h2>
HTML;

    $result = Legaltext::createFromHtml($html)->restoreTocIds();

    self::assertSame(1, substr_count($result, 'id="privacy"'));
  }

  public function testFallsBackToSameLevelHeadingPosition(): void
  {
    $html = <<<'HTML'
<h2>Table of contents</h2>
<ul>
  <li><a href="#foobar">Foobar</a></li>
  <li><a href="#privacy">Privacy</a></li>
</ul>
<h2>Foobar</h2>
<h2>Privacy policy</h2>
HTML;

    $result = Legaltext::createFromHtml($html)->restoreTocIds();

    self::assertStringContainsString('<h2 id="privacy">Privacy policy</h2>', $result);
  }
}
