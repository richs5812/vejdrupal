<?php

/**
 * @file
 * Contains \Drupal\photos\Plugin\migrate\destination\PhotosComment.
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
 *   id = "photos_comment"
 * )
 */
class PhotosComment extends DestinationBase implements ContainerFactoryPluginInterface {

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

    db_insert('photos_comment')
      ->fields(array(
        'fid' => $row->getDestinationProperty('fid'),
        'cid' => $row->getDestinationProperty('cid')
      ))
      ->execute();

    return array($row->getDestinationProperty('fid'), $row->getDestinationProperty('cid'));
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['fid']['type'] = 'integer';
    $ids['cid']['type'] = 'integer';
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function fields(MigrationInterface $migration = NULL) {
    return [
      'fid' => 'File ID',
      'cid' => 'Comment ID'
    ];
  }

}
