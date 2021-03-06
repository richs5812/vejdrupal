<?php

/**
 * @file
 * Contains \Drupal\photos\Plugin\migrate\source\PhotosNode.
 */

namespace Drupal\photos\Plugin\migrate\source;

use Drupal\migrate\Plugin\MigrateDestinationInterface;
use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for photos sub-album content.
 *
 * @MigrateSource(
 *   id = "photos_node"
 * )
 */
class PhotosNode extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {

    $query = $this->select('photos_node', 'n')
      ->fields('n', ['nid', 'fid', 'wid']);

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'nid' => $this->t('Node ID'),
      'fid' => $this->t('File ID'),
      'wid' => $this->t('Weight')
    ];

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'nid' => [
        'type' => 'integer',
        'alias' => 'n',
      ],
      'fid' => [
        'type' => 'integer',
        'alias' => 'n',
      ],
      'wid' => [
        'type' => 'integer',
        'alias' => 'n',
      ],
    ];
  }

}
