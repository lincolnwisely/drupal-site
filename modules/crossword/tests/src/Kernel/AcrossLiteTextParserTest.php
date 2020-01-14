<?php

namespace Drupal\Tests\crossword\Kernel;

/**
 * Tests the Across Lite Text parser plugin.
 *
 * @group crossword
 */
class AcrossLiteTextParserTest extends CrosswordFileParserPluginTestBase {

  /**
   * {@inheritdoc}
   */
  public $pluginId = 'across_lite_text';

  /**
   * {@inheritdoc}
   */
  public $class = 'Drupal\crossword\Plugin\crossword\crossword_file_parser\AcrossLiteTextParser';

  /**
   * {@inheritdoc}
   */
  public $filename = [
    'success' => 'test.txt',
    'failure' => 'failure.txt',
  ];

}
