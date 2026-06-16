<?php declare(strict_types=1);

namespace PresProg\Legaltext;

use Kirby\Cms\App;
use Kirby\Content\Field;

load([
  Legaltext::class => 'classes/Legaltext.php',
], __DIR__);

App::plugin('presprog/legaltext', [
  'fieldMethods' => [
    'legaltext' => function (Field $field): string {
      return Legaltext::createFromHtml((string)$field->kirbytext())->restoreTocIds();
    },
  ],
]);
