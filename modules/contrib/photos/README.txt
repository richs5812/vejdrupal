version:8.x-4.x

INSTALL
-------

  *Depends on core image and file module(s).
  *Content type photos is created automatically as primary photo album node type.
  *Configure global settings: admin/config/media/photos
  *Update user permissions.

  *For plupload integration.
    1. Install plupload module.
    2. Install plupload library.
    3. Enable plupload setting in photos global settings.
    4. Clear cache!

  *For cropping:
    1. Install Crop API: https://www.drupal.org/project/crop
    2. Install Image Widget Cropper: https://www.drupal.org/project/image_widget_crop
    3. Configure Image Widget Cropper and set up image styles to work with photos admin/config/media/photos.
    4. The image crop widget will appear on the image edit form photos/image/{file}/edit

  *For watermark:
    1. Install Image Effects https://www.drupal.org/project/image_effects
    2. Update image styles as needed.
    3. Tips: resize or scale image before watermark is added.
      Or use watermark scale option.

  *For inline editing photo title and description:
    1. TBD (@todo).
    2. Save jquery.jeditable.js AND jquery.jeditable.mini.js
      from http://www.appelsiini.net/projects/jeditable.
    3. Add both files to libraries/jeditable.

  *For colorbox integration:
    1. Install the Colorbox module (@todo).
    2. On the Colorbox module settings page check "Enable Colorbox load" in
      extra settings and save.


LOCKED AND PASSWORD PROTECTED GALLERIES
---------------------------------------

  *Please note that locked and password protected galleries will only protect
    the actual image URL if a private file path is set. In settings.php be sure
    to set the private file path ($settings['file_private_path']).

  *NGINX if you are using NGINX the following need to be added to your config file
    to allow image styles to be created and accessed on the private file system:
    # Private image styles
    location ~ ^/system/files/styles/ {
        try_files $uri @rewrite;
    }
    # Private photos image styles
    location ~ ^/system/files/photos/ {
        try_files $uri @rewrite;
    }

SEO
---

  *To help prevent bots from voting if anonymous voting is enabled and for
    better SEO you may want to add the following to your robots.txt file:

# Paths (clean URLs)
Disallow: /photos/image/*/vote/
Disallow: /photos/zoom/

# Paths (no clean URLs)
Disallow: /?q=photos/image/*/vote/
Disallow: /?q=photos/zoom/


UPGRADE
-------

  *Migration from D7 to D8 is available.
  *Make sure D7 is updated to the latest module release (7.x-3.0-rc4 +).
  *Enable photos and photos_access. Follow Drupal migration instructions.
    https://www.drupal.org/upgrade/migrate
  *Check image styles and photos admin settings: admin/config/media/photos
  *Check permissions
  *Successfully tested migration from D7 to D8.1.2.


FEATURES
--------

  photos:
  *Create photo galleries.
  *Upload and manage images.
  *Upload multiple images with Plupload.
  *Comment on images (@todo).
  *Vote on images (@todo).
  *Integrates with core image styles.
  *Support for Drupal core private file system.

  photos_access
  *Lock albums.
  *Password protected albums.
  *Create list of users who can access certain albums (@todo).
  *Create list of users who can edit albums, collaborators (@todo).


CREDITS
-------

  photos search integration by:
  R o n a l d   B a r n e s
