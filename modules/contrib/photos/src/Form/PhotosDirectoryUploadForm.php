<?php

/**
 * @file
 * Contains \Drupal\photos\Form\PhotosDirectoryUploadForm.
 */

namespace Drupal\photos\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\Url;

/**
 * Defines a form to upload photos to this site.
 */
class PhotosDirectoryUploadForm implements FormInterface {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'photos_directory_upload';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $submit_text = t('Move images');
    $show_submit = TRUE;
    // Add warning that images will be moved to the appropriate album's directory.
    // @todo add to help?
    $instructions = t('To add photos to an album from a directory that is already on the server first choose a user.
                     Then select an album. Then enter the directory where the photos are located. Note that the photos
                     will be moved to the selected albums directory.');
    $form['instructions'] = array(
      '#markup' => '<div>' . $instructions . '</div>'
    );
    if ($uid = $form_state->getValue('user')) {
      // Look up user albums and generate options for select list.
      $albums = db_query("SELECT nid, title FROM {node_field_data} WHERE uid = :uid AND type = 'photos'", array(':uid' => $uid));
      $options = array();
      foreach ($albums as $album) {
        $options[$album->nid] = '[nid:' . $album->nid . '] ' . $album->title;
      }
      if (empty($options)) {
        // No albums found for selected user.
        $add_album_link = \Drupal::l(t('Add new album.'), Url::fromUri('base:node/add/photos'));
        $form['add_album'] = array(
          '#markup' => '<div>' . t('No albums found.') . ' ' . $add_album_link . '</div>'
        );
        $show_submit = FALSE;
      }
      else {
        // Select album.
        $form['uid'] = array('#type' => 'hidden', '#value' => $uid);
        $form['album'] = array(
          '#type' => 'select',
          '#title' => t('Select album'),
          '#options' => $options
        );
        // Directory.
        $form['directory'] = array(
          '#title' => t('Directory'),
          '#type' => 'textfield',
          '#default_value' => '',
          '#description' => t('Directory containing images. Include / for absolute path. Include
            public:// or private:// to scan a directory in the public or private filesystem.'),
        );
      }
    }
    else {
      // User autocomplete.
      $form['user'] = array(
        '#type' => 'entity_autocomplete',
        '#title' => t('Username'),
        '#description' => t('Enter a user name.'),
        '#target_type' => 'user',
        '#tags' => FALSE,
        '#default_value' => '',
        '#process_default_value' => FALSE,
      );
      $submit_text = t('Select user');
    }

    // @todo batch operation?
    if ($show_submit) {
      $form['actions']['submit'] = array(
        '#type' => 'submit',
        '#value' => $submit_text,
        '#weight' => 10
      );
    }

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $directory = $form_state->getValue('directory');
    // @todo check if directory exists.
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user_value = $form_state->getValue('user');
    if ($user_value) {
      $form_state->setRebuild();
    }
    else {
      // @todo check if file is already in use before moving?
      // - If in use copy?
      $album = $form_state->getValue('album');
      $directory = $form_state->getValue('directory');
      $user = \Drupal::currentUser();
      $validators = array(
        'file_validate_is_image' => array()
      );
      $count = 0;
      $files_uploaded = array();
      $nid = $album;
      $album_uid = $form_state->getValue('uid');
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
      $account = \Drupal::entityManager()->getStorage('user')->load($album_uid);
      // Check if zip is included.
      $allow_zip = \Drupal::config('photos.settings')->get('photos_upzip') ? '|zip|ZIP' : '';
      $file_extensions = 'png|PNG|jpg|JPG|jpeg|JPEG|gif|GIF' . $allow_zip;
      $files = file_scan_directory($directory, '/^.*\.(' . $file_extensions . ')$/');
      foreach ($files as $dir_file) {
        $ext = \Drupal\Component\Utility\Unicode::substr($dir_file->uri, -3);
        if ($ext <> 'zip' && $ext <> 'ZIP') {
          // Prepare directory.
          $photos_path = photos_check_path($scheme, '', $account);
          $photos_name = _photos_rename($dir_file->filename);
          $file_uri = file_destination($photos_path . '/' . $photos_name, FILE_EXISTS_RENAME);
          if (file_unmanaged_move($dir_file->uri, $file_uri)) {
            // Save file to album. Include title and description.
            $image = \Drupal::service('image.factory')->get($file_uri);
            if ($image->getWidth()) {
              // Create a file entity.
              $file = entity_create('file', array(
                'uri' => $file_uri,
                'uid' => $user->id(),
                'status' => FILE_STATUS_PERMANENT,
                'pid' => $nid,
                'nid' => $nid,
                'filename' => $photos_name,
                'filesize' => $image->getFileSize(),
                'filemime' => $image->getMimeType()
              ));

              if ($file_fid = _photos_save_data($file)) {
                $files_uploaded[] = photos_image_date($file);
              }
              $count++;
            }
          }
        }
        else {
          // Zip upload from manual upload form.
          if (!\Drupal::config('photos.settings')->get('photos_upzip')) {
            return form_set_error('error', t('Please update settings to allow zip uploads.'));
          }
          $directory = photos_check_path();
          file_prepare_directory($directory);
          $zip = file_destination($directory . '/' . trim(basename($dir_file->uri)), FILE_EXISTS_RENAME);
          if (file_unmanaged_move($dir_file->uri, $zip)) {
            $value = new \stdClass();
            $value->pid = $nid;
            $value->nid = $nid;
            $value->des = '';
            $value->title = $dir_file->filename;
            if (!$file_count = _photos_unzip($zip, $value, $scheme, $account)) {
            $msg = t('Upload failed.');
            }
            else {
              $count = $count+$file_count;
            }
          }
        }
      }
      // Clear node and album page cache.
      Cache::invalidateTags(array('node:' . $nid, 'photos:album:' . $nid));
      $message = \Drupal::translation()->formatPlural($count, '1 image moved to selected album.', '@count images moved to selected album.');
      drupal_set_message($message);
    }
  }

}
