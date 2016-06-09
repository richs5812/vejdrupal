<?php

/**
 * @file
 * Contains \Drupal\photos\Form\PhotosAdminSettingsForm.
 */

namespace Drupal\photos\Form;

// @see https://www.drupal.org/node/2560515
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\PrivateStream;

/**
 * Defines a form to configure maintenance settings for this site.
 */
class PhotosAdminSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'photos_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    // Get variables for default values.
    $config = $this->config('photos.settings');

    // Load custom admin css and js library.
    $form['#attached']['library'] = array(
      'photos/photos.admin'
    );

    $form['basic'] = array(
      '#title' => t('Basic settings'),
      '#weight' => -5,
      '#type' => 'fieldset',
      '#collapsible' => TRUE,
    );

    // Search integration settings.
    $module_search_exists = \Drupal::moduleHandler()->moduleExists('search');
    if ($module_search_exists) {
      $url = \Drupal\Core\Url::fromRoute('entity.search_page.collection');
      $search_admin_link = \Drupal::l(t('enable photo searching'), $url);
      $form['basic']['search'] = array(
         '#markup' => t('Configure and @link', array('@link' => $search_admin_link))
       );
    }

    // Photos access integration settings.
    $module_photos_access_exists = \Drupal::moduleHandler()->moduleExists('photos_access');
    $url = \Drupal\Core\Url::fromRoute('system.modules_list', array(), array('fragment' => 'module-photos-access'));
    $link = \Drupal::l('photos_access', $url);
    $warning_msg = '';
    // Set warning if private file path is not set.
    if (!PrivateStream::basePath() && $config->get('photos_access_photos')) {
      $warning_msg = t('Warning: image files can still be accessed by visiting the direct URL.
        For better security, ask your website admin to setup a private file path.');
    }
    $form['basic']['photos_access_photos'] = array(
      '#type' => 'radios',
      '#title' => t('Privacy settings'),
      '#default_value' => $config->get('photos_access_photos') ?: 0,
      '#description' => $module_photos_access_exists ? $warning_msg : t('Enable the @link module.', array('@link' => $link)),
      '#options' => array(t('Disabled'), t('Enabled')),
      '#required' => TRUE,
      '#disabled' => ($module_photos_access_exists ? FALSE : TRUE)
    );

    // Classic upload form settings.
    $num_options = array(
      1 => 1,
      2 => 2,
      3 => 3,
      4 => 4,
      5 => 5,
      6 => 6,
      7 => 7,
      8 => 8,
      9 => 9,
      10 => 10
    );
    $form['basic']['photos_num'] = array(
      '#type' => 'select',
      '#title' => t('Classic form'),
      '#default_value' => $config->get('photos_num'),
      '#required' => TRUE,
      '#options' => $num_options,
      '#description' => t('Maximum number of upload fields on the classic upload form.'),
    );

    // Plupload integration settings.
    $module_plupload_exists = \Drupal::moduleHandler()->moduleExists('plupload');
    if ($module_plupload_exists) {
      $form['basic']['photos_plupload_status'] = array(
        '#type' => 'checkbox',
        '#title' => t('Use Plupoad for file uploads'),
        '#default_value' => $config->get('photos_plupload_status'),
      );
    }
    else {
      \Drupal::configFactory()->getEditable('photos.settings')->set('photos_plupload_status', 0)->save();
      $form['basic']['photos_plupload_status'] = array(
        '#type' => 'checkbox',
        '#title' => t('Use Plupoad for file uploads'),
        '#disabled' => TRUE,
        '#description' => t('To enable multiuploads and drag&drop upload features, download and install @link module', array(
          '@link' => \Drupal::l(t('Plupload integration'), \Drupal\Core\Url::fromUri('http://drupal.org/project/plupload'))
        )),
      );
    }

    // Photos module settings.
    // @todo token integration.
    $form['basic']['photos_path'] = array(
      '#type' => 'textfield',
      '#title' => t('Path'),
      '#default_value' => $config->get('photos_path'),
      '#description' => t('The path where the files will be saved relative to the files folder.
        Available variables: %uid, %username, %Y, %m, %d.'),
      '#size' => '40',
      '#required' => TRUE,
    );
    $form['basic']['photos_size_max'] = array(
      '#type' => 'textfield',
      '#title' => t('Maximum image resolution'),
      '#default_value' => $config->get('photos_size_max'),
      '#description' => t('The maximum image resolution example: 800x600. If an image toolkit is available the image will be scaled
        to fit within the desired maximum dimensions. Make sure this size is larger than any image styles used.
        Leave blank for no restrictions.'),
      '#size' => '40',
    );
    $form['basic']['photos_print_sizes'] = array(
      '#type' => 'radios',
      '#title' => t('How to display original image and all sizes?'),
      '#default_value' => $config->get('photos_print_sizes'),
      '#required' => TRUE,
      '#options' => array(
        t('Full page'),
        t('I am using colorbox (with Enable Colorbox load)'),
        t('Hide link')
      ),
    );
    $form['basic']['photos_comment'] = array(
      '#type' => 'radios',
      '#title' => t('Comment setting'),
      '#default_value' => $config->get('photos_comment') ?: 0,
      '#description' => t('Enable to comment on single photo. You must also open comments for content type / node.'),
      '#required' => TRUE,
      '#options' => array(t('Disabled'), t('Enabled')),
    );

    // Voting API module integration settings.
    $module_votingapi_exists = \Drupal::moduleHandler()->moduleExists('votingapi');
    $form['basic']['photos_vote'] = array(
      '#type' => 'radios',
      '#title' => t('Images vote'),
      '#default_value' => $config->get('photos_vote') ?: 0,
      '#description' => t('For the image to increase the voting feature, you must first install the votingapi.module.'),
      '#required' => TRUE,
      '#options' => array(t('Disabled'), t('Enabled')),
      '#disabled' => ($module_votingapi_exists ? FALSE : TRUE)
    );
    $form['basic']['photos_upzip'] = array(
      '#type' => 'radios',
      '#title' => t('Allow zip upload'),
      '#default_value' => $config->get('photos_upzip') ?: 0,
      '#description' => t('Will be allowed to upload images compressed into a zip folder.'),
      '#options' => array(t('Disabled'), t('Enabled'))
    );
    // @todo look into transliteration integration D8 core.
    $form['basic']['photos_rname'] = array(
      '#type' => 'radios',
      '#title' => t('Rename image'),
      '#default_value' => $config->get('photos_rname') ?: 0,
      '#description' => t('Rename uploaded image by random numbers, to solve problems with non-ASCII filenames such as Chinese.'),
      '#required' => TRUE,
      '#options' => array(t('Disabled'), t('Enabled')),
    );
    $form['basic']['num'] = array(
      '#title' => t('Number of albums'),
      '#weight' => 10,
      '#type' => 'fieldset',
      '#description' => t('The number of albums a user allowed to create. Administrater is not limited.'),
      '#collapsible' => TRUE,
      '#tree' => TRUE
    );

    $roles = user_roles(TRUE);
    foreach ($roles as $key => $role) {
      $form['basic']['num']['photos_pnum_' . $key] = array(
         '#type' => 'number',
         '#title' => $role->label(),
         '#required' => TRUE,
         '#default_value' => $config->get('photos_pnum_' . $key) ? $config->get('photos_pnum_' . $key) : 20,
         '#min' => 1,
         '#step' => 1,
         '#prefix' => '<div class="photos-admin-inline">',
         '#suffix' => '</div>',
         '#size' => 10
       );
    }
    // Thumb settings.
    if ($size = photos_upload_info(0)) {
      $num = ($size['count'] + 3);
      $sizes = array();
      foreach ($size['size'] as $style => $label) {
        $sizes[] = array(
          'style' => $style,
          'label' => $label
        );
      }
      $size['size'] = $sizes;
    }
    else {
      // @todo remove else or use $size_options?
      $num = 3;
      $size['size'] = array(
        array(
          'style' => 'medium',
          'label' => 'Medium'
        ),
        array(
          'style' => 'large',
          'label' => 'Large'
        ),
        array(
          'style' => 'thumbnail',
          'label' => 'Thumbnail'
        )
      );
    }
    $form['photos_thumb_count'] = array(
      '#type' => 'hidden',
      '#default_value' => $num,
    );
    $form['thumb'] = array(
      '#title' => t('Image sizes'),
      '#weight' => -4,
      '#type' => 'fieldset',
      '#description' => t('Default image sizes. Note: if an image style is deleted after it has been in use for some time that may result in broken external image links (i.e. from the share code and shared galleries).'),
      '#collapsible' => TRUE,
    );
    $thumb_options = image_style_options();
    if (empty($thumb_options)) {
      $form['thumb']['image_style'] = array(
        '#markup' => '<p>One or more image styles required: ' . \Drupal::l(t('add image styles'), \Drupal\Core\Url::fromRoute('entity.image_style.collection')) . '.</p>'
      );
    }
    else {
      $form['thumb']['photos_pager_imagesize'] = array(
        '#type' => 'select',
        '#title' => 'Pager size',
        '#default_value' => $config->get('photos_pager_imagesize'),
        '#description' => '(Default pager block image style.)',
        '#options' => $thumb_options,
        '#required' => TRUE,
      );
      $form['thumb']['photos_cover_imagesize'] = array(
        '#type' => 'select',
        '#title' => 'Cover size',
        '#default_value' => $config->get('photos_cover_imagesize'),
        '#description' => '(Default album cover image style.)',
        '#options' => $thumb_options,
        '#required' => TRUE,
      );
      $form['thumb']['photos_name_0'] = array(
        '#type' => 'textfield',
        '#title' => t('Name'),
        '#default_value' => isset($size['size'][0]['label']) ? $size['size'][0]['label'] : NULL,
        '#size' => '10',
        '#required' => TRUE,
        '#prefix' => '<div class="photos-admin-inline">'
      );

      $form['thumb']['photos_size_0'] = array(
        '#type' => 'select',
        '#title' => 'Thumb size',
        '#default_value' => isset($size['size'][0]['style']) ? $size['size'][0]['style'] : NULL,
        '#options' => $thumb_options,
        '#required' => TRUE,
        '#suffix' => '</div>'
      );
      $empty_option = array('' => '');
      $thumb_options = $empty_option+$thumb_options;
      $form['thumb']['additional_sizes'] = array(
        '#markup' => '<p>Additional image sizes ' . \Drupal::l(t('add more image styles'), \Drupal\Core\Url::fromRoute('entity.image_style.collection')) . '.</p>'
      );

      $additional_sizes = 0;
      for ($i = 1; $i < $num; $i++) {
        $form['thumb']['photos_name_' . $i] = array(
          '#type' => 'textfield',
          '#title' => t('Name'),
          '#default_value' => isset($size['size'][$i]['label']) ? $size['size'][$i]['label'] : NULL,
          '#size' => '10',
          '#prefix' => '<div class="photos-admin-inline">',
        );
        $form['thumb']['photos_size_' . $i] = array(
          '#type' => 'select',
          '#title' => t('Size'),
          '#default_value' => isset($size['size'][$i]['style']) ? $size['size'][$i]['style'] : NULL,
          '#options' => $thumb_options,
          '#suffix' => '</div>',
        );
        $additional_sizes = $i;
      }

      $form['thumb']['photos_additional_sizes'] = array(
        '#type' => 'hidden',
        '#value' => $additional_sizes
      );
    }
    // End thumb settings.
    // Display settings.
    $form['display'] = array(
      '#title' => t('Display Settings'),
      '#type' => 'fieldset',
      '#collapsible' => TRUE,
    );

    $form['display']['global'] = array(
      '#type' => 'fieldset',
      '#title' => t('Global Settings'),
      '#collapsible' => TRUE,
      '#description' => t('Albums basic display settings')
    );
    $form['display']['page'] = array(
      '#type' => 'fieldset',
      '#title' => t('Page Settings'),
      '#collapsible' => TRUE,
      '#description' => t('Page (e.g: node/[nid]) display settings'),
      '#prefix' => '<div id="photos-form-page">',
      '#suffix' => '</div>'
    );
    $form['display']['teaser'] = array(
      '#type' => 'fieldset',
      '#title' => t('Teaser Settings'),
      '#collapsible' => TRUE,
      '#description' => t('Teaser display settings'),
      '#prefix' => '<div id="photos-form-teaser">',
      '#suffix' => '</div>'
    );
    $form['display']['global']['photos_display_viewpager'] = array(
      '#type' => 'number',
      '#default_value' => $config->get('photos_display_viewpager'),
      '#title' => t('How many images show in each page?'),
      '#required' => TRUE,
      '#min' => 1,
      '#step' => 1,
    );
    $form['display']['global']['photos_display_imageorder'] = array(
      '#type' => 'select',
      '#title' => t('Image display order'),
      '#required' => TRUE,
      '#default_value' => $config->get('photos_display_imageorder'),
      '#options' => _photos_order_label()
    );
    $list_imagesize = $config->get('photos_display_list_imagesize');
    $view_imagesize = $config->get('photos_display_view_imagesize');
    $size_options = _photos_select_size();
    $form['display']['global']['photos_display_list_imagesize'] = array(
      '#type' => 'select',
      '#title' => t('Image display size (list)'),
      '#required' => TRUE,
      '#default_value' => $list_imagesize,
      '#description' => t('Displayed in the list (e.g: photos/album/[nid]) of image size.'),
      '#options' => $size_options
    );
    $form['display']['global']['photos_display_view_imagesize'] = array(
      '#type' => 'select',
      '#title' => t('Image display size (page)'),
      '#required' => TRUE,
      '#default_value' => $view_imagesize,
      '#description' => t('Displayed in the page (e.g: photos/image/[fid]) of image size.'),
      '#options' => $size_options
    );
    $form['display']['global']['photos_display_user'] = array(
      '#type' => 'radios',
      '#title' => t('Allow users to modify this setting when they create a new album.'),
      '#default_value' => $config->get('photos_display_user') ?: 0,
      '#options' => array(t('Disabled'), t('Enabled'))
    );
    $form['display']['page']['photos_display_page_display'] = array(
      '#type' => 'radios',
      '#default_value' => $config->get('photos_display_page_display'),
      '#title' => t('Display setting'),
      '#required' => TRUE,
      '#options' => array(t('Do not display'), t('Display cover'), t('Display thumbnails'))
    );
    $form['display']['page']['photos_display_full_viewnum'] = array(
      '#type' => 'number',
      '#default_value' => $config->get('photos_display_full_viewnum'),
      '#title' => t('Display quantity'),
      '#required' => TRUE,
      '#min' => 1,
      '#step' => 1,
      '#prefix' => '<div class="photos-form-count">'
    );
    $form['display']['page']['photos_display_full_imagesize'] = array(
      '#type' => 'select',
      '#title' => t('Image display size'),
      '#required' => TRUE,
      '#default_value' => $config->get('photos_display_full_imagesize'),
      '#options' => $size_options,
      '#suffix' => '</div>'
    );
    $form['display']['page']['photos_display_page_user'] = array(
      '#type' => 'radios',
      '#title' => t('Allow users to modify this setting when they create a new album.'),
      '#default_value' => $config->get('photos_display_page_user') ?: 0,
      '#options' => array(t('Disabled'), t('Enabled'))
    );
    $form['display']['teaser']['photos_display_teaser_display'] = array(
      '#type' => 'radios',
      '#default_value' => $config->get('photos_display_teaser_display'),
      '#title' => t('Display setting'),
      '#required' => TRUE,
      '#options' => array(t('Do not display'), t('Display cover'), t('Display thumbnails'))
    );
    $form['display']['teaser']['photos_display_teaser_viewnum'] = array(
      '#type' => 'number',
      '#default_value' => $config->get('photos_display_teaser_viewnum'),
      '#title' => t('Display quantity'),
      '#required' => TRUE,
      '#min' => 1,
      '#step' => 1,
      '#prefix' => '<div class="photos-form-count">'
    );
    $form['display']['teaser']['photos_display_teaser_imagesize'] = array(
      '#type' => 'select',
      '#title' => t('Image display size'),
      '#required' => TRUE,
      '#default_value' => $config->get('photos_display_teaser_imagesize'),
      '#options' => $size_options,
      '#suffix' => '</div>'
    );
    $form['display']['teaser']['photos_display_teaser_user'] = array(
      '#type' => 'radios',
      '#title' => t('Allow users to modify this setting when they create a new album.'),
      '#default_value' => $config->get('photos_display_teaser_user') ?: 0,
      '#options' => array(t('Disabled'), t('Enabled'))
    );
    // Count settings.
    $form['count'] = array(
      '#title' => t('Statistics'),
      '#type' => 'fieldset',
      '#collapsible' => TRUE,
    );
    $form['count']['photos_image_count'] = array(
      '#type' => 'radios',
      '#title' => t('Count image views'),
      '#default_value' => $config->get('photos_image_count') ?: 0,
      '#description' => t('Increment a counter each time image is viewed.'),
      '#options' => array( t('Enabled'), t('Disabled'))
    );
    $form['count']['photos_user_count_cron'] = array(
      '#type' => 'radios',
      '#title' => t('Image quantity statistics'),
      '#default_value' => $config->get('photos_user_count_cron') ?: 0,
      '#description' => t('Users/Site images and albums quantity statistics.'),
      '#options' => array( t('Update count when cron runs (affect the count update).'), t('Update count when image is uploaded (affect the upload speed)'))
    );
    // End count settings.
    // Exif settings.
    $form['exif'] = array(
      '#title' => t('Exif Settings'),
      '#type' => 'fieldset',
      '#collapsible' => TRUE,
      '#description' => t('These options require the php exif extension to be loaded.')
    );
    $form['exif']['photos_exif'] = array(
      '#type' => 'radios',
      '#title' => t('Show exif information'),
      '#default_value' => $config->get('photos_exif') ?: 0,
      '#description' => t('When the image is available automatically read and display exif information.'),
      '#options' => array(t('Disabled'), t('Enabled')),
      '#disabled' => (extension_loaded('exif') ? FALSE : TRUE)
    );
    $form['exif']['photos_exif_cache'] = array(
      '#type' => 'radios',
      '#title' => t('Cache exif information'),
      '#default_value' => $config->get('photos_exif_cache') ?: 0,
      '#description' => t('Exif information cache can improve access speed.'),
      '#options' => array(t('Do not cache'), t('To database')),
      '#disabled' => (extension_loaded('exif') ? FALSE : TRUE)
    );
    // $form['exif']['photos_exif_help']['#markup'] = '<p>' . t('For custom exif please modify function _photos_exif_tag,
    //  in: .../photos/inc/photos.down.inc') . '</p>';
    // End exif settings.

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // ...
  }

  /**
   * {@inheritdoc}.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    // Build $photos_size array().
    $size = array();
    for ($i = 0; $i < $form_state->getValue('photos_thumb_count'); $i++) {
      if ($form_state->getValue('photos_size_' . $i)) {
        $size[$form_state->getValue('photos_size_' . $i)] = $form_state->getValue('photos_name_' . $i);
      }
    }
    $photos_size = $size;

    // Set number of albums per role.
    $num = $form_state->getValue('num');
    foreach ($num as $rnum => $rcount) {
      $this->config('photos.settings')->set($rnum, $rcount);
    }

    $this->config('photos.settings')
      ->set('photos_access_photos', $form_state->getValue('photos_access_photos'))
      ->set('photos_additional_sizes', $form_state->getValue('photos_additional_sizes'))
      ->set('photos_comment', $form_state->getValue('photos_comment'))
      ->set('photos_cover_imagesize', $form_state->getValue('photos_cover_imagesize'))
      ->set('photos_display_full_imagesize', $form_state->getValue('photos_display_full_imagesize'))
      ->set('photos_display_full_viewnum', $form_state->getValue('photos_display_full_viewnum'))
      ->set('photos_display_imageorder', $form_state->getValue('photos_display_imageorder'))
      ->set('photos_display_list_imagesize', $form_state->getValue('photos_display_list_imagesize'))
      ->set('photos_display_page_display', $form_state->getValue('photos_display_page_display'))
      ->set('photos_display_page_user', $form_state->getValue('photos_display_page_user'))
      ->set('photos_display_teaser_display', $form_state->getValue('photos_display_teaser_display'))
      ->set('photos_display_teaser_imagesize', $form_state->getValue('photos_display_teaser_imagesize'))
      ->set('photos_display_teaser_user', $form_state->getValue('photos_display_teaser_user'))
      ->set('photos_display_teaser_viewnum', $form_state->getValue('photos_display_teaser_viewnum'))
      ->set('photos_display_user', $form_state->getValue('photos_display_user'))
      ->set('photos_display_view_imagesize', $form_state->getValue('photos_display_view_imagesize'))
      ->set('photos_display_viewpager', $form_state->getValue('photos_display_viewpager'))
      ->set('photos_exif', $form_state->getValue('photos_exif'))
      ->set('photos_exif_cache', $form_state->getValue('photos_exif_cache'))
      ->set('photos_image_count', $form_state->getValue('photos_image_count'))
      ->set('photos_num', $form_state->getValue('photos_num'))
      ->set('photos_pager_imagesize', $form_state->getValue('photos_pager_imagesize'))
      ->set('photos_path', $form_state->getValue('photos_path'))
      ->set('photos_plupload_status', $form_state->getValue('photos_plupload_status'))
      ->set('photos_print_sizes', $form_state->getValue('photos_print_sizes'))
      ->set('photos_rname', $form_state->getValue('photos_rname'))
      ->set('photos_size', $photos_size)
      ->set('photos_size_max', $form_state->getValue('photos_size_max'))
      ->set('photos_upzip', $form_state->getValue('photos_upzip'))
      ->set('photos_user_count_cron', $form_state->getValue('photos_user_count_cron'))
      ->set('photos_vote', $form_state->getValue('photos_vote'))
      ->save();

    // Set warning if private file path is not set.
    if (!PrivateStream::basePath() && $form_state->getValue('photos_access_photos')) {
      drupal_set_message(t('Warning: image files can still be accessed by visiting the direct URL.
        For better security, ask your website admin to setup a private file path.'), 'warning');
    }
  }

  /**
   * {@inheritdoc}.
   */
  protected function getEditableConfigNames() {
    return [
      'photos.settings',
    ];
  }

}
