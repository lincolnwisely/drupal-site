<?php

namespace Drupal\crossword;

use Drupal\Core\Plugin\PluginBase;
use Drupal\file\Entity\File;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\file\FileInterface;
use Masterminds\HTML5\Parser\UTF8Utils;

/**
 * Base class for Crossword File Parser Plugins.
 */
abstract class CrosswordFileParserPluginBase extends PluginBase implements CrosswordFileParserPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Cache for the result of the parse function.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;


  /**
   * The file entity that hopefully represents a crossword.
   *
   * @var \Drupal\file\FileInterface
   */
  protected $file;

  /**
   * The contents of the file.
   *
   * @var string
   */
  protected $contents;

  /**
   * Create a plugin with the given input.
   *
   * @param string $configuration
   *   The configuration of the plugin.
   * @param string $plugin_id
   *   The plugin id.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache service.
   *
   * @throws \Exception
   */
  public function __construct($configuration, $plugin_id, array $plugin_definition, CacheBackendInterface $cache) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->file = File::load($configuration['fid']);
    if (!static::isApplicable($this->file)) {
      throw new \Exception('Chosen crossword file parser cannot parse this file.');
    }
    $this->cache = $cache;
    $this->contents = file_get_contents($this->file->getFileUri());
    $this->contents = trim($this->contents);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('cache.crossword')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FileInterface $file) {
    return FALSE;
  }

  /**
   * Returns the data array representing the parsed crossword file.
   *
   * Plugins that extend this base should have their own getData() function.
   * The parse function is final so that caching and the data alter hook
   * are standardized.
   *
   * @return array
   *   An associative array that represents the crossword.
   */
  final public function parse() {

    $cached = $this->cache->get($this->file->id());
    if (isset($cached->data['data'])) {
      $data = $cached->data['data'];
    }
    else {
      $data = $this->getData();
      \Drupal::moduleHandler()->alter('crossword_data', $data, $this->file);
      $data = $this->convertCrosswordDataToUtf8($data);
      $this->cache->set($this->file->id(), ["data" => $data], CacheBackendInterface::CACHE_PERMANENT, $this->file->getCacheTags());
    }
    return $data;
  }

  /**
   * Returns the data array representing the parsed crossword file.
   *
   * This is different from the parse() function in that this function does
   * not interact with the cache or invoke any hooks.
   *
   * The "data" array is what the field formatter ends up using. If you extend
   * this base class, you definitely need to override this function.
   *
   * See crossword/tests/files/test.json for example json.
   *
   * Array(
   *   'id' => $this->file->id(),
   *   'title' => 'Awesome Puzzle',
   *   'author' => 'Dan',
   *   'notepad' => 'These are notes included in the file',
   *   'puzzle' => [
   *     'grid' => array of squares
   *     'clues' => [
   *       'across' => array of clues
   *       'down' => array of clues
   *     ],
   *   ],
   * )
   *
   * A square looks like this...
   * array(
   *  'fill' => NULL or a string,
   *  'numeral' => NULL or a number,
   *  'across' => [
   *    'index' => index of across clue
   *  ],
   *  'down' => [
   *    'index' => index of down clue
   *  ],
   *  'moves' => [
   *    'up' => ['row': number, 'col': number] or NULL
   *    'down' => ['row': number, 'col': number] or NULL
   *    'left' => ['row': number, 'col': number] or NULL
   *    'right' => ['row': number, 'col': number] or NULL
   *  ],
   *  'circle' => bool,
   *  'rebus' => bool,
   * )
   *
   * A clue looks like this...
   * array(
   *  'text' => string,
   *  'numeral' => number,
   *  'references' => array(
   *    [
   *      'dir' => 'down' or 'across',
   *      'numeral' => number,
   *      'index' => number,
   *    ],
   *   )
   * )
   *
   * @return array
   *   An associative array that represents the crossword.
   */
  protected function getData() {
    return [
      'id' => $this->file->id(),
      'title' => NULL,
      'author' => NULL,
      'notepad' => NULL,
      'puzzle' => NULL,
    ];
  }

  /**
   * Convert text endcoding to UTF-8 so that data works with drupalSettings.
   *
   * If a string not utf-8 encoded, then some special characters can make
   * json_encode() return FALSE. That causes problems if data is being passed
   * to drupalSettings as happens in the CrosswordFormatter plugin.
   *
   * @param array $data
   *   Associative array that represents the crossword with unknown encoding.
   *
   * @return array
   *   Associative array that represents the crossword with text UTF-8 encoded.
   *
   * @see https://www.drupal.org/project/crossword/issues/3102647
   */
  public function convertCrosswordDataToUtf8(array &$data) {
    $encodings_array = [
      'UTF-8',
      'Windows-1252',
      'ISO-8859-1',
    ];
    $encodings_string = implode(', ', $encodings_array);
    $encoding = mb_detect_encoding($data['title'], $encodings_string);
    $data['title'] = UTF8Utils::convertToUTF8($data['title'], $encoding);
    $encoding = mb_detect_encoding($data['author'], $encodings_string);
    $data['author'] = UTF8Utils::convertToUTF8($data['author'], $encoding);
    $encoding = mb_detect_encoding($data['notepad'], $encodings_string);
    $data['notepad'] = UTF8Utils::convertToUTF8($data['notepad'], $encoding);
    foreach ($data['puzzle']['grid'] as $row_index => $row) {
      foreach ($row as $col_index => $square) {
        if (!empty($square['fill'])) {
          $encoding = mb_detect_encoding($square['fill'], $encodings_string);
          $data['puzzle']['grid'][$row_index][$col_index]['fill'] = UTF8Utils::convertToUTF8($square['fill'], $encoding);
        }
      }
    }
    foreach ($data['puzzle']['clues']['across'] as &$clue) {
      $encoding = mb_detect_encoding($clue['text'], $encodings_string);
      $clue['text'] = UTF8Utils::convertToUTF8($clue['text'], $encoding);
    }
    foreach ($data['puzzle']['clues']['down'] as &$clue) {
      $encoding = mb_detect_encoding($clue['text'], $encodings_string);
      $clue['text'] = UTF8Utils::convertToUTF8($clue['text'], $encoding);
    }
    return $data;
  }

  /**
   * Returns an array representing clues referenced in the input text.
   *
   * If the text of a clue is something like "Common feature of 12- and
   * 57-Across and 34-Down", the return value will be:
   *
   * Array(
   *  [
   *   'dir' => 'across',
   *   'numeral' => 12,
   *  ],
   *  [
   *   'dir' => 'across',
   *   'numeral' => 57,
   *  ],
   *  [
   *   'dir' => 'down',
   *   'numeral' => 34,
   *  ],
   * )
   *
   * @param string $text
   *   The text of the clue to be parsed for references.
   *
   * @return array
   *   An array representing any clues to which a reference was found in $text.
   */
  protected function findReferences($text) {
    // Find references.
    $refRegex = '/(\d+\-)|(Down)|(Across)/';
    if (preg_match('/(\d+\-)/', $text) === 1 && preg_match('/(Across)|(Down)/', $text) === 1) {
      // there's likely a reference.
      $matches = [];
      $references = [];
      preg_match_all($refRegex, $text, $matches);
      // Something like [13- , 23- , Across, 45-, Down].
      $matches = $matches[0];
      $across_index = array_search("Across", $matches);
      $down_index = array_search("Down", $matches);

      if ($across_index === FALSE) {
        // Just down references.
        $i = 0;
        while ($i < $down_index) {
          $ref_num = str_replace("-", "", $matches[$i]);
          $references[] = [
            'dir' => 'down',
            'numeral' => $ref_num,
          ];
          $i++;
        }
      }
      if ($down_index === FALSE) {
        // Just across references.
        $i = 0;
        while ($i < $across_index) {
          $ref_num = str_replace("-", "", $matches[$i]);
          $references[] = [
            'dir' => 'across',
            'numeral' => $ref_num,
          ];
          $i++;
        }
      }
      if ($across_index > -1 && $down_index > -1) {
        // Assume Across references are first, as they should be
        // across.
        $i = 0;
        while ($i < $across_index) {
          $ref_num = str_replace("-", "", $matches[$i]);
          $references[] = [
            'dir' => 'across',
            'numeral' => $ref_num,
          ];
          $i++;
        }
        // Now down. We have to move past the acrossIndex.
        $i = $across_index + 1;
        while ($i < $down_index) {
          $ref_num = str_replace("-", "", $matches[$i]);
          $references[] = [
            'dir' => 'down',
            'numeral' => $ref_num,
          ];
          $i++;
        }
      }
      return $references;
    }
  }

  /**
   * Add index values to references contained in the clues array.
   *
   * $clues is the 'clues' element of $data, as described above the detData()
   * function. When passed to this function the clues should be fully created
   * other than the index element of any references.
   */
  protected function addIndexToClueReferences(&$clues) {
    foreach ($clues['down'] as &$down_clue) {
      if (!empty($down_clue['references'])) {
        foreach ($down_clue['references'] as &$reference) {
          foreach ($clues[$reference['dir']] as $index => $clue) {
            if ($clue['numeral'] == $reference['numeral']) {
              $reference['index'] = $index;
              break;
            }
          }
        }
      }
    }
    foreach ($clues['across'] as &$across_clue) {
      if (!empty($across_clue['references'])) {
        foreach ($across_clue['references'] as &$reference) {
          foreach ($clues[$reference['dir']] as $index => $clue) {
            if ($clue['numeral'] == $reference['numeral']) {
              $reference['index'] = $index;
              break;
            }
          }
        }
      }
    }
  }

  /**
   * Adds a 'moves' element to all the squares in the grid.
   *
   * This tells the arrow keys what to do when the puzzle is rendered.
   * By default, arrow keys won't move through black squares they get stopped
   * by the edges of the puzzle. If you want to modify this UX, the best
   * way may be to leverage hook_crossword_data_alter().
   */
  protected function addSquareMoves(&$grid) {
    foreach ($grid as $row_index => $row) {
      foreach ($row as $col_index => $square) {
        $grid[$row_index][$col_index]['moves'] = [
          'up' => NULL,
          'down' => NULL,
          'left' => NULL,
          'right' => NULL,
        ];
        // Up.
        if (isset($grid[$row_index - 1][$col_index]['fill'])) {
          $grid[$row_index][$col_index]['moves']['up'] = [
            'row' => $row_index - 1,
            'col' => $col_index,
          ];
        }
        // Down.
        if (isset($grid[$row_index + 1][$col_index]['fill'])) {
          $grid[$row_index][$col_index]['moves']['down'] = [
            'row' => $row_index + 1,
            'col' => $col_index,
          ];
        }
        // Left.
        if (isset($grid[$row_index][$col_index - 1]['fill'])) {
          $grid[$row_index][$col_index]['moves']['left'] = [
            'row' => $row_index,
            'col' => $col_index - 1,
          ];
        }
        // Right.
        if (isset($grid[$row_index][$col_index + 1]['fill'])) {
          $grid[$row_index][$col_index]['moves']['right'] = [
            'row' => $row_index,
            'col' => $col_index + 1,
          ];
        }
      }
    }
  }

}
