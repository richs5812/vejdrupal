<?php

/**
 * @file
 * Contains \Drupal\photos\Plugin\Block\PhotosInformation.
 */

namespace Drupal\photos\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a 'Photo information' block.
 *
 * @Block(
 *   id = "photos_information",
 *   admin_label = @Translation("Photo Information"),
 *   category = @Translation("Photos")
 * )
 */
class PhotosInformation extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $content = array();
    // Retrieve existing configuration for this block.
    $config = $this->getConfiguration();
    $show_sub_album = isset($config['show_sub_album']) ? $config['show_sub_album'] : 0;

    // Check which pager to load.
    $fid = \Drupal::routeMatch()->getParameter('file');
    $current_path = \Drupal::service('path.current')->getPath();
    $arg = explode('/', $current_path);
    $pager_type = 'pid';
    if (isset($arg[3])) {
      if ($arg[1] == 'photos' && $arg[2] == 'image' && is_numeric($arg[3]) && isset($_GET['photos_sub'])) {
        $fid = $arg[3];
        $pager_type = 'sub';
        $pager_id = (int)$_GET['photos_sub'];
      }
      elseif ($arg[1] == 'photos' && $arg[2] == 'image' && is_numeric($arg[3])) {
        $fid = $arg[3];
        $pager_type = 'pid';
      }
      elseif (isset($arg[5]) && $arg[1] == 'photos' && $arg[2] == 'user' && is_numeric($arg[3]) && is_numeric($arg[5])) {
        $fid = $arg[5];
        $uid = $arg[3];
        $pager_id = $arg[3];
        $pager_type = 'uid';
      }
    }

    if (isset($fid)) {
      // Get current image.
      $query = db_select('photos_image', 'p');
      $query->join('file_managed', 'f', 'f.fid = p.fid');
      $query->join('node_field_data', 'n', 'n.nid = p.pid');
      $query->join('users_field_data', 'u', 'f.uid = u.uid');
      $query->fields('p', array('count', 'comcount', 'exif', 'des'))
        ->fields('f', array('uri', 'created', 'filemime', 'fid'))
        ->fields('n', array('nid', 'title'))
        ->fields('u', array('name', 'uid'))
        ->condition('p.fid', $fid);
      $query->addTag('node_access');
      $image = $query->execute()->fetchObject();
      if ($image) {
        if ($pager_type == 'pid') {
          $pager_id = $image->nid;
        }
        // Get pager image(s).
        $image->pager = \Drupal\photos\Controller\PhotosImageController::imagePager($fid, $pager_id, $pager_type);
        $item = array();
        if (\Drupal::config('photos.settings')->get('photos_print_sizes') < 2) {
          $all_sizes_link = \Drupal::config('photos.settings')->get('photos_print_sizes');
          if ($all_sizes_link < 2) {
            // Display full page or colorbox.
            $colorbox = array();
            if ($all_sizes_link == 1) {
              $colorbox = array(
                'query' => array(
                  'iframe' => 'true',
                  'height' => 650,
                  'width' => 850
                ),
                'attributes' => array(
                  'class' => array('colorbox-load')
                )
              );
            }
            $item[] = \Drupal::l(t('Copy image to share code'), Url::fromUri('base:photos/zoom/' . $image->fid), $colorbox);
          }
        }
        if ($image->exif && \Drupal::config('photos.settings')->get('photos_exif')) {
          $item[] = \Drupal::l(t('View image Exif information'), Url::fromUri('base:photos/zoom/' . $image->fid . '/exif'), array(
            'query' => array(
              'iframe' => 'true',
              'height' => 650,
              'width' => 850
            ),
            'attributes' => array(
              'class' => array('colorbox-load')
            )
          ));
        }
        if (\Drupal::config('photos.settings')->get('photos_slide') && \Drupal::moduleHandler()->moduleExists('dfgallery')) {
          $image->slide_url = Url::fromUri('base:photos/album/' . $image->nid . '/slide')->toString();
        }
        $image->links = array(
          '#theme' => 'item_list',
          '#items' => $item,
          '#cache' => array(
            'max_age' => 0
          )
        );

        if ($show_sub_album) {
          $query = db_select('node_field_data', 'n');
          $query->join('photos_node', 'a', 'a.nid = n.nid');
          $query->join('users_data', 'u', 'u.uid = n.uid');
          $query->fields('n', array('nid', 'title'))
            ->fields('u', array('uid', 'name'))
            ->condition('a.fid', $image->fid)
            ->orderBy('n.nid', 'DESC')
            ->range(0, 10);
          $result = $query->execute();
          foreach ($result as $sub_album) {
            $image->sub_album[$sub_album->nid] =$sub_album;
            $image->sub_album[$sub_album->nid]->geturl = Url::fromUri('base:photos/data/sub_album/' . $sub_album->nid . '/block_new/json.json')->toString();
            $image->sub_album[$sub_album->nid]->url = Url::fromUri('base:photos/sub_album/' . $sub_album->nid)->toString();
            $image->sub_album[$sub_album->nid]->user = $image->name;
            $image->sub_album[$sub_album->nid]->info = t('@name in @time to create', array(
              '@name' => $sub_album->name,
              '@time' => \Drupal::service('date.formatter')->format($image->created, 'short')
            ));
          }
        }
        $content = array(
          '#theme' => 'photos_image_block',
          '#image' => $image,
          '#attached' => array(
            'library' => 'photos/photos.block.information'
          ),
          '#cache' => array(
            'max_age' => 0
          )
        );
      }
      return $content;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    // Retrieve existing configuration for this block.
    // @todo convert variable 'photos_block_num_information_pager' to block config.
    $config = $this->getConfiguration();

    // Add a form field to the existing block configuration form.
    $form['show_sub_album'] = array(
      '#type' => 'radios',
      '#title' => t('Show sub-album info'),
      '#default_value' => isset($config['show_sub_album']) ? $config['show_sub_album'] : 0,
      '#options' => array(t('Disabled'), t('Enabled'))
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
      // Save our custom settings when the form is submitted.
    $this->setConfigurationValue('show_sub_album', $form_state->getValue('show_sub_album'));
  }

}
