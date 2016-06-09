<?php

/**
 * @file
 * Contains \Drupal\photos_access\Form\PhotosAccessAdminSettings.
 */

namespace Drupal\photos_access\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class PhotosAccessAdminSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'photos_access_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('photos_access.settings');

    foreach (Element::children($form['privacy']) as $variable) {
      $value = $form_state->getValue($form['privacy'][$variable]['#parents']);
      $config->set($variable, $value);
    }
    $config->save();

    if (method_exists($this, '_submitForm')) {
      $this->_submitForm($form, $form_state);
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['photos_access.settings'];
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $config = $this->config('photos_access.settings');

    $form['privacy'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => t('Privacy settings'),
      '#description' => t('Enabled photos access privacy settings for the following content types.'),
    ];
    $types = \Drupal\node\Entity\NodeType::loadMultiple();
    foreach ($types as $type) {
      $form['privacy']['photos_access_' . $type->id()] = array(
        '#title' => $type->label(),
        '#type' => 'checkbox',
        '#default_value' => $config->get('photos_access_' . $type->id()),
      );
    }

    return parent::buildForm($form, $form_state);
  }

}
