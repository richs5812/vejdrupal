<?php

/**
 * @file
 * Definition of Drupal\photos\Plugin\views\field\PhotosImage
 */

namespace Drupal\photos\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\file\Entity\File;

/**
 * Field handler to view album photos.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("photos_image")
 */
class PhotosImage extends FieldPluginBase {

  /**
   * Define the available options
   * @return array
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['link_photo'] = array('default' => '');
    $options['image_style'] = array('default' => '');

    return $options;
  }

  /**
   * Provide the options form.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    // Link options.
    $form['link_photo'] = array(
      '#title' => t("Link image"),
      '#description' => t("Link the image to the album page or image page."),
      '#type' => 'radios',
      '#options' => array(
        '' => $this->t('None'),
        'album' => $this->t('Album page'),
        'image' => $this->t('Image page')
      ),
      '#default_value' => $this->options['link_photo']
    );

    // Get image styles.
    $style_options = image_style_options();
    $form['image_style'] = array(
      '#title' => $this->t('Image style'),
      '#type' => 'select',
      '#default_value' => $this->options['image_style'],
      '#options' => $style_options,
    );

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    $render_image = array();
    $image_style = $this->options['image_style'];
    $picture_fid = $this->getValue($values);

    if (!$picture_fid) {
      $node = $values->_entity;
      // Get first image for cover photo.
      /*if ($node && $node->getType() == 'photos') {
        $nid = $node->id();
        $picture_fid = db_query("SELECT fid FROM {photos_image} WHERE pid = :nid ORDER BY fid ASC",
          array(':nid' => $nid))->fetchField();
      }*/
    }

    if ($image_style && $picture_fid) {
      $file = File::load($picture_fid);
      $render_image = array(
        '#theme' => 'image_style',
        '#style_name' => $this->options['image_style'],
        '#uri' => $file->getFileUri(),
        '#cache' => array(
          'tags' => array('photos:image:' . $picture_fid)
        )
      );
    }

    // Add the link if option is selected.
    if ($this->options['link_photo'] == 'image') {
      // Link to image page.
      $image = \Drupal::service('renderer')->render($render_image);
      $link_href = 'base:photos/image/' . $picture_fid;
      $render_image = array(
        '#type' => 'link',
        '#title' => $image,
        '#url' => \Drupal\Core\Url::fromUri($link_href),
        '#options' => array(
          'attributes' => array('html' => TRUE),
        ),
        '#cache' => array(
          'tags' => array('photos:image:' . $picture_fid)
        )
      );
    }
    elseif ($this->options['link_photo'] == 'album') {
      // Get album id and link to album page.
      $node = $values->_entity;
      $nid = $node->id();
      $album_id = db_query("SELECT pid FROM {photos_image} WHERE fid = :nid",
          array(':nid' => $nid))->fetchField();
      //var_dump($album_id);
      $image = \Drupal::service('renderer')->render($render_image);
      $link_href = 'base:photos/album/' . $album_id;
      $render_image = array(
        '#type' => 'link',
        '#title' => $image,
        '#url' => \Drupal\Core\Url::fromUri($link_href),
        '#options' => array(
          'attributes' => array('html' => TRUE),
        ),
        '#cache' => array(
          'tags' => array('photos:album:' . $nid, 'photos:image:' . $picture_fid)
        )
      );
    }

    return $render_image;
  }
}
