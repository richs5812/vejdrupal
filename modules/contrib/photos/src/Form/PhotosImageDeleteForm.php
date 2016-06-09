<?php

/**
 * @file
 * Contains \Drupal\photos\Form\PhotosImageDeleteForm.
 */

namespace Drupal\photos\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Cache\Cache;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines a confirmation form for deleting images.
 */
class PhotosImageDeleteForm extends ConfirmFormBase {

  /**
   * The ID of the item to delete.
   *
   * @var string
   */
  protected $id;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'photos_image_confirm_delete';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Do you want to delete this image?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    $url = Url::fromUri('base:photos/image/' . $this->id);
    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return t('Only do this if you are sure!');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Delete it!');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return t('Nevermind');
  }

  /**
   * {@inheritdoc}
   *
   * @param int $id
   *   (optional) The ID of the item to be deleted.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $file = '') {
    // @todo update access!
    $this->id = $file;
    if (!$this->id) {
      throw new NotFoundHttpException();
    }
    // @todo set album type?
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $fid = $this->id;
    $pid = db_query("SELECT pid FROM {photos_image} WHERE fid = :fid", array(':fid' => $fid))->fetchField();
    // Get album type.
    $type = $form_state->getValue('type');

    if ($type <> 'sub_album') {
      // Remove from search index.
      if (\Drupal::moduleHandler()->moduleExists('search')) {
        search_index_clear('photos', $fid);
      }
      // Delete image.
      $v = photos_file_del($fid, 0, 1);
      // Update album count.
      if (isset($_GET['pid']) && intval($_GET['pid']) == $_GET['pid']) photos_set_count('node_album', $_GET['pid']);
      if (isset($_GET['uid']) && intval($_GET['uid']) == $_GET['uid']) photos_set_count('user_image', $_GET['uid']);
    }
    else {
      // Remove from sub-album.
      $v = db_delete('photos_node')
        ->condition('fid', $fid)
        ->execute();
      // Update sub-album count.
      if (isset($_GET['nid']) && intval($_GET['nid']) == $_GET['nid']) photos_set_count('node_node', $_GET['nid']);
    }
    if ($v) {
      drupal_set_message(t('Image deleted.'));
      // Invalidate cache tags.
      Cache::invalidateTags(array('node:' . $pid, 'photos:album:' . $pid, 'photos:image:' . $fid));
      // @todo redirect to album or sub-album.
      $url = Url::fromUri('base:photos/album/' . $pid);
      $form_state->setRedirectUrl($url);
    }
    else {
      drupal_set_message(t('Delete failed.'));
      // Redirect to cancel URL.
      $form_state->setRedirectUrl($this->getCancelUrl());
    }
  }

}
