<?php

/**
 * @file
 * Contains \Drupal\photos\Form\PhotosImageSubAlbumForm.
 */

namespace Drupal\photos\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Defines a form to edit images.
 */
class PhotosImageSubAlbumForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'photos_image_sub_album';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $fid = NULL) {
    $select_type = _photos_select_sub_album();
    if ($select_type[0]) {
      $form['title']['#markup'] = '<h2>' . t('Please select sub-album') . ': ' . '</h2>';
      $query = db_select('photos_node', 'p')
        ->fields('p', array('nid'))
        ->condition('fid', $fid);
      $result = $query->execute();
      $select_sub = array();
      foreach ($result as $sub) {
        $select_sub[] = $sub->nid;
      }
      if (!isset($select_sub[0])) $select_sub[] = 0;
      $query = db_select('node_field_data', 'n')->extend('Drupal\Core\Database\Query\PagerSelectExtender');
      $query->fields('n', array('nid', 'title'))
        ->condition('n.type', $select_type, 'IN')
        ->condition('n.nid', $select_sub, 'NOT IN')
        ->limit(50);
      $result = $query->execute();
      $form['sub']['#tree'] = TRUE;
      $true = FALSE;
      foreach ($result as $node) {
        $form['sub'][$node->id()] = array(
          '#type' => 'checkbox',
          // @todo update URL , array('attributes' => array('target' => '_blank')).
          '#title' => \Drupal::l($node->getTitle(), Url::fromUri('base:node/' . $node->id())),
        );

        $true = TRUE;
      }
      if ($true) {
        $form['fid'] = array(
          '#type' => 'value',
          '#value' => $fid
        );
        $form['submit'] = array(
          '#type' => 'submit',
          '#value' => t('Send confirmation'),
          '#submit' => array('_photos_to_sub_submit')
        );
      }
      else {
        $form['help']['#markup'] = t('There are no additional sub albums available.');
      }
    }
    else {
      $form['help']['#markup'] = t('Sub-album feature is not turned on.');
    }

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    // ...
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state['values']['fid']) return;
    $query = db_insert('photos_node')->fields(array('nid', 'fid'));
    $nids = array();
    foreach ($form_state['values']['sub'] as $key => $sub) {
      if ($sub) {
        $query->values(array(
          'nid' => $key,
          'fid' => $form_state['values']['fid']
        ));
        $nids[] = $key;
      }
    }
    if (!empty($nids)) {
      $query->execute();
      foreach ($nids as $nid) {
        photos_set_count('node_node', $nid);
      }
      $count = count($nids);
      $msg = \Drupal::translation()->formatPlural($count,
        'Successfully sent to 1 sub-album.',
        'Successfully sent to @count sub-albums.');
      drupal_set_message($msg);
    }
    $redirect = array('photos/image/' . $form_state['values']['fid']);
    $form_state['redirect'] = $redirect;
  }

}
