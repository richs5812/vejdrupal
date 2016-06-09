<?php

/**
 * @file
 * Definition of Drupal\photos\PhotosUserAlbumsController.
 */

namespace Drupal\photos\Controller;

use Drupal\Core\Url;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PhotosUserAlbumsController {

  /**
   * A custom access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   */
  public function access(AccountInterface $account) {
    // Check if user can view account photos.
    $uid = \Drupal::routeMatch()->getParameter('user');
    $account = \Drupal::entityManager()->getStorage('user')->load($uid);
    if (!$account || _photos_access('viewUser', $account)) {
      return AccessResult::allowed();
    }
    else {
      return AccessResult::forbidden();
    }
  }

  /**
   * Photos page title.
   */
  public function title() {
    // Generate title.
    $user = \Drupal::currentUser();
    $uid = \Drupal::routeMatch()->getParameter('user');
    if ($uid <> $user->id()) {
      $account = \Drupal::entityManager()->getStorage('user')->load($uid);
      return t('@name\'s Albums', array('@name' => $account->getUsername()));
    }
    else {
      return t('My Albums');
    }
  }

  /**
   * Returns content for recent images.
   *
   * @return string
   *   A HTML-formatted string with the administrative page content.
   *
   */
  public function contentOverview() {
    // Get current user and account.
    // @todo a lot of duplicate code can be consolidated in these controllers.
    $user = \Drupal::currentUser();
    $uid = \Drupal::routeMatch()->getParameter('user');
    $account = FALSE;
    if ($uid && is_numeric($uid)) {
      $account = \Drupal::entityManager()->getStorage('user')->load($uid);
    }
    if (!$account) {
      throw new NotFoundHttpException();
    }

    $output = '';
    $build = array();
    $cache_tags = array('user:' . $uid);
    if ($account->id() && $account->id() <> 0) {
      if ($user->id() == $account->id()) {
        $output = \Drupal::l(t('Rearrange albums'), Url::fromRoute('photos.album.rearrange', array('user' => $account->id())));
      }
      $query = db_select('node_field_data', 'n')
        ->extend('Drupal\Core\Database\Query\PagerSelectExtender');
      $query->join('photos_album', 'p', 'p.pid = n.nid');
      $query->fields('n', array('nid'));
      $query->condition('n.uid', $account->id());
      $query->orderBy('p.wid', 'ASC');
      $query->orderBy('n.nid', 'DESC');
      $query->limit(10);
      $query->addTag('node_access');
      $results = $query->execute();
    }
    else {
      $query = db_select('node', 'n')
        ->extend('Drupal\Core\Database\Query\PagerSelectExtender');
      $query->join('photos_album', 'p', 'p.pid = n.nid');
      $query->fields('n', array('nid'));
      $query->orderBy('n.nid', 'DESC');
      $query->limit(10);
      $query->addTag('node_access');
      $results = $query->execute();
    }
    foreach ($results as $result) {
      $nid = $result->nid;
      $cache_tags[] = 'node:' . $nid;
      $cache_tags[] = 'photos:album:' . $nid;
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
      if ($account <> FALSE) {
        $output .= t('@name has not created an album yet.', array('@name' => $account->getUsername()));
      }
      else {
        $output .= t('No albums have been created yet.');
      }
    }
    $build['#markup'] = $output;
    $build['#cache'] = array(
      'tags' => $cache_tags
    );

    return $build;
  }

}
