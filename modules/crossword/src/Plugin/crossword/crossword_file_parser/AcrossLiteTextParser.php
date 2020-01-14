<?php

namespace Drupal\crossword\Plugin\crossword\crossword_file_parser;

use Drupal\crossword\CrosswordFileParserPluginBase;
use Drupal\file\FileInterface;

/**
 * Plugin for parsing files in the Across Lite Text format v1 or v2.
 *
 * @CrosswordFileParser(
 *   id = "across_lite_text",
 *   title = @Translation("Across Lite Text")
 * )
 */
class AcrossLiteTextParser extends CrosswordFileParserPluginBase {

  /**
   * {@inheritdoc}
   *
   * Checks for missing tags, extra tags, out of order tags.
   */
  public static function isApplicable(FileInterface $file) {

    if ($file->getMimeType() !== "text/plain") {
      return FALSE;
    }

    $contents = file_get_contents($file->getFileUri());
    $contents = trim($contents);

    $missing_tags = [];
    $extra_tags = [];
    $required_tags = [
      "<GRID>",
      "<ACROSS>",
      "<DOWN>",
    ];
    $expected_order = [
      "<TITLE>",
      "<AUTHOR>",
      "<COPYRIGHT>",
      "<SIZE>",
      "<GRID>",
      "<REBUS>",
      "<ACROSS>",
      "<DOWN>",
      "<NOTEPAD>",
    ];

    $matches = [];
    preg_match_all("/<[A-Z]+?>/", $contents, $matches);
    // Note this does not match <ACROSS PUZZLE>, a worthless tag really.
    $actual_tags = $matches[0];

    foreach ($required_tags as $tag) {
      if (array_search($tag, $actual_tags) === FALSE) {
        // $missing_tags[] = $tag;.
        return FALSE;
      }
    }
    foreach ($actual_tags as $tag) {
      if (array_search($tag, $expected_order) === FALSE) {
        return FALSE;
      }
    }
    if (!empty($missing_tags) || !empty($extra_tags)) {
      return [
        'missing' => $missing_tags,
        'extra' => $extra_tags,
      ];
    }

    $relevant_tags = [];
    foreach ($expected_order as $tag) {
      if (array_search($tag, $actual_tags) > -1) {
        $relevant_tags[] = $tag;
      }
    }
    foreach ($relevant_tags as $index => $tag) {
      if ($relevant_tags[$index] != $actual_tags[$index]) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function getData() {

    $lines = explode("\n", $this->contents);
    foreach ($lines as &$line) {
      // @see https://www.drupal.org/project/crossword/issues/3102646
      $line = trim($line);
    }
    $data = [
      'id' => $this->file->id(),
      'title' => $this->getTitle($lines),
      'author' => $this->getAuthor($lines),
      'notepad' => $this->getNotepad($lines),
      'puzzle' => $this->getGridAndClues($lines),
    ];

    $this->addIndexToClueReferences($data['puzzle']['clues']);
    $this->addSquareMoves($data['puzzle']['grid']);

    return $data;
  }

  /**
   * Returns title of crossword.
   *
   * @param array $lines
   *   The contents of the file where each array element is a line of the file.
   *
   * @return string
   *   The title of the puzzle
   */
  public function getTitle(array $lines) {
    $title = $lines[array_search("<TITLE>", $lines) + 1];
    return trim($title);
  }

  /**
   * Returns author of crossword.
   *
   * @param array $lines
   *   The contents of the file where each array element is a line of the file.
   *
   * @return string
   *   The author of the puzzle
   */
  public function getAuthor(array $lines) {
    $author = $lines[array_search("<AUTHOR>", $lines) + 1];
    return trim($author);
  }

  /**
   * Returns notepad from crossword.
   *
   * @param array $lines
   *   The contents of the file where each array element is a line of the file.
   *
   * @return string
   *   The notepad of the puzzle
   */
  public function getNotepad(array $lines) {
    $notepad_index = strpos($this->contents, "<NOTEPAD>");
    if ($notepad_index > -1) {
      $notepad = substr($this->contents, $notepad_index + 10);
      return trim($notepad);
    }
  }

  /**
   * Returns grid and clues.
   *
   * When returns, the squares don't have moves and the references
   * don't have the index added yet.
   *
   * @param array $lines
   *   The contents of the file where each array element is a line of the file.
   *
   * @return array
   *   Associative array containing nearly fully parsed grid and clues.
   */
  public function getGridAndClues(array $lines) {
    $grid = [];
    $clues = [
      'across' => [],
      'down' => [],
    ];

    $raw_clues = $this->getRawClues($lines);
    $raw_grid = $this->getRawGrid($lines);

    $iterator = [
      'index_across' => -1,
      'index_down' => -1,
      'numeral' => 0,
    ];

    $rebus_array = $this->getRebusArray($lines);

    foreach ($raw_grid as $row_index => $raw_row) {
      $row = [];
      for ($col_index = 0; $col_index < count($raw_row); $col_index++) {
        $fill = $raw_row[$col_index];
        // In text, lowercase letters inidcate a circle.
        $circle = $fill && strtoupper($fill) != $fill;
        $square = [
          'row' => $row_index,
          'col' => $col_index,
          'circle' => $circle,
          'rebus' => FALSE,
          'fill' => $fill === NULL ? NULL : strtoupper($fill),
        ];
        if ($fill !== NULL) {
          // Init some things to NULL.
          $numeral_incremented = FALSE;
          $numeral = NULL;
          /*
          This will be the first square in an across clue if it is...
          1. the left square or to the right of a black
          AND
          2. not the right square and the square to its right is not black.
           */
          if ($col_index == 0 || $raw_row[$col_index - 1] === NULL) {
            if (isset($raw_row[$col_index + 1]) && $raw_row[$col_index + 1] !== NULL) {
              $iterator['index_across']++;
              $iterator['numeral']++;
              $numeral = $iterator['numeral'];
              $clues['across'][] = [
                'text' => $raw_clues['across'][$iterator['index_across']],
                'numeral' => $iterator['numeral'],
                'references' => $this->findReferences($raw_clues['across'][$iterator['index_across']]),
              ];
              $numeral_incremented = TRUE;

              $square['across'] = [
                'index' => $iterator['index_across'],
              ];
              $square['numeral'] = $numeral;
            }
            else {
              // In here? It's an uncrosswed square. No across clue. No numeral.
            }
          }
          else {
            // In here? No numeral.
            $square['across'] = [
              'index' => $iterator['index_across'],
            ];
          }

          /*
          This will be the first square in a down clue if...
          1. It's the top square or the below a black
          AND
          2. It's not the bottom square and the square below it is not black.
           */
          if ($row_index == 0 || $raw_grid[$row_index - 1][$col_index] === NULL) {
            if (isset($raw_grid[$row_index + 1][$col_index]) && $raw_grid[$row_index + 1][$col_index] !== NULL) {
              $iterator['index_down']++;
              if (!$numeral_incremented) {
                $iterator['numeral']++;
              }
              $numeral = $iterator['numeral'];
              $clues['down'][] = [
                'text' => $raw_clues['down'][$iterator['index_down']],
                'numeral' => $iterator['numeral'],
                'references' => $this->findReferences($raw_clues['down'][$iterator['index_down']]),
              ];
              $numeral_incremented = TRUE;

              $square['down'] = [
                'index' => $iterator['index_down'],
              ];
              $square['numeral'] = $numeral;
            }
            else {
              // In here? It's an uncrosswed square. No down clue. No numeral.
            }
          }
          else {
            // In here? No numeral. Take the down value from the square above.
            $square['down'] = $grid[$row_index - 1][$col_index]['down'];
          }
        }

        // Is it a rebus square?
        if (is_numeric($square['fill']) && !empty($rebus_array) && isset($rebus_array[$square['fill']])) {
          $square['fill'] = $rebus_array[$square['fill']];
          $square['rebus'] = TRUE;
        }

        $row[] = $square;
      }
      $grid[] = $row;
    }

    return [
      'grid' => $grid,
      'clues' => $clues,
    ];
  }

  /**
   * Returns an array of arrays of clue text.
   *
   * @param array $lines
   *   The contents of the file where each array element is a line of the file.
   *
   * @return array
   *   Associative array containing an array of across clue text and an array
   *   of down clue text.
   */
  public function getRawClues(array $lines) {
    $across_clues_start_index = array_search("<ACROSS>", $lines) + 1;
    $down_clues_start_index = array_search("<DOWN>", $lines) + 1;

    $across_clues_number = $down_clues_start_index - $across_clues_start_index - 1;
    $across_clues = array_slice($lines, $across_clues_start_index, $across_clues_number);

    if (array_search("<NOTEPAD>", $lines) > -1) {
      $down_clues_number = array_search("<NOTEPAD>", $lines) - $down_clues_start_index;
      $down_clues = array_slice($lines, $down_clues_start_index, $down_clues_number);
    }
    else {
      $down_clues = array_slice($lines, $down_clues_start_index);
    }

    return [
      'across' => $across_clues,
      'down' => $down_clues,
    ];
  }

  /**
   * Returns a 2D array where each element is the text of a square.
   *
   * @param array $lines
   *   The contents of the file where each array element is a line of the file.
   *
   * @return array
   *   2D array where each element is the text of a square.
   */
  public function getRawGrid(array $lines) {
    $raw_grid = [];

    $grid_start_index = array_search("<GRID>", $lines) + 1;
    $after_grid_index = array_search("<REBUS>", $lines) > -1 ? array_search("<REBUS>", $lines) : array_search("<ACROSS>", $lines);
    $number_of_rows = $after_grid_index - $grid_start_index;
    $grid_lines = array_slice($lines, $grid_start_index, $number_of_rows);

    foreach ($grid_lines as $row_index => $grid_line) {
      for ($col_index = 0; $col_index < strlen($grid_line); $col_index++) {
        $raw_grid[$row_index][] = $grid_line[$col_index] !== "." ? $grid_line[$col_index] : NULL;
      }
    }
    return $raw_grid;
  }

  /**
   * Returns array used to handle rebus puzzles.
   *
   * @param array $lines
   *   The contents of the file where each array element is a line of the file.
   *
   * @return array
   *   An associative array. The key is a number. The corresponding value
   *   is the text that should replace the number any time it appears in the
   *   grid.
   */
  private function getRebusArray(array $lines) {
    if (array_search("<REBUS>", $lines) > -1) {
      $rebus_start_index = array_search("<REBUS>", $lines) + 1;
      $rebus_array = [];
      $i = $rebus_start_index;
      while ($lines[$i] != "<ACROSS>") {
        $line = explode(':', $lines[$i]);
        if (is_numeric($line[0]) && isset($line[1])) {
          $rebus_array[$line[0]] = $line[1];
        }
        $i++;
      }
      return $rebus_array;
    }
  }

}
