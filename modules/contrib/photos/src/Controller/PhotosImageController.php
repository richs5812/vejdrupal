<?php

/**
 * @file
 * Definition of Drupal\photos\PhotosImageController.
 */

namespace Drupal\photos\Controller;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PhotosImageController {

  /**
   * Set page title.
   */
  public function getTitle() {
    // Get node object.
    $fid = \Drupal::routeMatch()->getParameter('file');
    $title = db_query("SELECT title FROM {photos_image} WHERE fid = :fid", array(':fid' => $fid))->fetchField();
    return $title;
  }

  /**
   * A custom access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   */
  public function access(AccountInterface $account) {
    // Check if user can view account photos.
    $fid = \Drupal::routeMatch()->getParameter('file');
    if (_photos_access('imageView', $fid)) {
      // Allow access.
      return AccessResult::allowed();
    }
    else {
      return AccessResult::forbidden();
    }
  }

  /**
   * Returns content for single image.
   *
   * @return string
   *   A HTML-formatted string with the administrative page content.
   *
   */
  public function contentOverview() {
    $fid = \Drupal::routeMatch()->getParameter('file');
    if (!is_numeric($fid)) {
      throw new NotFoundHttpException();
    }
    $user = \Drupal::currentUser();
    $query = db_select('file_managed', 'f');
    $query->join('photos_image', 'p', 'p.fid = f.fid');
    $query->join('photos_album', 'a', 'p.pid = a.pid');
    $query->join('node', 'n', 'n.nid = p.pid');
    $query->join('users_field_data', 'u', 'u.uid = f.uid');
    $query->fields('f', array('uri', 'filemime', 'created', 'filename'))
      ->fields('p')
      ->fields('a', array('data'))
      ->fields('u', array('uid', 'name'));
    $query->condition('p.fid', $fid);
    $query->addTag('node_access');
    $image = $query->execute()->fetchObject();

    if (!$image) {
      throw new NotFoundHttpException();
    }
    $image = photos_get_info(0, $image);
    $node = \Drupal::entityManager()->getStorage('node')->load($image->pid);
    if (_photos_access('imageEdit', $node)) {
      $image->ajax['edit_url'] = Url::fromUri('base:photos/image/' . $image->fid . '/update')->toString();
      if (_photos_select_sub_album()) {
        // Add image to sub-album.
        $url = Url::fromUri('base:photos/image/' . $image->fid . '/to_sub');
        $image->links['to_sub'] = l(t('Add to sub-album...'), $url, array(
          'attributes' => array(
            'class' => array('colorbox')
          )
        ));
      }
      // Set album cover.
      $url = Url::fromRoute('photos.album.update.cover', array('node' => $image->pid, 'file' => $fid));
      $image->links['cover'] = \Drupal::l(t('Set to Cover'), $url, array(
        'query' => drupal_get_destination())
      );
    }
    $image->class = array(
      'title_class' => '',
      'des_class' => '',
    );
    $image->id = array(
      'des_edit' => '',
      'title_edit' => ''
    );
    $edit = _photos_access('imageEdit', $node);
    if ($edit) {
      // Image edit link.
      $url = Url::fromUri('base:photos/image/' . $image->fid . '/edit');
      $image->ajax['edit_link'] = \Drupal::l(t('Edit'), $url, array(
        'query' => array(
          'destination' => 'photos/image/' . $image->fid
        ),
        'attributes' => array(
          'class' => array('colorbox-load', 'photos-edit-edit')
        )
      ));

      $image->class = array(
        'title_class' => ' jQueryeditable_edit_title',
        'des_class' => ' jQueryeditable_edit_des',
      );
      $image->id = array(
        'des_edit' => ' id="photos-image-edit-des-' . $image->fid . '"',
        'title_edit' => ' id="photos-image-edit-title-' . $image->fid . '"'
      );
      $jeditable_library = \Drupal::service('library.discovery')->getLibraryByName('photos', 'photos.jeditable');
    }
    if (_photos_access('imageDelete', $node)) {
      // Image delete link.
      $url = Url::fromUri('base:photos/image/' . $image->fid . '/delete');
      $image->ajax['del_link'] = \Drupal::l(t('Delete'), $url, array(
        'query' => array(
          'destination' => 'node/' . $image->pid
        ),
        'attributes' => array(
          'class' => array('colorbox-load', 'photos-edit-delete')
        )
      ));
    }
    if (\Drupal::config('photos.settings')->get('photos_vote')) {
      // @todo votingapi.
      $render_vote = array(
        '#theme' => 'photos_vote',
        '#fid' => $fid
      );
      // $image->vote = $render_vote;
    }
    if (\Drupal::config('photos.settings')->get('photos_comment')) {
      // Comment integration.
      $render_comment = array(
        '#theme' => 'photos_comment_count',
        '#comcount' => $image->comcount
      );
      $image->links['comment'] = $render_comment;
    }
    // @todo $uid?
    if (FALSE && $uid) {
      // User images.
      $pager_type = 'uid';
      $pager_id = $uid;
    }
    elseif (isset($_GET['photos_sub'])) {
      // Sub-album images.
      $pager_type = 'sub';
      $pager_id = (int)$_GET['photos_sub'];
    }
    else {
      // Album images.
      $pager_type = 'pid';
      $pager_id = $image->pid;
    }
    $data = unserialize($image->data);
    $style_name = isset($data['view_imagesize']) ? $data['view_imagesize'] : \Drupal::config('photos.settings')->get('photos_display_view_imagesize');

    // Necessary when upgrading from D6 to D7.
    // @todo remove?
    $image_styles = image_style_options(FALSE);
    if (!isset($image_styles[$style_name])) {
      $style_name = \Drupal::config('photos.settings')->get('photos_display_view_imagesize');
    }

    // Display all sizes link to share code?
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
      $url = Url::fromUri('base:photos/zoom/' . $fid);
      $image->links['more'] = \Drupal::l(t('All sizes'), $url, $colorbox);
    }
    $image->links['pager'] = $this->imagePager($fid, $pager_id, $pager_type);
    $image->view = array(
      '#theme' => 'photos_image_html',
      '#style_name' => $style_name,
      '#image' => $image,
      '#cache' => array(
        'tags' => array(
          'photos:image:' . $fid
        )
      )
    );

    // Get comments.
    $image->comment['view'] = _photos_comment($fid, $image->comcount, $node);
    if (!\Drupal::config('photos.settings')->get('photos_image_count')) {
      $count = 1;
      db_update('photos_image')
        ->fields(array('count' => $count))
        ->expression('count', 'count + :count', array(':count' => $count))
        ->condition('fid', $fid)
        ->execute();
    }
    $image->title = \Drupal\Component\Utility\SafeMarkup::checkPlain($image->title);
    $image->des = \Drupal\Component\Utility\SafeMarkup::checkPlain($image->des);

    $GLOBALS['photos'][$image->fid . '_pid'] = $image->pid;

    $image_view = array(
      '#theme' => 'photos_image_view',
      '#image' => $image,
      '#display_type' => 'view',
      '#cache' => array(
        'tags' => array(
          'photos:image:' . $fid
        )
      )
    );
    // Check for Jeditable library.
    // @todo move to static public function?
    if ($edit && isset($jeditable_library['js']) && file_exists($jeditable_library['js'][0]['data'])) {
      $image_view['#attached']['library'][] = 'photos/photos.jeditable';
    }

    return $image_view;
  }

  /**
   * Photos image view pager block.
   */
  public static function imagePager($fid, $id, $type = 'pid') {
    $query = db_select('file_managed', 'f');
    $query->join('photos_image', 'p', 'f.fid = p.fid');
    $query->fields('p', array('pid'))
      ->fields('f', array('fid', 'uri', 'filename'));

    // Default order by fid.
    $order = array('column' => 'f.fid', 'sort' => 'DESC');
    if ($type == 'pid') {
      // Viewing album.
      // Order images by album settings.
      $album_data = db_query('SELECT data FROM {photos_album} WHERE pid = :pid', array(':pid' => $id))->fetchField();
      $album_data = unserialize($album_data);
      $default_order = \Drupal::config('photos.settings')->get('photos_display_imageorder');
      $image_order = isset($album_data['imageorder']) ? $album_data['imageorder'] : $default_order;
      $order = explode('|', $image_order);
      $order = _photos_order_value_change($order[0], $order[1]);
      $query->condition('p.pid', $id);
    }
    elseif ($type == 'uid') {
      // Viewing all user images.
      $query->condition('f.uid', $id);
    }
    elseif ($type == 'sub') {
      // Viewing sub-album.
      // Default order by wid.
      $order = array('column' => 's.wid', 'sort' => 'ASC');
      $query->join('photos_node', 's', 's.fid = p.fid');
      $query->condition('s.nid', $id);
    }
    else {
      // Show all.
    }
    $query->orderBy($order['column'], $order['sort']);
    $result = $query->execute();

    $stop = $t['prev'] = $t['next'] = 0;
    $num = 0;
    foreach ($result as $image) {
      $num++;
      if ($stop == 1) {
        $t['next'] = $image;
        $t['next_view'] = photos_get_info(0, $t['next'], array('style_name' => \Drupal::config('photos.settings')->get('photos_pager_imagesize')));
        if ($type == 'sub') {
          // Sub-album next image.
          $url = Url::fromUri('base:photos/image/' . $image->fid);
          $t['next_url'] = url($url, array('query' => array('photos_sub' => $id)));
        }
        else {
          // Next image.
          $t['next_url'] = Url::fromUri('base:photos/image/' . $image->fid)->toString();
        }
        break;
      }
      if ($image->fid == $fid) {
        $t['current'] = $image;
        $t['current_view'] = photos_get_info(0, $t['current'], array('style_name' => \Drupal::config('photos.settings')->get('photos_pager_imagesize')));
        $stop = 1;
      }
      else {
        $t['prev'] = $image;
      }
    }
    if ($t['prev']) {
      $t['prev_view'] = photos_get_info(0, $t['prev'], array('style_name' => \Drupal::config('photos.settings')->get('photos_pager_imagesize')));
      if ($type == 'sub') {
        // Sub-album previous image.
        $url = Url::fromUri('base:photos/image/' . $t['prev']->fid);
        $t['prev_url'] = url($url, array('query' => array('photos_sub' => $id)));
      }
      else {
        // Previous image.
        $t['prev_url'] = Url::fromUri('base:photos/image/' . $t['prev']->fid)->toString();
      }
    }

    return $t;
  }

}
