<?php 
/**
   * @file
   * Contains \Drupal\rsvplist\Form\RSVPForm
 */
namespace Drupal\rsvplist\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\Formbase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
/**
   * Provides an RSVP Email Form
 */

class RSVPForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

   public function getFormId() {
    return 'rsvplist_email_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $node = \Drupal::routeMatch()->getParameter('node');
    $nid = $node->nid->value;
    $form['email'] = array(
      '#title' => t('Email Address'),
      '#type' => 'textfield',
      '#size' => 25,
      '#description' => t("We'll send updates to the email address you provide"),
      '#required' => TRUE,
    );
    $form['phone_number'] = array(
      '#title' => t('Phone number'),
      '#type' => 'tel',
      '#placeholder' => '314-555-5555'
    );
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('RSVP'),
    );
    $form['nid'] = array(
      '#type' => 'hidden',
      '#value' => $nid,
    );

    return $form;
  }
    /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $value = $form_state->getValue('email');
    if ($value == !\Drupal::service('email.validator')->isValid($value)) {
      $form_state->setErrorByName('email', t('The email address %mail is not valid.', array('%mail' => $value)));
      return;
    }
    $node = \Drupal::routeMatch()->getParameter('node');
    // Check if email already is set for this node
    $select = Database::getConnection()->select('rsvplist', 'r');
    $select->fields('r', array('nid'));
    $select->condition('nid', $node->id());
    $select->condition('mail', $value);
    $results = $select->execute();
    if(!empty($results->fetchCol())) {
      // We found a row with this nid and email.
      $form_state->setErrorByName('email', t('The address %mail is already subscribed to this list', array('%mail' => $value)));
    }
  }
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // drupal_set_message(t('The form is working!'));
    // var_dump($form_state->getValue('phone_number'));
    // prints string...
    // die;
    // 'phone_number' => $form_state->getValue('phone_number'),

    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());

      $this->database->insert('rsvplist')->fields(array(
        'mail' => $form_state->getValue('email'),
        'nid' => $form_state->getValue('nid'),
        'uid' => $user->id(),
        'created' => time(),
      ))
      ->execute();
      drupal_set_message(t('Thank you for your RSVP. You are on the list for the event! And lincoln is smart.'));
  }
 }