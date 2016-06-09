<?php

/**
 * @file
 * Contains \Drupal\photos_access\Plugin\migrate\source\PhotosAccessUser.
 */

namespace Drupal\photos_access\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for photos album access.
 *
 * @MigrateSource(
 *   id = "photos_access_user"
 * )
 */
class PhotosAccessUser extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {

    $query = $this->select('photos_access_user', 'u')
      ->fields('u', ['id', 'uid', 'collaborate']);

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'id' => $this->t('ID'),
      'uid' => $this->t('User ID'),
      'collaborate' => $this->t('User is Collaborator')
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
        'alias' => 'u',
      ],
      'uid' => [
        'type' => 'integer',
        'alias' => 'u',
      ],
    ];
  }

}
