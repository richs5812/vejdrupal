<?php

/**
 * @file
 * Definition of Drupal\photos\PhotosController.
 */

namespace Drupal\photos\Controller;

use Drupal\Core\Url;

class PhotosController {

  /**
   * Album views.
   */
  public function albumViews($type, $limit, $url = 0, $uid = 0, $sort = ' n.nid DESC') {
    $query = db_select('photos_album', 'p');
    $query->join('node_field_data', 'n', 'n.nid = p.pid');
    $query->join('users_field_data', 'u', 'u.uid = n.uid');
    $query->fields('p', array('count', 'fid'))
      ->fields('n', array('nid', 'title'))
      ->fields('u', array('uid', 'name'));
    $query->condition('n.status', 1);

    if ($type == 'user') {
      $query->condition('n.uid', $uid);
    }
    if ($type == 'rand') {
      $query->orderRandom();
    }
    else {
      $query->orderBy('n.nid', 'DESC');
    }
    $query->range(0, $limit);
    $query->addTag('node_access');
    $results = $query->execute();

    $i = 0;
    foreach ($results as $result) {
      if ($result->fid) {
        $render_view = photos_get_info($result->fid, 0, array('href' => 'photos/album/' . $result->nid, 'filename' => $result->title));
        $view = drupal_render($render_view);
      }
      else {
        $query = db_select('file_managed', 'f');
        $query->join('photos_image', 'p', 'p.fid = f.fid');
        $query->fields('f', array('fid', 'uri', 'filename'));
        $query->condition('p.pid', $result->nid);
        $query->orderBy('f.fid', 'DESC');
        $image = $query->execute()->fetchObject();
        if (isset($image->fid)) {
          $render_view = photos_get_info($image->fid, $image, array('href' => 'photos/album/' . $result->nid, 'filename' => $result->title));
          $view = drupal_render($render_view);
        }
        else {
          $view = '';
        }
      }
      $album[] = array('node' => $result, 'view' => $view);
      ++$i;
    }
    if ($i) {
      $photo_block = array(
        '#theme' => 'photos_block',
        '#images' => $album,
        '#block_type' => 'album'
      );
      $content = drupal_render($photo_block);
      $url = Url::fromUri('base:' . $url);
      if ($url && $i >= $limit) {
        $more_link = array(
          '#type' => 'more_link',
          '#url' => $url,
          '#title' => t('View more')
        );
        $content .= drupal_render($more_link);
      }
      if ($type == 'user') {
        return array(
          'content' => $content,
          'title' => $album[0]['node']->name . "'s Albums"
        );
      }
      else {
        return $content;
      }
    }
  }

  /**
   * Returns an overview of recent albums and photos.
   *
   * @return string
   *   A HTML-formatted string with the administrative page content.
   *
   */
  public function contentOverview() {
    $account = \Drupal::currentUser();
    $content = array();
    if ($account->id() && $account->hasPermission('create photo')) {
      $val = _photos_block_image('user', 5, 'photos/user/' . $account->id() . '/image', $account->id());
      $content['user']['image'] = isset($val['content']) ? $val['content'] : '';
      $val = $this->albumViews('user', 5, 'photos/user/' . $account->id() . '/album', $account->id());
      $content['user']['album'] = $val['content'] ? $val['content'] : '';
    }
    $content['site']['image'] = _photos_block_image('latest', 5, 'photos/image');
    $content['site']['album'] = $this->albumViews('latest', 5, 'photos/album');

    return array(
      '#theme' => 'photos_default',
      '#content' => $content,
      '#empty' => t('No photos available.'),
    );
  }

  public function imagesReArrange() {
  }
}
