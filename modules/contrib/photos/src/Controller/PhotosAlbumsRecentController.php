<?php

/**
 * @file
 * Definition of Drupal\photos\PhotosAlbumsRecentController.
 */

namespace Drupal\photos\Controller;

class PhotosAlbumsRecentController {

  /**
   * Returns content for recent images.
   *
   * @return string
   *   A HTML-formatted string with the administrative page content.
   *
   */
  public function contentOverview() {
    // @todo a lot of duplicate code can be consolidated in these controllers.
    $query = db_select('node', 'n')
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender');
    $query->join('photos_album', 'p', 'p.pid = n.nid');
    $query->fields('n', array('nid'));
    $query->orderBy('n.nid', 'DESC');
    $query->limit(10);
    $query->addTag('node_access');
    $results = $query->execute();

    $output = '';
    foreach ($results as $result) {
      $node = \Drupal::entityManager()->getStorage('node')->load($result->nid);
      $node_view = node_view($node, 'full');
      $output .= \Drupal::service("renderer")->render($node_view);
    }
    if ($output) {
      $pager = array(
        '#type' => 'pager'
      );
      $output .= \Drupal::service("renderer")->render($pager);
    }
    else {
      $output .= t('No albums have been created yet.');
    }

    return array(
      '#markup' => $output
    );
  }

}
