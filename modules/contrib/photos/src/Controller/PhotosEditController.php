<?php

/**
 * @file
 * Definition of Drupal\photos\PhotosEditController.
 */

namespace Drupal\photos\Controller;

use Drupal\Core\Url;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PhotosEditController {

  /**
   * A custom access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   */
  public function access(AccountInterface $account) {
    // Check for available parameters.
    $nid = \Drupal::routeMatch()->getParameter('node');
    $fid = \Drupal::routeMatch()->getParameter('file');
    if ($nid && $fid) {
      // Update cover.
      $node = \Drupal\node\Entity\Node::load($nid);
      if (_photos_access('editAlbum', $node)) {
        // Allowed to update album cover image.
        return AccessResult::allowed();
      }
      else {
        // Deny access.
        return AccessResult::forbidden();
      }
    }
    elseif ($fid) {
      // Check if edit or delete.
      $current_path = \Drupal::service('path.current')->getPath();
      $path_args = explode('/', $current_path);
      if (isset($path_args[4])) {
        if ($path_args[4] == 'edit' || $path_args[4] == 'to_sub') {
          if (_photos_access('imageEdit', $fid)) {
            // Allowed to edit image.
            return AccessResult::allowed();
          }
        }
        elseif ($path_args[4] == 'delete') {
          if (_photos_access('imageDelete', $fid)) {
            // Allowed to delete image.
            return AccessResult::allowed();
          }
        }
      }
      // Deny access.
      return AccessResult::forbidden();
    }
    else {
      return AccessResult::neutral();
    }
  }

  /**
   * Edit image.
   */
  public function editImage($file) {
    $fid = $file;
    $output = '';
    $query = db_select('file_managed', 'f');
    $query->join('photos_image', 'p', 'p.fid = f.fid');
    $query->join('users_field_data', 'u', 'f.uid = u.uid');
    $query->fields('f', array('uri', 'filemime', 'created', 'filename', 'filesize'));
    $query->fields('p');
    $query->fields('u', array('uid', 'name'));
    $query->condition('f.fid', $fid);
    $image = $query->execute()->fetchObject();

    if ($image && isset($image->fid)) {
      $edit_form = \Drupal::formBuilder()->getForm('\Drupal\photos\Form\PhotosImageEditForm', $image);
      return $edit_form;
    }
    else {
      throw new NotFoundHttpException();
    }

  }

  /**
   * Delete image from gallery and site.
   */
  public function deleteImage($file) {
    $fid = $file;
    if (\Drupal::moduleHandler()->moduleExists('colorbox')) {
      // Dispaly form in modal popup.
      // @todo does this still work?
      $confirm_delete_form = \Drupal::formBuilder()->getForm('\Drupal\photos\Form\PhotosImageConfirmDeleteForm', $fid);
      print \Drupal::service("renderer")->render($confirm_delete_form);
    }
    else {
      // Render full page.
      return \Drupal::formBuilder()->getForm('\Drupal\photos\Form\PhotosImageConfirmDeleteForm', $fid);
    }
  }

  /**
   * Ajax edit image.
   */
  public function ajaxEditUpdate($fid = NULL) {
    $message = '';
    if (isset($_POST['id'])) {
      $value = isset($_POST['value']) ? trim($_POST['value']) : '';
      $id = \Drupal\Component\Utility\SafeMarkup::checkPlain($_POST['id']);
      // Get fid.
      if (strstr($id, 'title')) {
        $switch = 'title';
        $fid = str_replace('photos-image-edit-title-', '', $id);
      }
      elseif (strstr($id, 'des')) {
        $switch = 'des';
        $fid = str_replace('photos-image-edit-des-', '', $id);
      }
      $fid = filter_var($fid, FILTER_SANITIZE_NUMBER_INT);
      // Check user image edit permissions.
      // @todo photos.routing.yml _csrf_token: 'TRUE'.
      if ($fid && _photos_access('imageEdit', $fid)) {
        switch ($switch) {
          case 'title':
            db_update('photos_image')
              ->fields(array(
                'title' => $value
              ))
              ->condition('fid', $fid)
              ->execute();
            $message = \Drupal\Component\Utility\SafeMarkup::checkPlain($value);
          break;
          case 'des':
            db_update('photos_image')
              ->fields(array(
                'des' => $value
              ))
              ->condition('fid', $fid)
              ->execute();
            $message = \Drupal\Component\Utility\SafeMarkup::checkPlain($value);
          break;
        }
        // Clear cache.
        $pid = db_query("SELECT pid FROM {photos_image} WHERE fid = :fid", array(':fid' => $fid))->fetchField();
        if ($pid) {
          Cache::invalidateTags(array('node:' . $pid, 'photos:album:' . $pid));
        }
        Cache::invalidateTags(array('photos:image:' . $fid));
      }
    }

    // Build plain text response.
    $response = new Response();
    $response->headers->set('Content-Type', 'text/plain');
    $response->setContent($message);
    return $response;
  }

  /**
   * Ajax edit image load text.
   */
  public function ajaxEditUpdateLoad() {
    $message = '';
    if (isset($_POST['id'])) {
      $id = \Drupal\Component\Utility\SafeMarkup::checkPlain($_POST['id']);
      if (strstr($id, 'title')) {
        $switch = 'title';
        $fid = str_replace('photos-image-edit-title-', '', $id);
      }
      elseif (strstr($id, 'des')) {
        $switch = 'des';
        $fid = str_replace('photos-image-edit-des-', '', $id);
      }
      $fid = filter_var($fid, FILTER_SANITIZE_NUMBER_INT);
      // Check user image edit permissions.
      // @todo photos.routing.yml _csrf_token: 'TRUE'.
      if ($fid && _photos_access('imageEdit', $fid)) {
        switch ($switch) {
          case 'title':
            $value = db_query("SELECT title FROM {photos_image} WHERE fid = :fid", array(':fid' => $fid))->fetchField();
            $message = $value;
          break;
          case 'des':
            $value = db_query("SELECT des FROM {photos_image} WHERE fid = :fid", array(':fid' => $fid))->fetchField();
            $message = $value;
          break;
        }
        // Clear cache.
        $pid = db_query("SELECT pid FROM {photos_image} WHERE fid = :fid", array(':fid' => $fid))->fetchField();
        if ($pid) {
          Cache::invalidateTags(array('node:' . $pid, 'photos:album:' . $pid));
        }
        Cache::invalidateTags(array('photos:image:' . $fid));
      }
    }

    // Build plain text response.
    $response = new Response();
    $response->headers->set('Content-Type', 'text/plain');
    $response->setContent($message);
    return $response;
  }

  /**
   * Set album cover.
   */
  public function setAlbumCover($node, $file) {
    $pid = db_query('SELECT pid FROM {photos_image} WHERE fid = :fid', array(':fid' => $file))->fetchField();
    if ($pid == $node) {
      // Update cover.
      db_query('UPDATE {photos_album} SET fid = :fid WHERE pid = :pid',
        array(':fid' => $file, ':pid' => $node));
      // Clear node cache.
      Cache::invalidateTags(array('node:' . $node, 'photos:album:' . $node));
      drupal_set_message(t('Cover successfully set.'));
      $goto = isset($_GET['destination']) ? $_GET['destination'] : 'photos/album/' . $node;
      $goto = Url::fromUri('base:' . $goto)->toString();
      $response = new RedirectResponse($goto);
      $response->send();
      return array(
        '#markup' => t('Cover successfully set.')
      );
    }
    else {
      throw new NotFoundHttpException();
    }
  }

  /**
   * Add image to sub album.
   */
  public function addImageSubAlbum($file) {
    $user = \Drupal::currentUser();
    // @todo update form and test (\Drupal\photos\Form\PhotosImageSubAlbumForm).
    $photos_to_sub_form = \Drupal::formBuilder()->getForm('\Drupal\photos\Form\PhotosImageSubAlbumForm', $file);
    $content = \Drupal::service("renderer")->render($photos_to_sub_form);
    $content .= drupal_render(array('#type' => 'pager'));

    $render_array = array(
      '#theme' => 'photos_print',
      '#content' => $content
    );

    print drupal_render($render_array);
  }

}
