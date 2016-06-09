<?php

/**
 * @file
 * Contains \Drupal\photos\Plugin\migrate\destination\PhotosNode.
 */

namespace Drupal\photos\Plugin\migrate\destination;

use Drupal\migrate\Plugin\MigrateDestinationInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\destination\DestinationBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * @MigrateDestination(
 *   id = "photos_node"
 * )
 */
class PhotosNode extends DestinationBase implements ContainerFactoryPluginInterface {

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

    db_insert('photos_node')
      ->fields(array(
        'nid' => $row->getDestinationProperty('nid'),
        'fid' => $row->getDestinationProperty('fid'),
        'wid' => $row->getDestinationProperty('wid')
      ))
      ->execute();

    return array($row->getDestinationProperty('nid'), $row->getDestinationProperty('fid'), $row->getDestinationProperty('wid'));
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['nid']['type'] = 'integer';
    $ids['fid']['type'] = 'integer';
    $ids['wid']['type'] = 'integer';
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function fields(MigrationInterface $migration = NULL) {
    return [
      'nid' => 'Node ID',
      'fid' => 'File ID',
      'wid' => 'Weight'
    ];
  }

}
