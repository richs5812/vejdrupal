<?php

/**
 * @file
 * Contains \Drupal\photos_access\Plugin\migrate\destination\PhotosAccess.
 */

namespace Drupal\photos_access\Plugin\migrate\destination;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\destination\DestinationBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * @MigrateDestination(
 *   id = "photos_access"
 * )
 */
class PhotosAccess extends DestinationBase implements ContainerFactoryPluginInterface {

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

    db_insert('photos_access_album')
      ->fields(array(
        'id' => $row->getDestinationProperty('id'),
        'nid' => $row->getDestinationProperty('nid'),
        'viewid' => $row->getDestinationProperty('viewid'),
        'pass' => $row->getDestinationProperty('pass'),
      ))
      ->execute();

    return array($row->getDestinationProperty('id'));
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['id']['type'] = 'integer';
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function fields(MigrationInterface $migration = NULL) {
    return [
      'id' => 'ID',
      'nid' => 'Node ID',
      'viewid' => 'Access type',
      'pass' => 'Password'
    ];
  }

}
