<?php

/**
 * @file
 * Contains \Drupal\photos\Form\PhotosImageEditForm.
 */

namespace Drupal\photos\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Cache\Cache;

/**
 * Defines a form to edit images.
 */
class PhotosImageEditForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'photos_image_edit';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $image = NULL, $type = 'album') {
    $user = \Drupal::currentUser();

    // Get node object.
    $node = \Drupal\node\Entity\Node::load($image->pid);
    $nid = $node->id();
    $cover = isset($node->album['cover']) ? $node->album['cover'] : array();
    $image->info = array(
      'cover' => $cover,
      'pid' => $node->id(),
      'title' => $node->getTitle(),
      'uid' => $node->getOwnerId()
    );

    if ($node->getType() == 'photos') {
      // Album.
      $album_update = '';
      if ($image && $user->id() <> $image->info['uid']) {
        $title = isset($image->info['title']) ? $image->info['title'] : '';
        $album_update = array($nid, $image->info['title']);
      }
      else {
        $album_update = '';
      }
      $uid = $image ? $image->uid : $user->id();
      $album_pid = _photos_useralbum_option($uid, $album_update);
      $del_label = t('Delete');
      if (isset($node->album) && isset($node->album['cover']['fid'])) {
        $form['cover_fid'] = array('#type' => 'hidden', '#default_value' => $node->album['cover']['fid']);
      }
      $form['oldpid'] = array('#type' => 'hidden', '#default_value' => $nid);
      $submit = 'photos_editlist_submit';
    }
    else {
      // Sub-album.
      $type = 'sub-album';
      $del_label = t('Move out');
      $submit = 'photos_editlist_submit_node';
    }
    $form['nid'] = array('#type' => 'hidden', '#default_value' => $nid);
    $form['type'] = array('#type' => 'hidden', '#value' => $type);
    $form['fid'] = array('#type' => 'hidden', '#value' => $image->fid);
    $form['del'] = array(
      '#title' => $del_label,
      '#type' => 'checkbox',
    );
    $image->user = \Drupal::entityManager()->getStorage('user')->load($image->uid);
    $image->href = 'photos/image/' . $image->fid;
    $item = array();
    $title = $image->title;
    $image_sizes = \Drupal::config('photos.settings')->get('photos_size');
    $style_name = key($image_sizes);
    $image_view = array(
      '#theme' => 'image_style',
      '#style_name' => $style_name,
      '#uri' => $image->uri,
      '#alt' => $title,
      '#title' => $title
    );

    $item[] = \Drupal::l($image_view, Url::fromUri('base:' . $image->href), array(
      'html' => TRUE,
      'attributes' => array('title' => $title)
    ));

    if ($type == 'album' && $image->fid <> $image->fid) {
      // Set cover link.
      $cover_url = Url::fromRoute('photos.album.update.cover', array(
        'node' => $image->pid,
        'file' => $image->fid
      ));
      $item[] = \Drupal::l(t('Set to Cover'), $cover_url);
    }
    if (isset($image->filesize)) {
      // @todo update to use MB?
      $size = round($image->filesize/1024);
      $item[] = t('Filesize: @size KB', array('@size' => number_format($size)));
    }
    if (isset($image->count)) {
      $item[] = t('Visits: @count', array('@count' => $image->count));
    }
    if (isset($image->comcount)) {
      $item[] = t('Comments: @count', array('@count' => $image->comcount));
    }
    $form['title'] = array(
      '#title' => t('Image title'),
      '#type' => 'textfield',
      '#default_value' => isset($image->title) ? $image->title : '',
      '#required' => FALSE
    );
    $form['path'] = array(
      '#theme' => 'item_list',
      '#items' => $item
    );
    // Check for cropper module and add image_crop field.
    if (\Drupal::moduleHandler()->moduleExists('image_widget_crop') &&
      $crop_config = \Drupal::config('image_widget_crop.settings')) {
      if ($crop_config->get('settings.crop_list')) {
        $file = \Drupal\file\Entity\File::load($image->fid);
        $form['image_crop'] = array(
          '#type' => 'image_crop',
          '#file' => $file,
          '#crop_type_list' => $crop_config->get('settings.crop_list'),
          '#crop_preview_image_style' => $crop_config->get('settings.crop_preview_image_style'),
          '#show_default_crop' => $crop_config->get('settings.show_default_crop'),
          '#warn_mupltiple_usages' => $crop_config->get('settings.warn_mupltiple_usages'),
        );
      }
    }
    $form['des'] = array(
      '#title' => t('Image description'),
      '#type' => 'textarea',
      '#default_value' => isset($image->des) ? $image->des : '',
      '#cols' => 40,
      '#rows' => 4
    );
    $form['wid'] = array(
      '#title' => t('Weight'),
      '#type' => 'textfield',
      '#size' => 5,
      '#default_value' => isset($image->wid) ? $image->wid : NULL,
    );
    $form['filepath'] = array('#type' => 'value', '#value' => $image->uri);
    if ($type == 'album') {
      $username = array(
        '#theme' => 'username',
        '#account' => $image->user
      );
      $upload_info = t('Uploaded on @time by @name', array(
        '@name' => drupal_render($username),
        '@time' => \Drupal::service('date.formatter')->format($image->created, 'short')
      ));
      $form['pid'] = array(
        '#title' => t('Move to album'),
        '#type' => 'select',
        '#options' => $album_pid,
        '#default_value' => $image->pid,
        '#required' => TRUE
      );
    }
    else {
      $upload_info = t('Uploaded by @name on @time to @title', array(
        '@name' => array(
          '#theme' => 'username',
          '#account' => $image->user
        ),
        '@time' => \Drupal::service('date.formatter')->format($image->created, 'short'),
        '@title' => \Drupal::l($image->album_title, Url::fromUri('base:node/' . $image->pid))
      ));
    }
    $form['time']['#markup'] = $upload_info;
    $form['uid'] = array('#type' => 'hidden', '#default_value' => $image->uid);
    $form['oldtitle'] = array('#type' => 'hidden', '#default_value' => $image->title);
    if (!empty($image)) {
      $form['actions']['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Confirm changes')
      );
    }

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    // ...
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Process image cropping data.
    $form_state_values = $form_state->getValues();
    $fid = $form_state_values['fid'];
    if (\Drupal::moduleHandler()->moduleExists('image_widget_crop') && isset($form_state_values['image_crop'])) {
      $entity = \Drupal\file\Entity\File::Load($fid);
      if ($entity && is_array($form_state_values['image_crop']) && isset($form_state_values['image_crop']['crop_wrapper'])) {
        $this->submitFormCrops($form, $form_state, $form_state_values, $entity);
      }
    }
    // Save other image data.
    if (!empty($form_state_values['del'])) {
      if ($form_state->getValue('cover_fid') == $fid) {
        db_update('photos_album')
          ->fields(array(
            'fid' => 0
          ))
          ->condition('pid', $form_state->getValue('oldpid'))
          ->execute();
      }
      $msg = photos_file_del($fid, $form_state_values['filepath']);
      $uid = $form_state_values['uid'];
    }
    else {
      $wid = is_numeric($form_state_values['wid']) ? $form_state_values['wid'] : 0;
      db_update('photos_image')
        ->fields(array(
          'pid' => $form_state_values['pid'],
          'des' => $form_state_values['des'],
          'wid' => $wid
        ))
        ->condition('fid', $fid)
        ->execute();

      if ($form_state_values['title'] <> $form_state_values['oldtitle']) {
        db_update('photos_image')
          ->fields(array(
            'title' => $form_state_values['title']
          ))
          ->condition('fid', $fid)
          ->execute();
      }
      if ($form_state_values['pid'] <> $form_state->getValue('oldpid')) {
        $sub_select = db_select('photos_comment', 'v')
          ->fields('v', array('cid'))
          ->condition('v.fid', $fid)
          ->execute()->fetchCol();
        if (!empty($sub_select)) {
          db_update('comment')
            ->fields(array(
              'nid' => $form_state_values['pid']
            ))
            ->condition('cid', $sub_select, 'IN')
            ->execute();
        }
        $pid = $form_state_values['pid'];
        $uid = $form_state_values['uid'];
      }
    }
    // Clear image page cache.
    Cache::invalidateTags(array('photos:image:' . $fid));
    if ($nid = $form_state->getValue('nid')) {
      // Clear album page and node cache.
      Cache::invalidateTags(array('photos:album:' . $nid, 'node:' . $nid));
    }

    if (isset($pid) && $pid) {
      $pid;
      // @todo if image moved to new album also move attached comments to new node?
      // Get node object and update comment statistics.
      // @todo Argument 1 passed to Drupal\comment\CommentStatistics::update() must be an instance of Drupal\comment\CommentInterface.
      // $node = \Drupal\node\Entity\Node::load($nid);
      // \Drupal::service('comment.statistics')->update($node);
      photos_set_count('node_album', $pid);
      // Clear album page and node cache.
      Cache::invalidateTags(array('photos:album:' . $pid, 'node:' . $pid));
      photos_set_count('user_image', $uid);
    }

    // Image deleted or moved.
    if (isset($msg)) {
      $pid = $form_state->getValue('oldpid');
      drupal_set_message(t('Image deleted.'));
      // Redirect to album page.
      $nid = $form_state->getValue('nid');
      $url = Url::fromUri('base:photos/album/' . $nid);
      $form_state->setRedirectUrl($url);
    }
    // @todo redirect to image page?
    // @todo redirect to destination.
    drupal_set_message(t('Changes saved.'));


  // @todo check and implement the following for sub-albums.
  /*
    foreach ($form_state->getValue('photos') as $fid => $form_state_values) {
     if (!empty($form_state_values['del'])) {
       $msg[] = db_query('DELETE FROM {photos_node} WHERE fid = :fid AND nid = :nid',
         array(':fid' => $fid, ':nid' => $form_state->getValue('nid')));
     }
     else {
       $update_fields = array(
         'des' => $form_state_values['des'],
       );
       if ($form_state_values['title'] <> $form_state_values['oldtitle']) {
         $update_fields['title'] = $form_state_values['title'];
       }
       db_merge('photos_image')
         ->key(array(
           'fid' => $fid
         ))
         ->fields($update_fields)
         ->execute();
       if ($form_state_values['wid']) {
         db_update('photos_node')
           ->fields(array(
             'wid' => $form_state_values['wid']
           ))
           ->condition('fid', $fid)
           ->condition('nid', $form_state->getValue('nid'))
           ->execute();
       }
     }
   }
   if (isset($msg)) {
     photos_set_count('node_node', $form_state->getValue('nid'));
     drupal_set_message(t('@count images are move out.', array('@count' => count($msg))));
   }
   */

  }

  /**
   * Form submission handler for image_widget_crop.
   */
  public function submitFormCrops(array &$form, FormStateInterface $form_state, $form_state_values = array(), $entity = NULL) {
    // @var \Drupal\file_entity\Entity\FileEntity $entity.

      // @var \Drupal\image_widget_crop\ImageWidgetCropManager $image_widget_crop_manager.
      $image_widget_crop_manager = \Drupal::service('image_widget_crop.manager');
      // Parse all values and get properties associate with the crop type.
      foreach ($form_state_values['image_crop']['crop_wrapper'] as $crop_type_name => $properties) {
        $properties = $properties['crop_container']['values'];
        // @var \Drupal\crop\Entity\CropType $crop_type.
        $crop_type = \Drupal::entityTypeManager()
          ->getStorage('crop_type')
          ->load($crop_type_name);

        // If the crop type needed is disabled or delete.
        if (empty($crop_type) && $crop_type instanceof \Drupal\crop\Entity\CropType) {
          drupal_set_message(t("The CropType ('@cropType') is not active or not defined. Please verify configuration of image style or ImageWidgetCrop formatter configuration", ['@cropType' => $crop_type->id()]), 'error');
          return;
        }

        if (is_array($properties) && isset($properties)) {
          $crop_exists = \Drupal\crop\Entity\Crop::cropExists($entity->getFileUri(), $crop_type_name);
          if (!$crop_exists) {
            if ($properties['crop_applied'] == '1' && isset($properties) && (!empty($properties['width']) && !empty($properties['height']))) {
              $image_widget_crop_manager->applyCrop($properties, $form_state_values['image_crop'], $crop_type);
            }
          }
          else {
            // Get all imagesStyle used this crop_type.
            $image_styles = $image_widget_crop_manager->getImageStylesByCrop($crop_type_name);
            $crops = $image_widget_crop_manager->loadImageStyleByCrop($image_styles, $crop_type, $entity->getFileUri());
            // If the entity already exist & is not deleted by user update
            // $crop_type_name crop entity.
            if ($properties['crop_applied'] == '0' && !empty($crops)) {
              $image_widget_crop_manager->deleteCrop($entity->getFileUri(), $crop_type, $entity->id());
            }
            elseif (isset($properties) && (!empty($properties['width']) && !empty($properties['height']))) {
              $image_widget_crop_manager->updateCrop($properties, [
                'file-uri' => $entity->getFileUri(),
                'file-id' => $entity->id(),
              ], $crop_type);
            }
          }
        }
      }
  }

}
