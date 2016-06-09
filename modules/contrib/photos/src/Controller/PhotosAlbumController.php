<?php

/**
 * @file
 * Definition of Drupal\photos\PhotosAlbumController.
 */

namespace Drupal\photos\Controller;

use Drupal\Core\Url;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PhotosAlbumController {

  /**
   * Set page title.
   */
  public function getTitle() {
    // Get node object.
    $nid = \Drupal::routeMatch()->getParameter('node');
    $node = \Drupal\node\Entity\Node::load($nid);
    // @todo add sub-album.
    $title = 'Album: ' . $node->getTitle();
    return $title;
  }

  /**
   * A custom access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   */
  public function access(AccountInterface $account) {
    // Get node.
    $nid = \Drupal::routeMatch()->getParameter('node');
    $node = \Drupal\node\Entity\Node::load($nid);
    if (!$node) {
      // Not found.
      throw new NotFoundHttpException();
    }
    $current_path = \Drupal::service('path.current')->getPath();
    $path_args = explode('/', $current_path);
    // Check access.
    $access_op = 'album';
    if (isset($path_args[3]) && $path_args[3] == 'sub_album') {
      $access_op = 'subAlbum';
    }
    if ($account->hasPermission('view photo') && _photos_access($access_op, $node)) {
      // Allow access.
      return AccessResult::allowed();
    }
    else {
      return AccessResult::forbidden();
    }
  }

  /**
   * Returns an overview of recent albums and photos.
   *
   * @return string
   *   A HTML-formatted string with the administrative page content.
   *
   */
  public function albumView() {
    // Get node object.
    $album = array();
    $nid = \Drupal::routeMatch()->getParameter('node');
    $node =  \Drupal\node\Entity\Node::load($nid);
    // Get order or set default order.
    $order = explode('|', (isset($node->album['imageorder']) ? $node->album['imageorder'] : \Drupal::config('photos.settings')->get('photos_display_imageorder')));
    $order = _photos_order_value_change($order[0], $order[1]);
    $limit = isset($node->album['full_viewnum']) ? $node->album['full_viewnum'] : \Drupal::config('photos.settings')->get('photos_display_viewpager');
    $column = isset($_GET['field']) ? \Drupal\Component\Utility\Html::escape($_GET['field']) : '';
    $sort = isset($_GET['sort']) ? \Drupal\Component\Utility\Html::escape($_GET['sort']) : '';
    $term = _photos_order_value($column, $sort, $limit, $order);
    // Album image's query.
    $query = db_select('file_managed', 'f')->extend('Drupal\Core\Database\Query\PagerSelectExtender');
    $query->join('photos_image', 'p', 'p.fid = f.fid');
    $query->join('users_field_data', 'u', 'u.uid = f.uid');
    $query->fields('f', array('uri', 'filemime', 'created', 'filename', 'filesize'))
      ->fields('p')
      ->fields('u', array('uid', 'name'))
      ->condition('p.pid', $nid)
      ->orderBy($term['order']['column'], $term['order']['sort'])
      ->limit($term['limit']);
    $result = $query->execute();

    // Check vote and comment settings.
    $com = \Drupal::config('photos.settings')->get('photos_comment');
    $vote = \Drupal::config('photos.settings')->get('photos_vote');
    // Check node access.
    $edit = $node->access('update');
    $del = $node->access('delete');
    $style_name = isset($node->album['list_imagesize']) ? $node->album['list_imagesize'] : \Drupal::config('photos.settings')->get('photos_display_list_imagesize');
    // Necessary when upgrading from D6 to D7.
    $image_styles = image_style_options(FALSE);
    if (!isset($image_styles[$style_name])) {
      $style_name = \Drupal::config('photos.settings')->get('photos_display_list_imagesize');
    }

    // Process images.
    foreach ($result as $data) {
      $image = photos_get_info(0, $data);
      $image->title = \Drupal\Component\Utility\SafeMarkup::checkPlain($image->title);
      $image->des = \Drupal\Component\Utility\SafeMarkup::checkPlain($image->des);

      $title = $image->title;

      // Build image view.
      $image_view_array = array(
        '#theme' => 'photos_image_html',
        '#style_name' => $style_name,
        '#image' => $image
      );
      $image->view = $image_view_array;

      // Image link.
      $image->url = Url::fromUri('base:photos/image/' . $image->fid)->toString();

      if ($com) {
        $image->links['comment'] = array(
          '#theme' => 'photos_comment_count',
          '#comcount' => $image->comcount,
          '#url' => $image->url
        );
      }
      if ($image->count) {
        $image->links['count'] = \Drupal::translation()->formatPlural($image->count, '@count visit', '@count visits', array('@count' => $image->count));
      }
      $image->links['info'] = t('Uploaded on @time by @name', array(
        '@name' => $image->name,
        '@time' => \Drupal::service('date.formatter')->format($image->created, 'short')
      ));

      $image->class = array(
        'title_class' => '',
        'des_class' => '',
      );
      $image->id = array(
        'des_edit' => '',
        'title_edit' => ''
      );
      // Edit links.
      if ($edit) {
        $destination = drupal_get_destination();
        $image->ajax['edit_url'] = $image->url . '/update';
        // Edit link.
        $url = Url::fromUri('base:photos/image/' . $image->fid . '/edit');
        $image->ajax['edit_link'] = \Drupal::l(t('Edit'), $url, array(
            'query' => array(
            'destination' => $destination['destination'],
            'pid' => $nid,
            'uid' => $image->uid
          ),
          'attributes' => array(
            'class' => array('colorbox-load', 'photos-edit-edit')
          )
        ));

        // Jeditable inline editing integration.
        // @todo add setting to enable Jeditable?
        $image->class = array(
          'title_class' => ' jQueryeditable_edit_title',
          'des_class' => ' jQueryeditable_edit_des',
        );
        $image->id = array(
          'des_edit' => ' id="photos-image-edit-des-' . $image->fid . '"',
          'title_edit' => ' id="photos-image-edit-title-' . $image->fid . '"'
        );
        $jeditable_library = \Drupal::service('library.discovery')->getLibraryByName('photos', 'photos.jeditable');

        // Link to update album cover.
        $url = Url::fromRoute('photos.album.update.cover', array('node' => $image->pid, 'file' => $image->fid));
        $image->links['cover'] = \Drupal::l(t('Set to Cover'), $url, array('query' => $destination));
      }
      $image->ajax['del_id'] = '';
      if ($del) {
        $image->ajax['del_id'] = 'id="photos_ajax_del_' . $image->fid . '"';
        $destination = drupal_get_destination();
        // Delete link.
        $url = \Drupal\Core\Url::fromUri('base:photos/image/' . $image->fid . '/delete');
        $image->ajax['del_link'] = \Drupal::l(t('Delete'), $url, array(
          'query' => array(
            'destination' => $destination['destination'],
            'pid' => $nid,
            'uid' => $image->uid
          ),
          'attributes' => array(
            'class' => array('colorbox-load', 'photos-edit-delete')
          )
        ));
      }
      if ($vote) {
        // @todo integrate voting API.
        // $image->links['vote'] = theme('photos_vote', array('fid' => $image->fid));
      }

      // Build image view for album.
      // @todo add configurable type (grid etc.).
      $image_view_array = array(
        '#theme' => 'photos_image_view',
        '#image' => $image,
        '#display_type' => 'list',
        '#cache' => array(
          'tags' => array(
            'photos:album:' . $nid,
            'node:' . $nid
          )
        )
      );
      $album['view'][] = $image_view_array;
    }
    if (isset($album['view'][0])) {
      $album['access']['edit'] = $edit;
      // Node edit link.
      $url = \Drupal\Core\Url::fromUri('base:node/' . $nid . '/edit');
      $album['node_edit_url'] = \Drupal::l(t('Album settings'), $url);

      // Image management link.
      $url = \Drupal\Core\Url::fromUri('base:node/' . $nid . '/photos');
      $album['image_management_url'] = \Drupal::l(t('Upload photos'), $url);

      // Album URL.
      $album['album_url'] = Url::fromUri('base:photos/album/' . $nid)->toString();

      $album['links'] = _photos_order_link('photos/album/' . $nid, 0, 0, 1);
      $cover_style_name = \Drupal::config('photos.settings')->get('photos_cover_imagesize');
      if (isset($node->album['cover']['url'])) {
        $image_info = \Drupal::service('image.factory')->get($node->album['cover']['url']);
        // Album cover view.
        $title = $node->getTitle();
        $album_cover_array = array(
          '#theme' => 'image_style',
          '#style_name' => $cover_style_name,
          '#uri' => $node->album['cover']['url'],
          '#width' => $image_info->getWidth(),
          '#height' => $image_info->getHeight(),
          '#alt' => $title,
          '#title' => $title,
          '#cache' => array(
            'tags' => array(
              'photos:album:' . $nid,
              'node:' . $nid
            )
          )
        );
        $album['cover'] = $node->album['cover']['view'];
      }
      $album['pager'] = array(
        '#type' => 'pager'
      );

      // Build album view.
      $album_view_array = array(
        '#theme' => 'photos_album_view',
        '#album' => $album,
        '#node' => $node,
        '#cache' => array(
          'tags' => array(
            'photos:album:' . $nid,
            'node:' . $nid
          )
        )
      );
      // Check for Jeditable library.
      // @todo move to static public function?
      if ($edit && isset($jeditable_library['js']) && file_exists($jeditable_library['js'][0]['data'])) {
        $album_view_array['#attached']['library'][] = 'photos/photos.jeditable';
      }
      $content = $album_view_array;
    }
    else {
      $content = array(
        '#markup' => t('Album is empty'),
        '#cache' => array(
          'tags' => array(
            'photos:album:' . $nid,
            'node:' . $nid
          )
        )
      );
    }

    return $content;
  }

  /**
   * Sub album page view.
   */
  public function subAlbumView($node) {
    if (!is_object($node)) {
      // Load node object.
      $node = \Drupal\node\Entity\Node::load($nid);
    }
    $order = explode('|', \Drupal::config('photos.settings')->get('photos_display_imageorder'));
    $order = _photos_order_value_change($order[0], $order[1]);
    $column = isset($_GET['field']) ? \Drupal\Component\Utility\Html::escape($_GET['field']) : '';
    $sort = isset($_GET['sort']) ? \Drupal\Component\Utility\Html::escape($_GET['sort']) : '';
    $term = _photos_order_value($column, $sort, \Drupal::config('photos.settings')->get('photos_display_viewpager'), $order);
    // Override weight sort for sub albums.
    if ($term['order']['column'] == 'p.wid') $term['order']['column'] = 'a.wid';
    $query = db_select('file_managed', 'f')->extend('Drupal\Core\Database\Query\PagerSelectExtender');
    $query->join('photos_image', 'p', 'p.fid = f.fid');
    $query->join('photos_node', 'a', 'a.fid = f.fid');
    $query->join('node', 'n', 'n.nid = p.pid');
    $query->join('users_field_data', 'u', 'u.uid = f.uid');
    $query->addField('n', 'title', 'album_title');
    $query->fields('f', array('uri', 'filemime', 'created', 'filename', 'filesize'))
      ->fields('p')
      ->fields('a')
      ->fields('u', array('uid', 'name'))
      ->condition('a.nid', $node->id());
    $query->orderBy($term['order']['column'], $term['order']['sort']);
    $query->range(0, $term['limit']);
    $result = $query->execute();

    $com = \Drupal::config('photos.settings')->get('photos_comment');
    $edit = $node->access('update');
    $del = $node->access('delete');
    $vote = \Drupal::config('photos.settings')->get('photos_vote');
    $style_name = \Drupal::config('photos.settings')->get('photos_display_list_imagesize');
    foreach ($result as $data) {
      $image = photos_get_info(0, $data);
      $image->view = array(
        '#theme' => 'photos_image_html',
        '#style_name' => $style_name,
        '#image' => $image
      );

      $image->url = Url::fromUri('base:photos/image/' . $image->fid, array('query' => array('photos_sub' => $image->nid)))->toString();

      if ($com) {
        $image->links['comment'] = array(
          '#theme' => 'photos_comment_count',
          '#comcount' => $image->comcount,
          '#url' => $image->url
        );
      }
      if ($image->count) {
        $image->links['count'] = \Drupal::translation()->formatPlural($image->count, '!cou visit', '!cou visits', array('!cou' => $image->count));
      }
      $image->links['info'] = t('Uploaded by @name on @time to @title', array(
        '@name' => $image->name,
        '@time' => \Drupal::service('date.formatter')->format($image->created, 'short'),
        '@title' => \Drupal::l($image->album_title, Url::fromUri('base:photos/album/' . $image->pid))
      ));

      $image->class = array(
        'title_class' => '',
        'des_class' => '',
      );
      $image->id = array(
        'des_edit' => '',
        'title_edit' => ''
      );
      if ($edit) {
        $image->ajax['edit_url'] = $image->url . '/update';
        // $image->links['cover'] = l(t('Set as Cover'), 'node/' . $image->pid . '/photos/cover/' . $image->fid, array('query' => drupal_get_destination()));
        $image->class = array(
          'title_class' => ' jQueryeditable_edit_title',
          'des_class' => ' jQueryeditable_edit_des',
        );
        $image->id = array(
          'des_edit' => ' id="photos-image-edit-des-' . $image->fid . '"',
          'title_edit' => ' id="photos-image-edit-title-' . $image->fid . '"'
        );
      }
      $image->ajax['del_id'] = '';
      if ($del) {
        $image->ajax['del_id'] = 'id="photos_ajax_del_' . $image->fid . '"';
        $current_path = \Drupal::service('path.current')->getPath();
        $image->ajax['del_link'] = \Drupal::l(t('Move out'), Url::fromUri('base:photos/image/' . $image->fid . '/delete', array(
          'query' => array(
            'destination' => $current_path,
            'type' => 'sub_album',
            'nid' => $node->id()
          ),
          'attributes' => array(
            'class' => 'colorbox-load',
          )
        )));
      }
      if ($vote) {
        $image->links['vote'] = array(
          '#theme' => 'photos_vote',
          '#fid' => $image->fid
        );
      }
      $album['view'][] = array(
        '#theme' => 'photos_image_view',
        '#image' => $image,
        '#display_type' => 'list'
      );
    }
    if (isset($album['view'][0])) {
      $album['node_url'] = Url::fromUri('base:node/' . $node->id())->toString();
      $album['album_url'] = Url::fromUri('base:photos/sub_album/' . $node->id())->toString();
      $album['links'] = _photos_order_link('photos/album/' . $node->id(), $node->subalbum['count'], 0, 1);
      $album['pager'] = array('#type' => 'pager');

      if (isset($node->album['cover']['url'])) {
        $style_name = \Drupal::config('photos.settings')->get('photos_cover_imagesize') ?: 'thumbnail';
        $album_cover = array(
          '#theme' => 'image_style',
          '#style_name' => $style_name,
          '#path' => $node->album['cover']['url'],
          '#alt' => $node->getTitle(),
          '#title' => $node->getTitle()
        );
        $album['cover'] = $album_cover;
      }
      $content = array(
        '#theme' => 'photos_album_view',
        '#album' => $album,
        '#node' => $node
      );
    }
    else {
      $content = t('Sub-Album is empty');
    }

    return $content;
  }
}
