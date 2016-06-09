<?php

/**
 * @file
 * Contains \Drupal\photos\PhotosBreadcrumbBuilder.
 */

namespace Drupal\photos;

use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Link;
use Drupal\Core\Routing\LinkGeneratorTrait;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

class PhotosBreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    // Check if image page.
    $fid = $route_match->getParameter('file');
    if ($fid) {
      $current_path = \Drupal::service('path.current')->getPath();
      $path_args = explode('/', $current_path);
      return ($path_args[1] == 'photos' && $path_args[2] == 'image');
    }
    // Check if album page.
    $node = $route_match->getParameter('node');
    return $node instanceof NodeInterface && !empty($node->album);
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheContexts(['route']);
    // Home.
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));
    $fid = $route_match->getParameter('file');
    if ($fid) {
      // Recent images.
      $breadcrumb->addLink(Link::createFromRoute($this->t('Images'), 'photos.image.recent'));
      // Images by User.
      $uid = db_query("SELECT uid FROM {file_managed} WHERE fid = :fid", array(':fid' => $fid))->fetchField();
      $account = \Drupal\user\Entity\User::load($uid);
      $username = $account->getUsername();
      $breadcrumb->addLink(Link::createFromRoute($this->t('Images by :name', array(':name' => $username)), 'photos.user.images', ['user' => $uid]));
      // Album.
      $pid = db_query("SELECT pid FROM {photos_image} WHERE fid = :fid", array(':fid' => $fid))->fetchField();
      $node = \Drupal\node\Entity\Node::load($pid);
      $breadcrumb->addLink(Link::createFromRoute($node->getTitle(), 'photos.album', ['node' => $pid]));
      // Image.
      // @todo image title?
    }

    return $breadcrumb;
  }

}
