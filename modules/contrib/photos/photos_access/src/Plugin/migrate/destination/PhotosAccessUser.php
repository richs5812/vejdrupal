<?php

/**
 * @file
 * Contains \Drupal\photos_access\Plugin\migrate\destination\PhotosAccessUser.
 */

namespace Drupal\photos_access\Plugin\migrate\destination;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\destination\DestinationBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * @MigrateDestination(
 *   id = "photos_access_user"
 * )
 */
class PhotosAccessUser extends DestinationBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = array()) {

    db_insert('photos_access_user')
      ->fields(array(
        'id' => $row->getDestinationProperty('id'),
        'uid' => $row->getDestinationProperty('uid'),
        'collaborate' => $row->getDestinationProperty('collaborate'),
      ))
      ->execute();

    return array($row->getDestinationProperty('id'), $row->getDestinationProperty('uid'), $row->getDestinationProperty('collaborate'));
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['id']['type'] = 'integer';
    $ids['uid']['type'] = 'integer';
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function fields(MigrationInterface $migration = NULL) {
    return [
      'id' => 'ID',
      'uid' => 'User ID',
      'collaborate' => 'User is Collaborator'
    ];
  }

}
