<?php

/**
 * @file
 * Contains \Drupal\photos\Form\PhotosUploadForm.
 */

namespace Drupal\photos\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\StreamWrapper\PrivateStream;

/**
 * Defines a form to upload photos to this site.
 */
class PhotosUploadForm implements FormInterface {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'photos_upload';
  }

  /**
   * A custom access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   */
  public function access(AccountInterface $account) {
    // Check if user can edit this album.
    $node = \Drupal::routeMatch()->getParameter('node');
    if (!is_object($node)) {
      $node = \Drupal\node\Entity\Node::load($node);
    }
    if (_photos_access('editAlbum', $node)) {
      return AccessResult::allowed();
    }
    else {
      return AccessResult::forbidden();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // @todo create controller to combine upload form & edit list form.
    // - or separate the two.
    $account = \Drupal::currentUser();

    // Get node object.
    $nid = \Drupal::routeMatch()->getParameter('node');
    $node = \Drupal\node\Entity\Node::load($nid);

    $form['#attributes']['enctype'] = 'multipart/form-data';
    $form['new'] = array(
      '#title' => t('Image upload'),
      '#weight' => -4,
      '#type' => 'fieldset',
      '#collapsible' => TRUE
    );
    $allow_zip = ((\Drupal::config('photos.settings')->get('photos_upzip')) ? ' zip' : '');
    // Check if plubload is installed.
    if (\Drupal::config('photos.settings')->get('photos_plupload_status')) {
      $form['new']['plupload'] = array(
        '#type' => 'plupload',
        '#title' => t('Upload photos'),
        '#description' => t('Upload multiple images.'),
        '#autoupload' => TRUE,
        '#submit_element' => '#edit-submit',
        '#upload_validators' => array(
          'file_validate_extensions' => array('jpg jpeg gif png' . $allow_zip),
        ),
        '#plupload_settings' => array(
          'chunk_size' => '1mb',
        ),
      );
    }
    else {
      // Manual upload form.
      $form['new']['#description'] = t('Allow the type:') . ' jpg gif png jpeg' . $allow_zip;

      for ($i = 0; $i < \Drupal::config('photos.settings')->get('photos_num'); ++$i) {
        $form['new']['images_' . $i] = array(
          '#type' => 'file'
        );
        $form['new']['title_' . $i] = array(
          '#type' => 'textfield',
          '#title' => t('Image title'),
        );
        $form['new']['des_' . $i] = array(
          '#type' => 'textarea',
          '#title' => t('Image description'),
          '#cols' => 40,
          '#rows' => 3,
        );
      }
    }
    // @todo pid is redundant unless albums become own entity.
    //  - maybe make pid serial and add nid... or entity_id.
    $form['new']['pid'] = array(
      '#type' => 'value',
      '#value' => $nid
    );
    $form['new']['nid'] = array(
      '#type' => 'value',
      '#value' => $nid
    );
    $form['new']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Confirm upload'),
      '#weight' => 10
    );

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    // ...
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = \Drupal::currentUser();
    $validators = array(
      'file_validate_is_image' => array()
    );
    $count = 0;
    $files_uploaded = array();
    $nid = $form_state->getValue('nid');
    $album_uid = db_query("SELECT uid FROM {node_field_data} WHERE nid = :nid", array(':nid' => $nid))->fetchField();
    // If photos_access is enabled check viewid.
    $scheme = 'default';
    $album_viewid = 0;
    if (\Drupal::moduleHandler()->moduleExists('photos_access')) {
      $node = \Drupal\node\Entity\Node::load($nid);
      if (isset($node->privacy) && isset($node->privacy['viewid'])) {
        $album_viewid = $node->privacy['viewid'];
        if ($album_viewid > 0) {
          // Check for private file path.
          if (PrivateStream::basePath()) {
            $scheme = 'private';
          }
          else {
            // Set warning message.
            drupal_set_message(t('Warning: image files can still be accessed by visiting the direct URL.
              For better security, ask your website admin to setup a private file path.'), 'warning');
          }
        }
      }
    }
    if (empty($album_uid)) {
      $album_uid = $user->id();
    }
    // \Drupal\user\Entity\User::load($album_uid);
    $account = \Drupal::entityManager()->getStorage('user')->load($album_uid);
    // Check if plupload is enabled.
    // @todo check for plupload library?
    if (\Drupal::config('photos.settings')->get('photos_plupload_status')) {
      $plupload_files = $form_state->getValue('plupload');
      foreach ($plupload_files as $uploaded_file) {
        if ($uploaded_file['status'] == 'done') {
          // Check for zip files.
          $ext = \Drupal\Component\Utility\Unicode::substr($uploaded_file['name'], -3);
          if ($ext <> 'zip' && $ext <> 'ZIP') {
            // Prepare directory.
            $photos_path = photos_check_path($scheme, '', $account);
            $photos_name = _photos_rename($uploaded_file['name']);
            $file_uri = file_destination($photos_path . '/' . $photos_name, FILE_EXISTS_RENAME);
            if (file_unmanaged_move($uploaded_file['tmppath'], $file_uri)) {
              $path_parts = pathinfo($file_uri);
              $image = \Drupal::service('image.factory')->get($file_uri);
              if ($path_parts['extension'] && $image->getWidth()) {
                // Create a file entity.
                $file = entity_create('file', array(
                  'uri' => $file_uri,
                  'uid' => $user->id(),
                  'status' => FILE_STATUS_PERMANENT,
                  'pid' => $form_state->getValue('pid'),
                  'nid' => $form_state->getValue('nid'),
                  'filename' => $photos_name,
                  'filesize' => $image->getFileSize(),
                  'filemime' => $image->getMimeType()
                ));

                if ($file_fid = _photos_save_data($file)) {
                  $files_uploaded[] = photos_image_date($file);
                }
                $count++;
              }
              else {
                file_delete($file_uri);
                \Drupal::logger('photos')->notice('Wrong file type');
              }
            }
            else {
              \Drupal::logger('photos')->notice('Upload error. Could not move temp file.');
            }
          }
          else {
            if (!\Drupal::config('photos.settings')->get('photos_upzip')) {
              drupal_set_message(t('Please set Album photos to open zip uploads.'), 'error');
            }
            $directory = photos_check_path();
            file_prepare_directory($directory);
            $zip = file_destination($directory . '/' . $uploaded_file['name'], FILE_EXISTS_RENAME);
            if (file_unmanaged_move($uploaded_file['tmppath'], $zip)) {
              $value = new \StdClass();
              $value->pid = $form_state->getValue('pid');
              $value->nid = $form_state->getValue('nid');
              $value->title = $uploaded_file['name'];
              $value->des = '';
              // Unzip it.
              if (!$file_count = _photos_unzip($zip, $value, $scheme, $account)) {
                drupal_set_message(t('Zip upload failed.'), 'error');
              }
              else {
                // Update image upload count.
                $count = $count+$file_count;
              }
            }
          }
        }
        else {
          drupal_set_message(t('Error uploading some photos.'), 'error');
        }
      }
    }
    else {
      // Manual upload form.
      $pid = $form_state->getValue('pid');
      $photos_num = \Drupal::config('photos.settings')->get('photos_num');
      for ($i = 0; $i < $photos_num; ++$i) {
        if ($_FILES['files']['name']['images_' . $i]) {
          $ext = \Drupal\Component\Utility\Unicode::substr($_FILES['files']['name']['images_' . $i], -3);
          if ($ext <> 'zip' && $ext <> 'ZIP') {
            // Prepare directory.
            $photos_path = photos_check_path($scheme, '', $account);
            if ($file = file_save_upload('images_' . $i, $validators, $photos_path, 0)) {
              // Save file to album. Include title and description.
              $file->pid = $pid;
              $file->nid = $form_state->getValue('nid');
              $file->des = $form_state->getValue('des_' . $i);
              $file->title = $form_state->getValue('title_' . $i);
              $files_uploaded[] = photos_image_date($file);
              $count++;
            }
          }
          else {
            // Zip upload from manual upload form.
            if (!\Drupal::config('photos.settings')->get('photos_upzip')) {
              return form_set_error('error', t('Please update settings to allow zip uploads.'));
            }
            $directory = photos_check_path();
            file_prepare_directory($directory);
            $zip = file_destination($directory . '/' . trim(basename($_FILES['files']['name']['images_' . $i])), FILE_EXISTS_RENAME);
            if (file_unmanaged_move($_FILES['files']['tmp_name']['images_' . $i], $zip)) {
              $value = new \stdClass();
              $value->pid = $pid;
              $value->nid = $form_state->getValue('nid') ? $form_state->getValue('nid') : $form_state->getValue('pid');
              $value->des = $form_state->getValue('des_' . $i);
              $value->title = $form_state->getValue('title_' . $i);
              if (!$file_count = _photos_unzip($zip, $value, $scheme, $account)) {
                $msg = t('Upload failed.');
              }
              else {
                $count = $count+$file_count;
              }
            }
          }
        }
      }
    }
    // Clear node and album page cache.
    Cache::invalidateTags(array('node:' . $nid, 'photos:album:' . $nid));
    $message = \Drupal::translation()->formatPlural($count, '1 image uploaded.', '@count images uploaded.');
    drupal_set_message($message);
  }

}
