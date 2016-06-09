<?php

/**
 * @file
 * Contains \Drupal\photos\Plugin\Block\PhotosUserImages.
 */

namespace Drupal\photos\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Photos user images' block.
 *
 * @Block(
 *   id = "photos_user_images",
 *   admin_label = @Translation("User's Images"),
 *   category = @Translation("Photos")
 * )
 */
class PhotosUserImages extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Retrieve existing configuration for this block.
    // @todo migrate variables to block configuration.
    $config = $this->getConfiguration();
    $count = isset($config['image_count']) ? $config['image_count'] : 10;

    // Check current path for args to find uid.
    $current_path = \Drupal::service('path.current')->getPath();
    $arg = explode('/', $current_path);
    if (isset($arg[2])) {
      if ($arg[1] == 'photos' && isset($arg[3])) {
        switch ($arg[2]) {
          case 'image':
            $uid = db_query('SELECT uid FROM {file_managed} WHERE fid = :uid',
              array(':uid' => $arg[3]))->fetchField();
          break;
          case 'user':
            $uid = $arg[3];
        }
      }
      if ($arg[1] == 'node' && is_numeric($arg[2])) {
        $uid = db_query('SELECT uid FROM {node_field_data} WHERE nid = :nid',
          array(':nid' => $arg[2]))->fetchField();
      }
    }
    if (!isset($uid)) {
      $user = \Drupal::currentUser();
      $uid = $user->id();
    }
    if ($uid && ($block_info = _photos_block_image('user', $count, 'photos/user/' . $uid . '/image', $uid))) {
      return array(
        '#markup' => $block_info['content'],
        '#title' => $block_info['title']
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    // Retrieve existing configuration for this block.
    $config = $this->getConfiguration();

    // Add a form field to the existing block configuration form.
    $options = array_combine(
      array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 15, 20, 25, 30, 40),
      array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 15, 20, 25, 30, 40)
    );
    $form['image_count'] = array(
      '#type' => 'select',
      '#title' => t('Number of images to display'),
      '#options' => $options,
      '#default_value' => isset($config['image_count']) ? $config['image_count'] : '',
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
      // Save our custom settings when the form is submitted.
    $this->setConfigurationValue('image_count', $form_state->getValue('image_count'));
  }

}
