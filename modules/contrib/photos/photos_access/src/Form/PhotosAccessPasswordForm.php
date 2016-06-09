<?php

/**
 * @file
 * Contains \Drupal\photos_access\Form\PhotosAccessPasswordForm.
 */

namespace Drupal\photos_access\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Defines a form to upload photos to this site.
 */
class PhotosAccessPasswordForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'photos_access_password';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL) {
    $form['pass'] = array(
      '#type' => 'password',
      '#title' => t('Please enter album password'),
      '#attributes' => array(
        'autocomplete' => 'off'
      )
    );
    $form['nid'] = array(
      '#type' => 'value',
      '#value' => $node->id(),
    );
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Submit'),
    );

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $user = \Drupal::currentUser();
    $node = db_query("SELECT pass, nid FROM {photos_access_album} WHERE nid = :nid AND pass = :pass",
      array(
        ':nid' => $form_state->getValue('nid'),
        ':pass' => md5($form_state->getValue('pass'))
      )
    )->fetchObject();
    if (isset($node->pass)) {
      $_SESSION[$node->nid . '_' . session_id()] = $node->pass;

      // Redirect.
      $redirect_url = Url::fromUri('base:node/' . $node->nid)->toString();
      return new RedirectResponse($redirect_url);
    }
    else {
      $form_state->setErrorByName('pass', t('Password required'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // ...
  }

}
