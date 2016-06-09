<?php

/**
 * @file
 * Contains \Drupal\photos\Form\PhotosManagementForm.
 */

namespace Drupal\photos\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form to configure maintenance settings for this site.
 */
class PhotosManagementForm implements FormInterface {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'photos_management';
  }

  /**
   * Get album images by $node->id().
   * @todo move function so it is easily accessible.
   */
  public function getAlbumImages($nid, $limit = 10) {
    $images = array();
    $column = isset($_GET['field']) ? \Drupal\Component\Utility\Html::escape($_GET['field']) : '';
    $sort = isset($_GET['sort']) ? \Drupal\Component\Utility\Html::escape($_GET['sort']) : '';
    $term = _photos_order_value($column, $sort, $limit, array('column' => 'p.wid', 'sort' => 'asc'));
    $query = db_select('file_managed', 'f')
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender');
    $query->join('photos_image', 'p', 'p.fid = f.fid');
    $query->join('users_field_data', 'u', 'f.uid = u.uid');
    $query->join('node', 'n', 'n.nid = p.pid');
    $query->fields('f', array('uri', 'filemime', 'created', 'filename', 'filesize'));
    $query->fields('p');
    $query->fields('u', array('uid', 'name'));
    $query->condition('p.pid', $nid);
    $query->limit($term['limit']);
    $query->orderBy($term['order']['column'], $term['order']['sort']);
    $query->addTag('node_access');
    $result = $query->execute();
    foreach ($result as $data) {
      // @todo create new function to return image object.
      $images[] = photos_get_info(0, $data);
    }
    if (isset($images[0]->fid)) {
      $node = \Drupal::entityManager()->getStorage('node')->load($nid);
      $images[0]->info = array(
        'pid' => $node->id(),
        'title' => $node->getTitle(),
        'uid' => $node->getOwnerId()
      );
      if (isset($node->album['cover'])) {
        $images[0]->info['cover'] = $node->album['cover'];
      }
    }
    return $images;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // @todo move to PhotosUploadForm.php.
    // @todo create controller to combine upload form & edit list form.
    $account = \Drupal::currentUser();
    $images = array();
    $type = 'album';
    if ($type == 'album') {
      $album_update = '';
      // Get node object.
      // $node = \Drupal::request()->attributes->get('node');
      $nid = \Drupal::routeMatch()->getParameter('node');
      $node =  \Drupal\node\Entity\Node::load($nid);

      $images = $this->getAlbumImages($nid);

      /*
      drupal_set_message('Images: <pre>' . print_r($images, 1) . '</pre>');

      $uid = $account->id();
      // Options to select photo album (not needed on node edit page).
      // $album_pid = _photos_useralbum_option($uid, $album_update);
      $del_label = ''; // _photos_del_checkbox(0, t('Delete'));
      if (isset($images[0]->fid)) {
        $form['cover_fid'] = array('#type' => 'hidden', '#default_value' => $images[0]->fid);
      }
      $form['oldpid'] = array('#type' => 'hidden', '#default_value' => $nid);
      $submit = 'photos_editlist_submit';
    }
    else {
      $del_label = ''; // _photos_del_checkbox(0, t('Move out'));
      $submit = 'photos_editlist_submit_node';
      $form['nid'] = array('#type' => 'hidden', '#default_value' => $images[0]->info['nid']);
    }
    $form['photos']['#tree'] = TRUE;
    foreach ($images as $image) {
      $form['photos'][$image->fid]['del'] = $del_label;
      $image->user = user_load($image->uid);
      $image->href = 'photos/image/' . $image->fid;
      $item = array();
      $title = $image->title;
      $style_name = variable_get('photos_size_0', 'thumbnail');
      // $item[] = l(theme('image_style', array('style_name' => $style_name, 'path' => $image->uri, 'alt' => $title, 'title' => $title)), $image->href, array('html' => TRUE, 'attributes' => array('title' => $title)));
      if ($type == 'album' && $images[0]->fid <> $image->fid) {
        $item[] = l(t('Set to Cover'), 'node/' . $image->pid . '/photos/cover/' . $image->fid);
      }
      if (isset($image->filesize)) {
        $item[] = t('Filesize: !size KB', array('!size' => round($image->filesize/1024)));
      }
      if (isset($image->count)) {
        $item[] = t('Visits: !count', array('!count' => $image->count));
      }
      if (isset($image->comcount)) {
        $item[] = t('Comments: !count', array('!count' => $image->comcount));
      }
      // $form['photos'][$image->fid]['path']['#markup'] = theme('item_list', array('items' => $item));
      $form['photos'][$image->fid]['des'] = array(
        '#title' => t('Image description'),
        '#type' => 'textarea',
        '#default_value' => isset($image->des) ? $image->des : '',
        '#cols' => 40,
        '#rows' => 4
      );
      $form['photos'][$image->fid]['title'] = array(
        '#title' => t('Image title'),
        '#type' => 'textfield',
        '#default_value' => isset($image->title) ? $image->title : '',
        '#required' => FALSE
      );
      $form['photos'][$image->fid]['wid'] = array(
        '#title' => t('Weight'),
        '#type' => 'textfield',
        '#size' => 5,
        '#default_value' => isset($image->wid) ? $image->wid : NULL,
      );
      $form['photos'][$image->fid]['filepath'] = array('#type' => 'value', '#value' => $image->uri);
      if ($type == 'album') {
        // $upload_info = t('Uploaded on !time by !name', array('!name' => theme('username', array('account' => $image->user)), '!time' => format_date($image->timestamp, 'small')));
        $form['photos'][$image->fid]['pid'] = array(
          '#title' => t('Move to the album'),
          '#type' => 'select',
          '#options' => $album_pid,
          '#default_value' => $image->pid,
          '#required' => TRUE
        );
      }
      else {
        $upload_info = t('!name in !time upload to !title', array('!name' => theme('username', array('account' => $image->user)), '!time' => format_date($image->timestamp, 'small'), '!title' => l($image->title, 'node/' . $image->pid)));
      }
      $form['photos'][$image->fid]['time']['#markup'] = $upload_info;
      $form['photos'][$image->fid]['uid'] = array('#type' => 'hidden', '#default_value' => $image->uid);
      $form['photos'][$image->fid]['oldtitle'] = array('#type' => 'hidden', '#default_value' => $image->title);
    };
    if (!empty($images)) {
      $form['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Confirm changes'),
        '#submit' => array($submit),
      );
      */
    }

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
    $files_uploaded = array();
    if (\Drupal::config('photos.settings')->get('photos_plupload_status')) {
      $nid = $form_state->getValue('nid');
      $album_uid = db_query("SELECT uid FROM {node_field_data} WHERE nid = :nid", array(':nid' => $nid))->fetchField();
      if (empty($album_uid)) {
        $album_uid = $user->id();
      }
      // \Drupal\user\Entity\User::load($album_uid);
      $account = \Drupal::entityManager()->getStorage('user')->load($album_uid);
      $plupload_files = $form_state->getValue('plupload');
      foreach ($plupload_files as $uploaded_file) {
        if ($uploaded_file['status'] == 'done') {
          // Check for zip files.
          $ext = \Drupal\Component\Utility\Unicode::substr($uploaded_file['name'], -3);
          if ($ext <> 'zip' && $ext <> 'ZIP') {
            $photos_path = photos_check_path('default', '', $account);
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
              }
              else {
                file_delete($file_uri);
                \Drupal::logger('photos')->notice('Wrong file type', []);
              }
            }
            else {
              \Drupal::logger('photos')->notice('Upload error. Could not move temp file.', []);
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
              $value = new stdClass();
              $value->pid = $form_state->getValue('pid');
              $value->nid = $form_state->getValue('nid');
              $value->title = $uploaded_file['name'];
              $value->des = '';
              if (!$msg = _photos_unzip($zip, $value)) {
                drupal_set_message(t('Zip upload failed.'), 'error');
              }
            }
          }
        }
        else {
          drupal_set_message(t('Error uploading some photos.'), 'error');
        }
      }
    }
  }

}
