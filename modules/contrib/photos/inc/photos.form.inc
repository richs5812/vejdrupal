<?php
/**
 * @file
 * Handles album settings form.
 */

/**
 * Node album settings form.
 */
function _photos_form($node) {
  // @todo re-write as form alter.
  $user = \Drupal::currentUser();
  if ($user->id() <> 1) {
    $t = photos_user_count();
    $msg = t('You may create @a albums, now you have created @b albums, you can still create @aa albums.', array('@a' => $t['total'], '@b' => $t['create'], '@aa' => $t['remain']));
    // @todo update deprecated arg().
    if (isset($t['rest']) && arg(2) <> 'edit') {
      drupal_set_message($msg);
      $redirect_url = Url::fromUri('base:user/' . $user->id())->toString();
      return new RedirectResponse($redirect_url);
    }
    $form['help'] = array('#value' => $msg, '#weight' => -10);
  }

  $photos_global = \Drupal::config('photos.settings')->get('photos_display_user');
  $photos_page = \Drupal::config('photos.settings')->get('photos_display_page_user');
  $photos_teaser = \Drupal::config('photos.settings')->get('photos_display_teaser_user');
  $size = photos_upload_info(0);
  $image_styles = image_style_options(FALSE);
  $list_imagesize = \Drupal::config('photos.settings')->get('photos_display_list_imagesize');
  $view_imagesize = \Drupal::config('photos.settings')->get('photos_display_view_imagesize');
  $full_imagesize = \Drupal::config('photos.settings')->get('photos_display_full_imagesize');
  $teaser_imagesize = \Drupal::config('photos.settings')->get('photos_display_teaser_imagesize');

  if (isset($node->album['list_imagesize'])) {
    $style_name = $node->album['list_imagesize'];
    // Necessary when upgrading from D6 to D7.
    if (isset($image_styles[$style_name])) {
      $list_imagesize = $style_name;
    }
  }

  if (isset($node->album['view_imagesize'])) {
    $style_name = $node->album['view_imagesize'];
    // Necessary when upgrading from D6 to D7.
    if (isset($image_styles[$style_name])) {
      $view_imagesize = $style_name;
    }
  }

  if (isset($node->album['full_imagesize'])) {
    $style_name = $node->album['full_imagesize'];
    // Necessary when upgrading from D6 to D7.
    if (isset($image_styles[$style_name])) {
      $full_imagesize = $style_name;
    }
  }

  if (isset($node->album['teaser_imagesize'])) {
    $style_name = $node->album['teaser_imagesize'];
    // Necessary when upgrading from D6 to D7.
    if (isset($image_styles[$style_name])) {
      $teaser_imagesize = $style_name;
    }
  }

  if ($photos_global || $photos_page || $photos_teaser) {
    $form['photos'] = array(
      '#type' => 'details',
      '#title' => t('Album settings'),
      '#open' => FALSE,
      '#weight' => 20
    );
    $opt = array(t('Do not display'), t('Display cover'), t('Display thumbnails'));
    if ($photos_global) {
      $form['photos']['global']['album'] = array(
        '#type' => 'details',
        '#title' => t('Global Settings'),
        '#tree' => TRUE
      );
      $form['photos']['global']['album']['viewpager'] = array(
        '#type' => 'number',
        '#title' => t('Images per page'),
        '#default_value' => isset($node->album['viewpager']) ? $node->album['viewpager'] : \Drupal::config('photos.settings')->get('photos_display_viewpager'),
        '#required' => TRUE,
        '#min' => 1,
        '#step' => 1
      );
      $form['photos']['global']['album']['imageorder'] = array(
        '#type' => 'select',
        '#title' => t('Image display order'),
        '#required' => TRUE,
        '#default_value' => isset($node->album['imageorder']) ? $node->album['imageorder'] : \Drupal::config('photos.settings')->get('photos_display_imageorder'),
        '#options' => _photos_order_label()
      );

      $form['photos']['global']['album']['list_imagesize'] = array(
        '#type' => 'select',
        '#title' => t('Image size (list)'),
        '#required' => TRUE,
        '#default_value' => $list_imagesize,
        '#description' => t('Displayed in the list(e.g: photos/album/[nid]) of image size.'),
        '#options' => _photos_select_size()
      );
      $form['photos']['global']['album']['view_imagesize'] = array(
        '#type' => 'select',
        '#title' => t('Image size (page)'),
        '#required' => TRUE,
        '#default_value' => $view_imagesize,
        '#description' => t('Displayed in the page(e.g: photos/image/[fid]) of image size.'),
        '#options' => _photos_select_size()
      );
    }
    else {
      $form['photos']['global']['album'] = array(
        '#type' => 'value',
        '#value' => 'album',
        '#tree' => TRUE
      );
      $form['photos']['global']['album']['viewpager'] = array(
        '#type' => 'value',
        '#value' => isset($node->album['viewpager']) ? $node->album['viewpager'] : \Drupal::config('photos.settings')->get('photos_display_viewpager'),
      );
      $form['photos']['global']['album']['imageorder'] = array(
        '#type' => 'value',
        '#value' => isset($node->album['imageorder']) ? $node->album['imageorder'] : \Drupal::config('photos.settings')->get('photos_display_imageorder'),
      );
      $form['photos']['global']['album']['list_imagesize'] = array(
        '#type' => 'value',
        '#value' => $list_imagesize,
      );
      $form['photos']['global']['album']['view_imagesize'] = array(
        '#type' => 'value',
        '#value' => $view_imagesize,
      );
    }

    if ($photos_page) {
      // @todo add library.
      // $form['#attached']['js'][] = drupal_get_path('module', 'photos') . '/js/min/photos_form_full.min.js';
      $form['photos']['page']['album'] = array(
        '#type' => 'details',
        '#title' => t('Page Settings'),
        '#tree' => TRUE,
        '#prefix' => '<div id="photos-form-page">',
        '#suffix' => '</div>'
      );
      $form['photos']['page']['album']['page_display'] = array(
        '#type' => 'radios',
        '#default_value' => isset($node->album['page_display']) ? $node->album['page_display'] : \Drupal::config('photos.settings')->get('photos_display_page_display'),
        '#title' => t('Display setting'),
        '#required' => TRUE,
        '#options' => $opt
      );
      $form['photos']['page']['album']['full_viewnum'] = array(
        '#type' => 'number',
        '#default_value' => isset($node->album['full_viewnum']) ? $node->album['full_viewnum'] : \Drupal::config('photos.settings')->get('photos_display_full_viewnum'),
        '#title' => t('Quantity'),
        '#required' => TRUE,
        '#min' => 1,
        '#step' => 1,
        '#prefix' => '<div class="photos-form-count">'
      );
      $form['photos']['page']['album']['full_imagesize'] = array(
        '#type' => 'select',
        '#title' => t('Image size'),
        '#required' => TRUE,
        '#default_value' => $full_imagesize,
        '#options' => _photos_select_size(),
        '#suffix' => '</div>'
      );
    }
    else {
      $form['photos']['page']['album'] = array(
        '#type' => 'value',
        '#value' => 'page',
        '#tree' => TRUE,
      );
      $form['photos']['page']['album']['page_display'] = array(
        '#type' => 'value',
        '#value' => isset($node->album['page_display']) ? $node->album['page_display'] : \Drupal::config('photos.settings')->get('photos_display_page_display'),
      );
      $form['photos']['page']['album']['full_viewnum'] = array(
        '#type' => 'value',
        '#value' => isset($node->album['full_viewnum']) ? $node->album['full_viewnum'] : \Drupal::config('photos.settings')->get('photos_display_full_viewnum'),
      );
      $form['photos']['page']['album']['full_imagesize'] = array(
        '#type' => 'value',
        '#value' => $full_imagesize,
      );
    }

    if ($photos_teaser) {
      // @todo add library.
      // $form['#attached']['js'][] = drupal_get_path('module', 'photos') . '/js/min/photos_form_teaser.min.js';
      $form['photos']['teaser']['album'] = array(
        '#type' => 'details',
        '#title' => t('Teaser Settings'),
        '#tree' => TRUE,
        '#prefix' => '<div id="photos-form-teaser">',
        '#suffix' => '</div>'
      );
      $form['photos']['teaser']['album']['teaser_display'] = array(
        '#type' => 'radios',
        '#default_value' => isset($node->album['teaser_display']) ? $node->album['teaser_display'] : \Drupal::config('photos.settings')->get('photos_display_teaser_display'),
        '#title' => t('Display setting'),
        '#required' => TRUE,
        '#options' => $opt
      );
      $form['photos']['teaser']['album']['teaser_viewnum'] = array(
        '#type' => 'number',
        '#default_value' => isset($node->album['teaser_viewnum']) ? $node->album['teaser_viewnum'] : \Drupal::config('photos.settings')->get('photos_display_teaser_viewnum'),
        '#title' => t('Quantity'),
        '#required' => TRUE,
        '#min' => 1,
        '#step' => 1,
        '#prefix' => '<div class="photos-form-count">'
      );
      $form['photos']['teaser']['album']['teaser_imagesize'] = array(
        '#type' => 'select',
        '#title' => t('Image size'),
        '#required' => TRUE,
        '#default_value' => $teaser_imagesize,
        '#options' => _photos_select_size(),
        '#suffix' => '</div>'
      );
    }
    else {
      $form['photos']['teaser']['album'] = array(
        '#type' => 'value',
        '#value' => 'teaser',
        '#tree' => TRUE,
      );
      $form['photos']['teaser']['album']['teaser_display'] = array(
        '#type' => 'value',
        '#value' => isset($node->album['teaser_display']) ? $node->album['teaser_display'] : \Drupal::config('photos.settings')->get('photos_display_teaser_display'),
      );
      $form['photos']['teaser']['album']['teaser_viewnum'] = array(
        '#type' => 'value',
        '#value' => isset($node->album['teaser_viewnum']) ? $node->album['teaser_viewnum'] : \Drupal::config('photos.settings')->get('photos_display_teaser_viewnum'),
      );
      $form['photos']['teaser']['album']['teaser_imagesize'] = array(
        '#type' => 'value',
        '#value' => $teaser_imagesize,
      );
    }
  }
  else {
    $form['photos']['global']['album'] = array(
      '#type' => 'value',
      '#value' => 'global',
      '#tree' => TRUE
    );
    $form['photos']['global']['album']['viewpager'] = array(
      '#type' => 'value',
      '#value' => isset($node->album['viewpager']) ? $node->album['viewpager'] : \Drupal::config('photos.settings')->get('photos_display_viewpager'),
    );
    $form['photos']['global']['album']['imageorder'] = array(
      '#type' => 'value',
      '#value' => isset($node->album['imageorder']) ? $node->album['imageorder'] : \Drupal::config('photos.settings')->get('photos_display_imageorder'),
    );
    $form['photos']['global']['album']['list_imagesize'] = array(
      '#type' => 'value',
      '#value' => $list_imagesize,
    );
    $form['photos']['global']['album']['view_imagesize'] = array(
      '#type' => 'value',
      '#value' => $view_imagesize,
    );
    $form['photos']['page']['album'] = array(
      '#type' => 'value',
      '#value' => 'page',
      '#tree' => TRUE,
    );
    $form['photos']['page']['album']['page_display'] = array(
      '#type' => 'value',
      '#value' => isset($node->album['page_display']) ? $node->album['page_display'] : \Drupal::config('photos.settings')->get('photos_display_page_display'),
    );
    $form['photos']['page']['album']['full_viewnum'] = array(
      '#type' => 'value',
      '#value' => isset($node->album['full_viewnum']) ? $node->album['full_viewnum'] : \Drupal::config('photos.settings')->get('photos_display_full_viewnum'),
    );
    $form['photos']['page']['album']['full_imagesize'] = array(
      '#type' => 'value',
      '#value' => $full_imagesize,
    );
    $form['photos']['teaser']['album'] = array(
      '#type' => 'value',
      '#value' => 'teaser',
      '#tree' => TRUE,
    );
    $form['photos']['teaser']['album']['teaser_display'] = array(
      '#type' => 'value',
      '#value' => isset($node->album['teaser_display']) ? $node->album['teaser_display'] : \Drupal::config('photos.settings')->get('photos_display_teaser_display'),
    );
    $form['photos']['teaser']['album']['teaser_viewnum'] = array(
      '#type' => 'value',
      '#value' => isset($node->album['teaser_viewnum']) ? $node->album['teaser_viewnum'] : \Drupal::config('photos.settings')->get('photos_display_teaser_viewnum'),
    );
    $form['photos']['teaser']['album']['teaser_imagesize'] = array(
      '#type' => 'value',
      '#value' => $teaser_imagesize,
    );
  }

  $pid = isset($node->album['pid']) ? $node->album['pid'] : NULL;
  $form['photos']['page']['album']['pid'] = array(
    '#type' => 'value',
    '#value' => $pid
  );

  return $form;
}
