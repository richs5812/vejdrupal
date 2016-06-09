<?php

/**
 * @file
 * Definition of Drupal\photos\PhotosRearrangeController.
 */

namespace Drupal\photos\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PhotosRearrangeController {

  /**
   * Set page title.
   */
  public function getTitle() {
    // Get node object.
    $nid = \Drupal::routeMatch()->getParameter('node');
    $node = \Drupal\node\Entity\Node::load($nid);
    $title = t('Rearrange Photos: @title', array('@title' => $node->getTitle()));
    return $title;
  }

  /**
   * A custom access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   */
  public function access(AccountInterface $account) {
    // Check if user can edit this album.
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node && !is_object($node)) {
      $node = \Drupal\node\Entity\Node::load($node);
    }
    // Check user for album rearrange.
    $user = \Drupal::routeMatch()->getParameter('user');
    if ($user && !is_object($user)) {
      $user = \Drupal\user\Entity\User::load($user);
    }
    if ($node && _photos_access('editAlbum', $node)) {
      return AccessResult::allowed();
    }
    elseif ($user && _photos_access('viewUser', $user)) {
      return AccessResult::allowed();
    }
    else {
      return AccessResult::forbidden();
    }
  }

  /**
   * Get album images.
   */
  public function albumImages($nid, $limit = 10) {
    $images = array();
    $column = isset($_GET['field']) ? \Drupal\Component\Utility\Html::escape($_GET['field']) : '';
    $sort = isset($_GET['sort']) ? \Drupal\Component\Utility\Html::escape($_GET['sort']) : '';
    $term = _photos_order_value($column, $sort, $limit, array('column' => 'p.wid', 'sort' => 'asc'));
    $query = db_select('file_managed', 'f')
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender');
    $query->join('photos_image', 'p', 'p.fid = f.fid');
    $query->join('users_field_data', 'u', 'f.uid = u.uid');
    $query->join('node_field_data', 'n', 'n.nid = p.pid');
    $query->fields('f', array('uri', 'filemime', 'created', 'filename', 'filesize'));
    $query->fields('p');
    $query->fields('u', array('uid', 'name'));
    $query->condition('p.pid', $nid);
    $query->limit($term['limit']);
    $query->orderBy($term['order']['column'], $term['order']['sort']);
    $query->addTag('node_access');
    $result = $query->execute();
    foreach ($result as $data) {
      $images[] = photos_get_info(0, $data);
    }
    if (isset($images[0]->fid)) {
      $node = \Drupal::entityManager()->getStorage('node')->load($nid);
      $images[0]->info = array(
        'pid' => $node->id(),
        'title' => $node->getTitle(),
        'uid' => $node->uid
      );
      if (isset($node->album['cover'])) {
        $images[0]->info['cover'] = $node->album['cover'];
      }
    }
    return $images;
  }

  /**
   * Returns photos to be rearranged.
   *
   * @return string
   *   A HTML-formatted string with the administrative page content.
   *
   */
  public function contentOverview() {
    // Get node object.
    $node = \Drupal::routeMatch()->getParameter('node');
    if (!is_object($node)) {
      $node = \Drupal\node\Entity\Node::load($node);
    }
    $nid = $node->id();
    // Check album type.
    $type = 'album';
    if (isset($node->subalbum)) {
      $type = 'sub_album';
    }
    $output = '';
    $build = array();
    $update_button = '';
    if (isset($node->album['imageorder']) && $node->album['imageorder'] <> 'weight|asc') {
      $update_button = ' ' . t('Update album image display order to "Weight - smallest first".');
    }

    // Load library photos.dragndrop
    $build['#attached']['library'][] = 'photos/photos.dragndrop';
    // Set custom drupalSettings for use in JavaScript file.
    $build['#attached']['drupalSettings']['photos']['pid'] = $nid;


    $images = array();
    if ($type == 'album') {
      // Set custom drupalSettings for use in JavaScript file.
      $build['#attached']['drupalSettings']['photos']['sort'] = 'images';
    }
    elseif ($type == 'sub_album') {
      // Set custom drupalSettings for use in JavaScript file.
      $build['#attached']['drupalSettings']['photos']['sort'] = 'sub_album';
    }
    $images = $this->albumImages($nid, 50);
    $count = count($images);
    $output .= t('Limit') . ': ' . \Drupal::l(100, Url::fromUri('base:node/' . $nid . '/photos-rearrange', array('query' => array('limit' => 100))));
    $output .= ' - ' . \Drupal::l(500, Url::fromUri('base:node/' . $nid . '/photos-rearrange', array('query' => array('limit' => 500))));
    $default_message = t('%img_count images to rearrange.', array('%img_count' => $count));
    $output .= '<div id="photos-sort-message">' . $default_message . $update_button . ' ' . '<span id="photos-sort-updates"></span></div>';
    $output .= '<ul id="photos-sortable">';
    foreach ($images as $image) {
      $item = array();
      $title = $image->title;
      // @todo set photos_sort_style variable for custom image style settings.
      $image_sizes = \Drupal::config('photos.settings')->get('photos_size');
      $style_name = key($image_sizes);
      $output .= '<li id="photos_' . $image->fid . '" class="photos-sort-grid ui-state-default">';
      $render_image = array(
        '#theme' => 'image_style',
        '#style_name' => $style_name,
        '#uri' => $image->uri,
        '#alt' => $title,
        '#title' => $title
      );
      $output .= \Drupal::service("renderer")->render($render_image);

      $output .= '</li>';
    }
    $output .= '</ul>';
    $build['#markup'] = $output;
    $build['#cache'] = array(
      'tags' => array('node:' . $nid, 'photos:album:' . $nid)
    );

    return $build;
  }

  /**
   * Rearrange user albums.
   */
  public function albumRearrange() {
    $output = '';
    $build = array();
    $account = \Drupal::routeMatch()->getParameter('user');
    if ($account && !is_object($account)) {
      $account = \Drupal\user\Entity\User::load($account);
    }
    $uid = $account->id();
    // Load library photos.dragndrop
    $build['#attached']['library'][] = 'photos/photos.dragndrop';
    // Set custom drupalSettings for use in JavaScript file.
    $build['#attached']['drupalSettings']['photos']['uid'] = $uid;
    $build['#attached']['drupalSettings']['photos']['sort'] = 'albums';

    $albums = $this->getAlbums($uid);
    $count = count($albums);
    $limit_uri = Url::fromUri('base:photos/user/' . $uid . '/album-rearrange', array('query' => array('limit' => 100)));
    $output .= t('Limit') . ': ' . \Drupal::l(100, $limit_uri);
    $limit_uri = Url::fromUri('base:photos/user/' . $uid . '/album-rearrange', array('query' => array('limit' => 500)));
    $output .= ' - ' . \Drupal::l(500, $limit_uri);
    $default_message = t('%album_count albums to rearrange.', array('%album_count' => $count));
    $output .= '<div id="photos-sort-message">' . $default_message . ' ' . '<span id="photos-sort-updates"></span></div>';
    $output .= '<ul id="photos-sortable">';
    foreach ($albums as $album) {
      $item = array();
      $title = $album['title'];
      $cover = file_load($album['fid']);
      // @todo set photos_sort_style variable for custom image style settings.
      $image_sizes = \Drupal::config('photos.settings')->get('photos_size');
      $style_name = key($image_sizes);
      $output .= '<li id="photos_' . $album['nid'] . '" class="photos-sort-grid ui-state-default">';
      $render_image = array(
        '#theme' => 'image_style',
        '#style_name' => $style_name,
        '#uri' => $cover->getFileUri(),
        '#alt' => $title,
        '#title' => $title
      );
      $output .= \Drupal::service("renderer")->render($render_image);

      $output .= '</li>';
    }
    $output .= '</ul>';
    $build['#markup'] = $output;
    $build['#cache'] = array(
      'tags' => array('user:' . $uid)
    );

    return $build;
  }

  /**
   * Get user albums.
   */
  public function getAlbums($uid) {
    $albums = array();
    $limit = isset($_GET['limit']) ? \Drupal\Component\Utility\Html::escape($_GET['limit']) : 50;
    $query = db_select('node_field_data', 'n');
    $query->join('photos_album', 'p', 'p.pid = n.nid');
    $query->fields('n', array('nid', 'title'));
    $query->fields('p', array('wid', 'fid', 'count'));
    $query->condition('n.uid', $uid);
    $query->range(0, $limit);
    $query->orderBy('p.wid', 'ASC');
    $query->orderBy('n.nid', 'DESC');
    $result = $query->execute();

    foreach ($result as $data) {
      if (isset($data->fid) && $data->fid <> 0) {
        $cover_fid = $data->fid;
      }
      else {
        $cover_fid = db_query("SELECT fid FROM {photos_image} WHERE pid = :pid", array(':pid' => $data->nid))->fetchField();
        if (empty($cover_fid)) {
          // Skip albums with no images.
          continue;
        }
      }
      $albums[] = array(
        'wid' => $data->wid,
        'nid' => $data->nid,
        'fid' => $cover_fid,
        'count' => $data->count,
        'title' => $data->title
      );
    }
    return $albums;
  }

  /**
   * Ajax callback to save new image order.
   */
  public function ajaxRearrange() {
    // @todo convert to CommandInterface class?
    $nid = isset($_POST['pid']) ? $_POST['pid'] : 0;
    $uid = isset($_POST['uid']) ? $_POST['uid'] : 0;
    $type = isset($_POST['type']) ? $_POST['type'] : 0;
    $new_order = isset($_POST['order']) ? $_POST['order'] : array();
    $message = '';
    if (!empty($new_order) && is_array($new_order)) {
      if ($type == 'images') {
        if ($nid) {
          $message = $this->editSortSave($new_order, $nid, $type);
        }
      }
      elseif ($type == 'albums') {
        if ($uid) {
          // Save sort order for albums.
          $message = $this->editSortAlbumsSave($new_order, $uid);
        }
      }
      elseif ($type == 'sub_album') {
        // Save sort order for images in sub-albums.
        if ($nid) {
          $message = $this->editSortSave($new_order, $nid, $type);
        }
      }
    }
    if ($nid) {
      // Clear album page cache.
      Cache::invalidateTags(array('node:' . $nid, 'photos:album:' . $nid));
    }

    // Build plain text response.
    $response = new Response();
    $response->headers->set('Content-Type', 'text/plain');
    $response->setContent($message);
    return $response;
  }

  /**
   * Save new order.
   */
  public function editSortSave($order = array(), $nid = 0, $type = 'images') {
    if ($nid) {
      $access = FALSE;
      if ($nid) {
        $node = \Drupal::entityManager()->getStorage('node')->load($nid);
        // Check for node_accss.
        $access = _photos_access('editAlbum', $node);
      }
      if ($access) {
        $weight = 0;
        // Update weight for all images in array / album.
        foreach ($order as $image_id) {
          $fid = str_replace('photos_', '', $image_id);
          if ($type == 'images') {
            // Save sort order for images in album.
            db_query("UPDATE {photos_image} SET wid = :wid WHERE fid = :fid AND pid = :pid",
              array(':wid' => $weight, ':fid' => $fid, ':pid' => $nid));
          }
          else {
            // Save sort order for images in sub-albums.
            db_query("UPDATE {photos_node} SET wid = :wid WHERE fid = :fid AND nid = :nid",
              array(':wid' => $weight, ':fid' => $fid, ':nid' => $nid));
          }
          $weight++;
        }
        if ($weight > 0) {
          $message = t('Image order saved!');
          return $message;
        }
      }
    }
  }

  /**
   * Save new album weights.
   */
  public function editSortAlbumsSave($order = array(), $uid = 0) {
    if ($uid) {
      $user = \Drupal::currentUser();
      $access = FALSE;
      // @todo add support for admin role?
      if ($user->id() == $uid || $user->id() == 1) {
        $weight = 0;
        // Update weight for all albums in array.
        foreach ($order as $album_id) {
          $pid = str_replace('photos_', '', $album_id);
          $node = \Drupal::entityManager()->getStorage('node')->load($pid);
          // Check for node_accss.
          $access = _photos_access('editAlbum', $node);
          if ($access) {
            db_query("UPDATE {photos_album} SET wid = :wid WHERE pid = :pid",
              array(':wid' => $weight, ':pid' => $pid));
            $weight++;
          }
        }
        if ($weight > 0) {
          $message = t('Album order saved!');
          return $message;
        }
      }
    }
  }

}
