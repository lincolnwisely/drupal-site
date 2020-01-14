<?php

namespace Drupal\Tests\crossword\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;

/**
 * Tests basic functionality of crossword formatter.
 *
 * @group crossword
 */
class CrosswordFormatterTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = "classy";

  /**
   * {@inheritdoc}
   */
  public static $modules = ['crossword_tests'];

  /**
   * Test the crossword Field Formatter plugin.
   */
  public function testCrosswordFormatter() {
    $node = $this->createTestNode();
    $this->assertEqual(1, $node->id());

    // View the crossword node.
    $this->drupalGet('node/1');
    $assertSession = $this->assertSession();
    $session = $this->getSession();
    $page = $session->getPage();

    // Check for presence of various test.
    $assertSession->elementTextContains('css', '.crossword-title', "Test Puzzle");
    $assertSession->elementTextContains('css', '.crossword-author', "Test Author");

    // Check initial active clue.
    $session->wait(100);
    $assertSession->elementTextContains('css', '#active-clues', "3A Second square has a circle");

    // Click on bottom left square. Check that active clues update.
    $square = $page->find('css', '[data-col="0"][data-row="2"]');
    $input = $page->find('css', '[data-col="0"][data-row="2"] input');
    $clue_across = $page->find('css', '.crossword-clue[data-clue-index-across="1"]');
    $clue_down = $page->find('css', '.crossword-clue[data-clue-index-down="0"]');
    $square->click();
    $session->wait(100);
    $this->assertTrue($square->hasClass('active'));
    $this->assertTrue($clue_across->hasClass('active'));
    $this->assertFalse($clue_down->hasClass('active'));
    $assertSession->elementTextContains('css', '#active-clues', "5A Has a reference to 3-Across and 1-Down");
    $assertSession->elementTextContains('css', '#active-clues', "3A Second square has a circle");
    $assertSession->elementTextContains('css', '#active-clues', "1D is AB2");

    // Uncheck references. Make sure active clues update.
    $page->find('css', '#show-references')->click();
    $session->wait(100);
    $assertSession->elementTextContains('css', '#active-clues', "5A Has a reference to 3-Across and 1-Down");
    $assertSession->elementTextNotContains('css', '#active-clues', "3A Second square has a circle");
    $assertSession->elementTextNotContains('css', '#active-clues', "1D is AB2");

    // Click on bottom left square again. Check that direction toggles.
    $square->click();
    $session->wait(100);
    $this->assertTrue($square->hasClass('active'));
    $this->assertFalse($clue_across->hasClass('active'));
    $this->assertTrue($clue_down->hasClass('active'));
    $assertSession->elementTextNotContains('css', '#active-clues', "5A Has a reference to 3-Across and 1-Down");
    $assertSession->elementTextContains('css', '#active-clues', "1D is AB2");

    // Check that errors work on squares and clues while testing rebus.
    $this->assertFalse($square->hasClass('error'));
    $this->assertFalse($clue_across->hasClass('error'));
    $square->click();
    $session->wait(100);
    $input->setValue('b');
    $session->wait(100);
    $this->assertTrue($square->hasClass('error'));
    $this->assertTrue($clue_across->hasClass('error'));
    // Go back.
    $square->click();
    $input->setValue('T');
    $session->wait(100);
    $this->assertTrue($square->hasClass('error'));
    $this->assertTrue($clue_across->hasClass('error'));
    $input->setValue('W');
    $session->wait(100);
    $this->assertTrue($square->hasClass('error'));
    $this->assertTrue($clue_across->hasClass('error'));
    $input->setValue('O');
    $session->wait(100);
    $this->assertFalse($square->hasClass('error'));
    $this->assertFalse($clue_across->hasClass('error'));

    // Check that some buttons work.
    // Previous across clue button.
    $page->find('css', '.crossword-clue-change.prev')->click();
    $session->wait(100);
    $new_active_square = $page->find('css', '[data-col="0"][data-row="1"]');
    $new_active_clue = $page->find('css', '[data-clue-index-across="0"]');
    $this->assertTrue($new_active_square->hasClass('active'));
    $this->assertTrue($new_active_clue->hasClass('active'));
    $this->assertFalse($square->hasClass('active'));
    $this->assertFalse($clue_across->hasClass('active'));

    // Cheat button.
    $page->find('css', '.button-cheat')->click();
    $session->wait(100);
    $this->assertTrue($new_active_square->hasClass('cheat'));
    $this->assertTrue($new_active_clue->hasClass('cheat'));
    $assertSession->elementTextContains('css', '[data-col="0"][data-row="1"] .square-fill', 'B');

    // Check out undo and redo. First cheat one more time.
    $page->find('css', '.button-cheat')->click();
    $session->wait(100);
    $assertSession->elementTextContains('css', '[data-col="1"][data-row="1"] .square-fill', 'C');
    $assertSession->elementTextContains('css', '[data-col="0"][data-row="1"] .square-fill', 'B');
    // Now undo.
    $page->find('css', '.button-undo')->click();
    $session->wait(100);
    $assertSession->elementTextNotContains('css', '[data-col="1"][data-row="1"] .square-fill', 'C');
    $assertSession->elementTextContains('css', '[data-col="0"][data-row="1"] .square-fill', 'B');
    // Now undo again.
    $page->find('css', '.button-undo')->click();
    $session->wait(100);
    $assertSession->elementTextNotContains('css', '[data-col="1"][data-row="1"] .square-fill', 'C');
    $assertSession->elementTextNotContains('css', '[data-col="0"][data-row="1"] .square-fill', 'B');
    // Now redo.
    $page->find('css', '.button-redo')->click();
    $session->wait(100);
    $assertSession->elementTextNotContains('css', '[data-col="1"][data-row="1"] .square-fill', 'C');
    $assertSession->elementTextContains('css', '[data-col="0"][data-row="1"] .square-fill', 'B');
    // Now redo again.
    $page->find('css', '.button-redo')->click();
    $session->wait(100);
    $assertSession->elementTextContains('css', '[data-col="1"][data-row="1"] .square-fill', 'C');
    $assertSession->elementTextContains('css', '[data-col="0"][data-row="1"] .square-fill', 'B');

    // Check that local storage works.
    // Reload the page.
    $this->drupalGet('node/1');
    $assertSession = $this->assertSession();
    $session = $this->getSession();
    $page = $session->getPage();
    $session->wait(100);
    $assertSession->elementTextNotContains('css', '[data-col="0"][data-row="0"] .square-fill', 'A');
    $assertSession->elementTextNotContains('css', '[data-col="2"][data-row="0"] .square-fill', 'ONE');
    $assertSession->elementTextContains('css', '[data-col="0"][data-row="1"] .square-fill', 'B');
    $assertSession->elementTextContains('css', '[data-col="1"][data-row="1"] .square-fill', 'C');
    $assertSession->elementTextNotContains('css', '[data-col="2"][data-row="1"] .square-fill', 'D');
    $assertSession->elementTextContains('css', '[data-col="0"][data-row="2"] .square-fill', 'TWO');
    $assertSession->elementTextNotContains('css', '[data-col="1"][data-row="2"] .square-fill', 'E');
    $assertSession->elementTextNotContains('css', '[data-col="2"][data-row="2"] .square-fill', 'F');
  }

  /**
   * Helper function to create node that can be viewed and used for testing.
   *
   * @return Drupal\node\Entity\Node
   *   A crossword node that can be used in tests.
   */
  protected function createTestNode() {
    // First we move a test file to the file system.
    $contents = file_get_contents(drupal_get_path('module', 'crossword') . "/tests/files/test.txt");
    $file = file_save_data($contents, "public://test.txt");
    // Now use that file in a new crossword node.
    $node = Node::create(['type' => 'crossword']);
    $node->set('title', 'Test Crossword Node');
    $node->set('field_crossword', $file->id());
    $node->set('status', 1);
    $node->save();
    return $node;
  }

}
