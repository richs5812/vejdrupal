<?php

/**
 * @file
 * Contains \Drupal\photos\Plugin\migrate\source\PhotosCount.
 */

namespace Drupal\photos\Plugin\migrate\source;

use Drupal\migrate\Plugin\MigrateDestinationInterface;
use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for photos count content.
 *
 * @MigrateSource(
 *   id = "photos_count"
 * )
 */
class PhotosCount extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {

    $query = $this->select('photos_count', 't')
      ->fields('t', ['id', 'cid', 'changed', 'type', 'value']);

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'id' => $this->t('ID'),
      'cid' => $this->t('CID'),
      'changed' => $this->t('Last updated'),
      'type' => $this->t('Type'),
      'value' => $this->t('Count value')
    ];

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'alias' => 't',
      ],
    ];
  }

}
